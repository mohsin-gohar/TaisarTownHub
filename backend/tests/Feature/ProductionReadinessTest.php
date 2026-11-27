<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Production-readiness contracts:
 *  - the deploy preflight command exits 0 in a healthy environment;
 *  - /api/health actually probes the database instead of claiming health;
 *  - private API responses stay same-origin while public media may be embedded.
 */
class ProductionReadinessTest extends EnterpriseTestCase
{
    public function test_production_check_command_reports_no_hard_failures(): void
    {
        $exitCode = Artisan::call('app:production-check', ['--json' => true]);
        $output = Artisan::output();
        $report = json_decode($output, true);

        $this->assertSame(0, $exitCode, 'Healthy environments must pass the preflight gate.');
        $this->assertIsArray($report, 'The --json flag must emit a JSON report: ' . $output);
        $this->assertSame(0, $report['summary']['fail']);

        $keys = array_column($report['checks'], 'key');
        foreach (['app_debug', 'app_key', 'database', 'migrations', 'storage', 'cors', 'queue', 'cache', 'session'] as $key) {
            $this->assertContains($key, $keys, "Missing production check: {$key}");
        }
    }

    public function test_health_endpoint_probes_the_database_and_returns_security_headers(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('database', ucfirst(DB::connection()->getDriverName()) . ' connected')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }
    public function test_production_env_template_has_no_local_or_wildcard_cors_origins(): void
    {
        $template = file_get_contents(base_path('.env.example'));

        $this->assertIsString($template);
        preg_match('/^CORS_ALLOWED_ORIGINS=(.*)$/m', $template, $matches);
        $this->assertArrayHasKey(1, $matches, 'The production template must define CORS_ALLOWED_ORIGINS.');

        $origins = array_filter(array_map('trim', explode(',', $matches[1])));
        $this->assertNotEmpty($origins);
        foreach ($origins as $origin) {
            $this->assertNotSame('*', $origin, 'Wildcard CORS is forbidden in the production template.');
            $this->assertStringNotContainsString('localhost', strtolower($origin));
            $this->assertStringNotContainsString('127.0.0.1', $origin);
            $this->assertStringStartsWith('https://', $origin);
        }
    }

}
