<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Applicantfile;
use App\Services\ApplicantFileStore;
use App\Services\ImageProcessor;
use App\Support\ApplicantRules;
use App\Support\CompanyContext;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    public function __construct(private ImageProcessor $images, private ApplicantFileStore $store, private CompanyContext $context)
    {
    }

    private function fileRules(): array
    {
        return ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . config('applicant.image.max_upload_kb')];
    }

    private function item(Applicantfile $f): array
    {
        return [
            'id'            => $f->id,
            'type'          => $f->type,
            'original_name' => $f->originalName,
            'url'           => $this->store->url($f->filename),
            'created_at'    => optional($f->created_at)->toIso8601String(),
        ];
    }

    /** Hanya file milik pelamar yang sedang login. */
    private function ownFile(Request $request, int $id): Applicantfile
    {
        return Applicantfile::where('id_karyawan', $request->user('applicant')->applicant_id)->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        $files = Applicantfile::where('id_karyawan', $request->user('applicant')->applicant_id)
            ->whereIn('type', array_keys($this->context->documents()))->orderBy('id')->get();

        return response()->json(['files' => $files->map(fn ($f) => $this->item($f))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user('applicant');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys($this->context->documents()))],
            'file' => $this->fileRules(),
        ], ApplicantRules::messages(), ApplicantRules::attributes());

        $doc   = $this->context->documents()[$data['type']];
        $count = Applicantfile::where('id_karyawan', $user->applicant_id)->where('type', $data['type'])->count();
        if ($count >= $doc['max_files']) {
            throw ValidationException::withMessages([
                'file' => ["Jumlah file {$doc['label']} maksimal {$doc['max_files']}. Hapus atau ganti file yang sudah ada."],
            ]);
        }

        $binary = $this->images->toWebp($request->file('file'));
        $path   = $this->store->put($this->context->get()->code, $user->applicant_id, $data['type'], $binary);

        try {
            $record = Applicantfile::create([
                'id_karyawan'  => $user->applicant_id,
                'type'         => $data['type'],
                'originalName' => Text::safeFilename($request->file('file')->getClientOriginalName()),
                'filename'     => $path,
            ]);
        } catch (\Throwable $e) {
            $this->store->delete($path); // jangan tinggalkan file yatim di S3
            throw $e;
        }

        return response()->json(['file' => $this->item($record)], 201);
    }

    /** Ganti isi file yang sudah ada. */
    public function replace(Request $request, int $id): JsonResponse
    {
        $record = $this->ownFile($request, $id);
        $request->validate(['file' => $this->fileRules()], ApplicantRules::messages(), ApplicantRules::attributes());

        $binary  = $this->images->toWebp($request->file('file'));
        $oldPath = $record->filename;
        $newPath = $this->store->put($this->context->get()->code, $record->id_karyawan, $record->type ?: 'file', $binary);

        try {
            $record->update([
                'originalName' => Text::safeFilename($request->file('file')->getClientOriginalName()),
                'filename'     => $newPath,
            ]);
        } catch (\Throwable $e) {
            $this->store->delete($newPath);
            throw $e;
        }

        try {
            $this->store->delete($oldPath);
        } catch (\Throwable $e) {
            report($e); // file baru sudah aktif; file lama gagal dihapus hanya dicatat
        }

        return response()->json(['file' => $this->item($record->fresh())]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user   = $request->user('applicant');
        $record = $this->ownFile($request, $id);

        // Setelah submit, dokumen wajib tidak boleh dikosongkan (cukup diganti).
        $required = $this->context->documents()[$record->type]["required"] ?? false;
        if ((int) $user->status === 1 && $required) {
            $left = Applicantfile::where('id_karyawan', $user->applicant_id)->where('type', $record->type)->count();
            if ($left <= 1) {
                throw ValidationException::withMessages([
                    'file' => ['Dokumen wajib tidak bisa dikosongkan. Gunakan "Ganti" untuk mengubah file.'],
                ]);
            }
        }

        try {
            $this->store->delete($record->filename);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'File gagal dihapus dari penyimpanan. Coba lagi.'], 500);
        }
        $record->delete();

        return response()->json(['message' => 'File dihapus.']);
    }
}
