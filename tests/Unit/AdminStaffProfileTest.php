<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminStaffProfileTest extends TestCase
{
    protected function controller(): DashboardController
    {
        return $this->app->make(DashboardController::class);
    }

    protected function resolveDepartment(Request $request, ?object $existing)
    {
        $method = new \ReflectionMethod(DashboardController::class, 'resolveDepartment');
        $method->setAccessible(true);

        return $method->invoke($this->controller(), $request, $existing);
    }

    public function test_edit_form_declares_exactly_one_department_id_field(): void
    {
        $html = file_get_contents(resource_path('views/admin/users-edit.blade.php'));

        $this->assertSame(
            1,
            substr_count($html, 'name="department_id"'),
            'users-edit.blade.php must declare exactly one field named department_id.'
        );

        // The student picker must use its own name.
        $this->assertStringContainsString('name="student_department_id"', $html);
    }

    public function test_absent_department_field_preserves_the_stored_department(): void
    {
        // A partial payload (name only) must never be able to blank the column.
        $request = Request::create('/admin/users/3', 'PUT', ['name' => 'Prof. Juan Santos']);

        $this->assertSame(2, $this->resolveDepartment($request, (object) ['department_id' => 2]));
    }

    public function test_absent_department_field_with_no_stored_value_stays_null(): void
    {
        $request = Request::create('/admin/users/3', 'PUT', ['name' => 'Prof. Juan Santos']);

        $this->assertNull($this->resolveDepartment($request, (object) ['department_id' => null]));
        $this->assertNull($this->resolveDepartment($request, null));
    }

    public function test_explicitly_empty_department_is_treated_as_a_clear(): void
    {
        $request = Request::create('/admin/users/3', 'PUT', ['department_id' => '']);

        $this->assertNull($this->resolveDepartment($request, (object) ['department_id' => 2]));
    }

    public function test_a_submitted_department_wins(): void
    {
        $request = Request::create('/admin/users/3', 'PUT', ['department_id' => '4']);

        $this->assertSame(4, $this->resolveDepartment($request, (object) ['department_id' => 2]));
    }

    public function test_duplicate_department_id_in_a_raw_body_resolves_last_wins(): void
    {
        // Documents the underlying PHP behaviour that caused the bug, so the form is
        // never allowed to rely on field order again.
        $body = 'department_id=2&name=Prof.+Juan+Santos&department_id=';
        parse_str($body, $parsed);

        $this->assertSame('', $parsed['department_id']);
    }
}
