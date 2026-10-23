<?php

namespace App\Providers;

use App\Services\Api\MockApiClient;
use App\Services\Api\MirrorWriter;
use App\Services\RiskThresholdService;
use App\Services\StudentNotificationService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MockApiClient::class, fn () => new MockApiClient());

        // MirrorWriter must also be a singleton: defer()/flush() accumulate the
        // mirrors for one unit of work, so the state has to survive injection.
        $this->app->singleton(MirrorWriter::class, fn ($app) => new MirrorWriter($app->make(MockApiClient::class)));

        $this->app->singleton(RiskThresholdService::class, fn () => new RiskThresholdService());

        $this->app->singleton(StudentNotificationService::class);

        $this->app->singleton(\App\Services\CounselorNavigationService::class);

        $this->app->singleton(\App\Services\Reports\CounselorActivityReportService::class);

        $this->app->singleton(\App\Services\AcademicHeadNavigationService::class);

        $this->app->singleton(\App\Services\Reports\EndOfTermReportInsightService::class);

        foreach ([
            \App\Repositories\Api\AcademicStructureRepository::class,
            \App\Repositories\Api\AttendanceRepository::class,
            \App\Repositories\Api\AttendanceSummaryRepository::class,
            \App\Repositories\Api\CalendarRepository::class,
            \App\Repositories\Api\GradeRepository::class,
            \App\Repositories\Api\ParentRepository::class,
            \App\Repositories\Api\PaymentRepository::class,
            \App\Repositories\Api\StaffRepository::class,
            \App\Repositories\Api\StudentRepository::class,
            \App\Repositories\Api\SubjectRepository::class,
            \App\Repositories\Local\RiskScoreRepository::class,
        ] as $repository) {
            $this->app->singleton($repository);
        }
    }

    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            if ($user === null || !$user->isStudent()) {
                return;
            }

            $alerts = app(StudentNotificationService::class)->forEmail($user->email);

            $view->with('studentBellAlerts', $alerts);
            $view->with('studentBellUnread', $alerts->where('is_acknowledged', false)->count());
        });
    }
}
