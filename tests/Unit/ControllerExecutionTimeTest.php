<?php

namespace Tests\Unit;

use App\Http\Controllers\Controller;
use Tests\TestCase;

/**
 * Guards the per-request execution-time ceiling that keeps the long, synchronous
 * AI endpoints (block risk scoring, block recommendation generation) alive on a
 * host whose php.ini still ships the 30-second default — without raising the
 * limit for any other page.
 */
class ControllerExecutionTimeTest extends TestCase
{
    private string|false $original = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->original = ini_get('max_execution_time');
    }

    protected function tearDown(): void
    {
        if ($this->original !== false) {
            @ini_set('max_execution_time', (string) $this->original);
        }

        parent::tearDown();
    }

    /**
     * Apply the guard with a given configured ceiling and report the ceiling
     * PHP is left holding for the request.
     */
    private function callGuard(int $seconds): string|false
    {
        config(['services.aistudio.max_execution_seconds' => $seconds]);

        $controller = new class extends Controller {
            public function apply(): void
            {
                $this->extendAiExecutionTime();
            }
        };

        $controller->apply();

        return ini_get('max_execution_time');
    }

    public function test_the_config_ships_a_300_second_default(): void
    {
        // Asserted against the file (not .env) so the documented default cannot
        // be changed or dropped without this test noticing.
        $config = file_get_contents(config_path('services.php'));

        $this->assertMatchesRegularExpression(
            "/'max_execution_seconds'\s*=>\s*\(int\)\s*env\('AI_STUDIO_MAX_EXECUTION_SECONDS',\s*300\)/",
            $config
        );
    }

    public function test_it_raises_the_ceiling_for_the_current_request(): void
    {
        $this->assertSame('300', $this->callGuard(300));
    }

    public function test_it_honours_a_configured_ceiling(): void
    {
        $this->assertSame('42', $this->callGuard(42));
    }

    public function test_zero_leaves_the_host_value_untouched(): void
    {
        // 0 disables the override: a host that has already tuned php.ini keeps
        // its own value, and the guard never lowers a ceiling.
        @ini_set('max_execution_time', '7');

        $this->assertSame('7', $this->callGuard(0));
    }
}