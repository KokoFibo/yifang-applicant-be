<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicantdata;
use App\Models\Applicantfile;
use App\Models\Company;
use App\Services\ApplicantFileStore;
use App\Support\ApplicantRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API baca-saja untuk konsol admin (Astro di Cloudflare). Dilindungi AdminApiToken.
 * Berada di luar ResolveCompany: mencakup semua perusahaan.
 */
class ApplicantAdminController extends Controller
{
    private const FACETS = ['gender', 'agama', 'etnis', 'pendidikan', 'status_pernikahan', 'golongan_darah', 'ptkp'];
    private const SORTS  = ['created_at', 'nama', 'email', 'tgl_lahir', 'etnis', 'pendidikan', 'status'];
    private const TZ     = 'Asia/Jakarta';

    // ---------------------------------------------------------------- filter bersama

    /** Nilai ganda dari ?key[]=a&key[]=b atau ?key=a,b */
    private function multi(Request $request, string $key): array
    {
        $v = $request->input($key, []);
        if (is_string($v)) {
            $v = explode(',', $v);
        }
        if (! is_array($v)) {
            return [];
        }
        $v = array_map(fn ($x) => is_scalar($x) ? trim((string) $x) : '', $v);
        $v = array_values(array_unique(array_filter($v, fn ($x) => $x !== '' && mb_strlen($x) <= 60)));

        return array_slice($v, 0, 30);
    }

    private function parseDay(?string $value, bool $endOfDay): ?Carbon
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            $d = Carbon::createFromFormat('Y-m-d', $value, self::TZ);
        } catch (\Throwable $e) {
            return null;
        }

        return ($endOfDay ? $d->endOfDay() : $d->startOfDay())->setTimezone(config('app.timezone'));
    }

    private function filtered(Request $request): Builder
    {
        $q = Applicantdata::query();

        if ($codes = $this->multi($request, 'company')) {
            $none = in_array('_none', $codes, true);
            $ids  = Company::whereIn('code', $codes)->pluck('id');
            $q->where(function ($w) use ($ids, $none) {
                $w->whereIn('company_id', $ids);
                if ($none) {
                    $w->orWhereNull('company_id');
                }
            });
        }

        foreach (self::FACETS as $facet) {
            if ($values = $this->multi($request, $facet)) {
                $q->whereIn($facet, $values);
            }
        }

        $status = $request->input('status');
        if ($status === 'sent') {
            $q->where('status', '>=', 1);
        } elseif ($status === 'draft') {
            $q->where('status', 0);
        }

        $verified = $request->input('verified');
        if ($verified === 'yes') {
            $q->whereNotNull('email_verified_at');
        } elseif ($verified === 'no') {
            $q->whereNull('email_verified_at');
        }

        if (is_numeric($min = $request->input('min_age'))) {
            $q->where('tgl_lahir', '<=', now()->subYears((int) $min)->toDateString());
        }
        if (is_numeric($max = $request->input('max_age'))) {
            $q->where('tgl_lahir', '>', now()->subYears((int) $max + 1)->toDateString());
        }

        if ($from = $this->parseDay($request->input('from'), false)) {
            $q->where('created_at', '>=', $from);
        }
        if ($to = $this->parseDay($request->input('to'), true)) {
            $q->where('created_at', '<=', $to);
        }

        $term = mb_substr(trim((string) $request->input('q', '')), 0, 100);
        if ($term !== '') {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $q->where(function ($w) use ($like) {
                foreach (['nama', 'email', 'hp', 'no_identitas', 'tempat_lahir', 'nama_kampus', 'jurusan'] as $col) {
                    $w->orWhere($col, 'like', $like);
                }
            });
        }

        return $q;
    }

    private function sorted(Builder $q, Request $request): void
    {
        $dir  = $request->input('dir') === 'asc' ? 'asc' : 'desc';
        $sort = (string) $request->input('sort', 'created_at');

        if ($sort === 'company') {
            $q->orderByRaw('(select name from companies where companies.id = applicantdatas.company_id) ' . $dir);
        } elseif (in_array($sort, self::SORTS, true)) {
            $q->orderBy($sort, $dir);
        } else {
            $q->orderBy('created_at', 'desc');
        }
        $q->orderBy('id', 'desc');
    }

    // ---------------------------------------------------------------- pembentuk data

    private function birthDate(Applicantdata $a): ?Carbon
    {
        try {
            return $a->tgl_lahir;
        } catch (\Throwable $e) {
            return null; // data lama dengan tanggal tidak valid
        }
    }

    private function row(Applicantdata $a): array
    {
        $birth = $this->birthDate($a);

        return [
            'id'             => $a->id,
            'applicant_id'   => $a->applicant_id,
            'company'        => $a->company ? ['code' => $a->company->code, 'name' => $a->company->name] : null,
            'nama'           => $a->nama,
            'email'          => $a->email,
            'hp'             => $a->hp,
            'gender'         => $a->gender,
            'tempat_lahir'   => $a->tempat_lahir,
            'tgl_lahir'      => $birth?->toDateString(),
            'umur'           => $birth?->age,
            'agama'          => $a->agama,
            'etnis'          => $a->etnis,
            'pendidikan'     => $a->pendidikan,
            'jurusan'        => $a->jurusan,
            'nama_kampus'    => $a->nama_kampus,
            'status'         => (int) $a->status,
            'email_verified' => $a->email_verified_at !== null,
            'files_count'    => (int) ($a->files_count ?? 0),
            'created_at'     => $a->created_at?->toIso8601String(),
        ];
    }

    private function groupCounts(string $col, int $limit = 60): array
    {
        abort_unless(in_array($col, self::FACETS, true), 500);

        return Applicantdata::query()
            ->select($col . ' as label', DB::raw('COUNT(*) as n'))
            ->whereNotNull($col)->where($col, '!=', '')
            ->groupBy($col)->orderByDesc('n')->limit($limit)
            ->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'n' => (int) $r->n])
            ->all();
    }

    // ---------------------------------------------------------------- endpoint

    /** Daftar perusahaan dan pilihan filter (dengan jumlahnya). */
    public function meta(): JsonResponse
    {
        $ptkp = config('applicant.options.ptkp', []);

        $facets = [];
        foreach (self::FACETS as $facet) {
            $facets[$facet] = array_map(fn ($r) => [
                'value' => $r['label'],
                'label' => $facet === 'ptkp' ? ($ptkp[$r['label']] ?? $r['label']) : $r['label'],
                'n'     => $r['n'],
            ], $this->groupCounts($facet));
        }

        return response()->json([
            'companies'  => Company::orderBy('name')->get(['code', 'name'])->map(fn ($c) => ['code' => $c->code, 'name' => $c->name])->values(),
            'unassigned' => Applicantdata::whereNull('company_id')->count(),
            'facets'     => $facets,
        ]);
    }

    public function summary(): JsonResponse
    {
        $byCompany = Company::query()
            ->withCount(['applicants as total', 'applicants as sent' => fn ($q) => $q->where('status', '>=', 1)])
            ->orderBy('name')->get()
            ->map(fn ($c) => ['code' => $c->code, 'name' => $c->name, 'total' => (int) $c->total, 'sent' => (int) $c->sent])
            ->values();

        // Pendaftaran 30 hari terakhir (zona waktu WIB)
        $start  = now(self::TZ)->subDays(29)->startOfDay();
        $counts = Applicantdata::query()
            ->where('created_at', '>=', $start->copy()->setTimezone(config('app.timezone')))
            ->pluck('created_at')
            ->countBy(fn ($d) => $d->copy()->setTimezone(self::TZ)->toDateString());

        $series = [];
        for ($i = 0; $i < 30; $i++) {
            $day      = $start->copy()->addDays($i)->toDateString();
            $series[] = ['date' => $day, 'n' => (int) ($counts[$day] ?? 0)];
        }

        return response()->json([
            'totals' => [
                'total'      => Applicantdata::count(),
                'sent'       => Applicantdata::where('status', '>=', 1)->count(),
                'verified'   => Applicantdata::whereNotNull('email_verified_at')->count(),
                'last7'      => Applicantdata::where('created_at', '>=', now()->subDays(7))->count(),
                'unassigned' => Applicantdata::whereNull('company_id')->count(),
            ],
            'by_company'    => $byCompany,
            'series'        => $series,
            'distributions' => [
                'etnis'             => $this->groupCounts('etnis', 12),
                'pendidikan'        => $this->groupCounts('pendidikan', 12),
                'gender'            => $this->groupCounts('gender', 4),
                'agama'             => $this->groupCounts('agama', 8),
                'status_pernikahan' => $this->groupCounts('status_pernikahan', 6),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50, 100], true) ? (int) $request->input('per_page') : 25;

        $query = $this->filtered($request)->with('company:id,code,name')->withCount('files');
        $this->sorted($query, $request);
        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn ($a) => $this->row($a))->values(),
            'meta' => [
                'total'        => $page->total(),
                'per_page'     => $page->perPage(),
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'from'         => $page->firstItem(),
                'to'           => $page->lastItem(),
            ],
        ]);
    }

    public function show(Request $request, int $id, ApplicantFileStore $store): JsonResponse
    {
        $a = Applicantdata::with('company:id,code,name')->withCount('files')->findOrFail($id);

        $labels = collect(config('applicant.documents'))->map(fn ($d) => $d['label']);
        $files  = Applicantfile::where('id_karyawan', $a->applicant_id)->orderBy('id')->get()->map(function ($f) use ($store, $labels) {
            try {
                $url = $store->url($f->filename);
            } catch (\Throwable $e) {
                report($e);
                $url = null;
            }

            return [
                'id'            => $f->id,
                'type'          => $f->type,
                'label'         => $labels[$f->type] ?? ($f->type ?: 'Lainnya'),
                'original_name' => $f->originalName,
                'url'           => $url,
                'created_at'    => $f->created_at?->toIso8601String(),
            ];
        })->values();

        Log::info('admin.applicant.view', ['id' => $a->id, 'ip' => $request->ip()]);

        $fields = array_merge(['applicant_id', 'email'], ApplicantRules::allFields());

        return response()->json([
            'applicant' => array_merge($this->row($a), Arr::only($a->attributesToArray(), $fields), [
                'tgl_lahir'  => $this->birthDate($a)?->toDateString(),
                'updated_at' => $a->updated_at?->toIso8601String(),
            ]),
            'files' => $files,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with('company:id,code,name')->withCount('files');
        $this->sorted($query, $request);

        Log::info('admin.applicant.export', ['ip' => $request->ip(), 'filters' => $request->query()]);

        $headers = [
            'Perusahaan', 'Nama', 'Email', 'No. HP', 'No. Telepon', 'Tempat Lahir', 'Tanggal Lahir', 'Umur',
            'Jenis Kelamin', 'Status Pernikahan', 'Gol. Darah', 'Agama', 'Etnis', 'PTKP',
            'Pendidikan', 'Jurusan', 'Sekolah/Kampus', 'Jenis Identitas', 'No. Identitas',
            'Alamat Identitas', 'Alamat Tinggal',
            'Kontak Darurat 1', 'No. Kontak 1', 'Hubungan 1', 'Kontak Darurat 2', 'No. Kontak 2', 'Hubungan 2',
            'Status', 'Email Terverifikasi', 'Jumlah Dokumen', 'Terdaftar (WIB)',
        ];
        $statusLabel = [0 => 'Belum dikirim', 1 => 'Sudah dikirim', 2 => 'Diterima'];

        $cell = function ($v): string {
            $v = (string) ($v ?? '');
            if ($v === '') {
                return '';
            }
            // Angka panjang (NIK, nomor HP): paksa sebagai teks di Excel agar tidak jadi 3,2E+15 / kehilangan angka 0
            if (preg_match('/^\+?\d{6,}$/', $v)) {
                return '="' . $v . '"';
            }
            // Mencegah CSV injection (sel yang diawali = + - @)
            if (preg_match('/^[=+\-@\t\r]/', $v)) {
                return "'" . $v;
            }

            return $v;
        };

        $filename = 'pelamar-' . now(self::TZ)->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query, $headers, $statusLabel, $cell) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, $headers);

            foreach ($query->lazy(500) as $a) {
                $birth = $this->birthDate($a);
                fputcsv($out, array_map($cell, [
                    $a->company?->name, $a->nama, $a->email, $a->hp, $a->telp, $a->tempat_lahir,
                    $birth?->toDateString(), $birth?->age, $a->gender, $a->status_pernikahan, $a->golongan_darah,
                    $a->agama, $a->etnis, $a->ptkp, $a->pendidikan, $a->jurusan, $a->nama_kampus,
                    $a->jenis_identitas, $a->no_identitas, $a->alamat_identitas, $a->alamat_tinggal_sekarang,
                    $a->nama_contact_darurat, $a->contact_darurat_1, $a->hubungan_1,
                    $a->nama_contact_darurat_2, $a->contact_darurat_2, $a->hubungan_2,
                    $statusLabel[(int) $a->status] ?? (string) $a->status,
                    $a->email_verified_at ? 'Ya' : 'Tidak',
                    $a->files_count ?? 0,
                    $a->created_at?->copy()->setTimezone(self::TZ)->format('Y-m-d H:i'),
                ]));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
