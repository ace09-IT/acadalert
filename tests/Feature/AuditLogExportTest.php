<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditLogExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();

        config()->set('services.mock_api', [
            'base_url' => 'http://bulk.test',
            'core_url' => 'http://core.test',
            'timeout' => 5,
            'retry_times' => 1,
            'retry_sleep_ms' => 0,
            'cache_ttl_seconds' => 0,
            'concurrency' => 5,
            'attendance_chunk' => 25,
            'writes_enabled' => true,
            'core_resources' => ['users'],
        ]);

        // `users` is served by the mock API, so the page's in-PHP join needs it.
        Http::fake([
            'core.test/*' => Http::response($this->userDirectory(), 200),
            'bulk.test/*' => Http::response([], 200),
        ]);
    }

    protected function createTables(): void
    {
        foreach (['audit_logs', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('admin');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 100);
            $table->string('model_type', 100)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    protected function user(string $role = 'admin'): User
    {
        return User::create([
            'name' => 'Admin User',
            'email' => $role . '@example.test',
            'password' => 'secret-password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    protected function userDirectory(): array
    {
        return [
            ['id' => '1', 'name' => 'Ana Reyes', 'email' => 'ana@example.test', 'role' => 'admin'],
            ['id' => '2', 'name' => 'Ben Cruz', 'email' => 'ben@example.test', 'role' => 'guidance_counselor'],
        ];
    }

    protected function log(int $userId, string $action, string $at, string $ip = '10.0.0.1'): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $userId,
            'action' => $action,
            'model_type' => 'Admin',
            'model_id' => null,
            'old_values' => null,
            'new_values' => null,
            'ip_address' => $ip,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    protected function csvRows(string $content): array
    {
        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    public function test_the_export_downloads_a_csv_of_exactly_the_filtered_rows(): void
    {
        $this->log(1, 'USER_LOGIN', '2026-07-10 08:00:00', '10.0.0.1');
        $this->log(1, 'USER_LOGIN', '2026-07-12 17:30:00', '10.0.0.6');
        $this->log(1, 'USER_CREATED', '2026-07-11 09:00:00', '10.0.0.2');
        $this->log(2, 'USER_LOGIN', '2026-07-11 10:00:00', '10.0.0.3');
        $this->log(1, 'USER_LOGIN', '2026-06-30 23:59:58', '10.0.0.4');
        $this->log(1, 'USER_LOGIN', '2026-07-13 00:00:01', '10.0.0.5');

        $response = $this->actingAs($this->user())->get(route('admin.audit.logs.export', [
            'date_from' => '2026-07-10',
            'date_to' => '2026-07-12',
            'user' => '1',
            'action' => 'USER_LOGIN',
        ]));

        $response->assertOk();

        // The browser must be told to SAVE this, not to render it.
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.csv', (string) $response->headers->get('content-disposition'));

        $rows = $this->csvRows($response->getContent());

        // The same four columns, in the same order, as the table on the page.
        $this->assertSame(['Date/Time', 'User', 'Action', 'IP Address'], $rows[0]);
        $this->assertCount(3, $rows);

        $this->assertSame(['2026-07-12 17:30:00', 'Ana Reyes', 'USER_LOGIN', '10.0.0.6'], $rows[1]);
        $this->assertSame(['2026-07-10 08:00:00', 'Ana Reyes', 'USER_LOGIN', '10.0.0.1'], $rows[2]);
    }

    public function test_the_export_without_filters_matches_the_pages_own_defaults(): void
    {
        $this->log(1, 'USER_LOGIN', now()->subDays(3)->format('Y-m-d H:i:s'), '10.0.0.1');
        $this->log(2, 'USER_LOGIN', now()->subDays(45)->format('Y-m-d H:i:s'), '10.0.0.2');

        $response = $this->actingAs($this->user())->get(route('admin.audit.logs.export'));

        $response->assertOk();

        $rows = $this->csvRows($response->getContent());

        $this->assertCount(2, $rows); // header + the one in-window row
        // `user` and `action` default to all, so only the window excludes the other.
        $this->assertSame('Ana Reyes', $rows[1][1]);
    }

    public function test_the_export_carries_the_whole_filtered_set_not_only_the_first_page(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->log(1, 'USER_LOGIN', now()->subDay()->format('Y-m-d H:i:s'), '10.0.0.1');
        }

        $response = $this->actingAs($this->user())->get(route('admin.audit.logs.export'));

        $response->assertOk();

        // The page paginates at 50; the report must not.
        $this->assertCount(61, $this->csvRows($response->getContent()));
    }

    public function test_the_page_button_exports_instead_of_printing(): void
    {
        $this->log(1, 'USER_LOGIN', now()->subDay()->format('Y-m-d H:i:s'), '10.0.0.1');

        $response = $this->actingAs($this->user())->get(route('admin.audit.logs'));

        $response->assertOk();

        $response->assertDontSee('window.print()', false);
        $response->assertSee('id="exportLogsBtn"', false);
        $response->assertSee('onclick="exportAuditLogs()"', false);
        // The old print handler must not survive anywhere on the page.
        $response->assertDontSee('fa-print', false);
    }

    public function test_the_page_hands_the_export_the_same_filters_it_applied(): void
    {
        $response = $this->actingAs($this->user())->get(route('admin.audit.logs', [
            'date_from' => '2026-07-10',
            'date_to' => '2026-07-12',
            'user' => '1',
            'action' => 'USER_LOGIN',
        ]));

        $response->assertOk();

        $response->assertSee('export?date_from=2026-07-10', false);
        $response->assertSee('date_to=2026-07-12', false);
        $response->assertSee('user=1', false);
        $response->assertSee('action=USER_LOGIN', false);
    }

    public function test_a_non_admin_cannot_export_the_logs(): void
    {
        $this->actingAs($this->user('guidance_counselor'))
            ->get(route('admin.audit.logs.export'))
            ->assertRedirect(route('dashboard'));
    }
}