<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminApiAccessTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-rahasia-untuk-tes';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'admin.api_token_hash' => hash('sha256', self::TOKEN),
            'admin.allowed_hosts'  => [],
        ]);
    }

    private function good(string $url = '/api/admin/meta')
    {
        return $this->withHeader('Authorization', 'Bearer ' . self::TOKEN)->getJson($url);
    }

    private function bad(string $url = '/api/admin/meta')
    {
        return $this->withHeader('Authorization', 'Bearer salah')->getJson($url);
    }

    public function test_requires_a_valid_token(): void
    {
        $this->getJson('/api/admin/meta')->assertStatus(401);
        $this->bad()->assertStatus(401);
        $this->good()->assertOk();
    }

    public function test_is_disabled_when_no_token_hash_is_configured(): void
    {
        config(['admin.api_token_hash' => '']);

        $this->good()->assertStatus(401);
    }

    public function test_repeated_bad_tokens_are_blocked_with_429(): void
    {
        for ($i = 0; $i < AdminApiToken::MAX_FAILURES; $i++) {
            $this->bad()->assertStatus(401);
        }

        $this->bad()->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_a_valid_token_is_never_locked_out_by_other_peoples_failures(): void
    {
        for ($i = 0; $i < AdminApiToken::MAX_FAILURES + 3; $i++) {
            $this->bad();
        }

        $this->good()->assertOk();
    }

    public function test_failures_are_counted_per_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);
        for ($i = 0; $i < AdminApiToken::MAX_FAILURES; $i++) {
            $this->bad();
        }
        $this->bad()->assertStatus(429);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);
        $this->bad()->assertStatus(401);
    }

    public function test_throttle_runs_before_the_token_check(): void
    {
        // Regresi: dulu AdminApiToken (yang menahan worker 300 ms per token salah) berjalan SEBELUM throttle,
        // sehingga throttle tidak melindungi dari banjir token palsu.
        $route      = app('router')->getRoutes()->match(Request::create('/api/admin/meta', 'GET'));
        $middleware = array_values(array_map('strval', $route->gatherMiddleware()));

        $throttle = null;
        foreach ($middleware as $i => $m) {
            if (str_starts_with($m, 'throttle:')) {
                $throttle = $i;
                break;
            }
        }

        $token = array_search(AdminApiToken::class, $middleware, true);

        $this->assertNotNull($throttle, 'Rute admin harus punya throttle.');
        $this->assertNotFalse($token, 'Rute admin harus memakai AdminApiToken.');
        $this->assertLessThan($token, $throttle, 'throttle harus berjalan sebelum AdminApiToken.');
    }

    public function test_can_be_restricted_to_specific_hosts(): void
    {
        config(['admin.allowed_hosts' => ['api.admin.test']]);

        // Di domain lain (mis. domain tenant) API ini seolah tidak ada, walau tokennya benar.
        $this->good('http://alpha.test/api/admin/meta')->assertStatus(404);
        $this->good('http://api.admin.test/api/admin/meta')->assertOk();
        $this->bad('http://api.admin.test/api/admin/meta')->assertStatus(401);
    }
}
