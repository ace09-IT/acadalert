<!DOCTYPE html>
<html lang="en" data-bs-theme="dark" class="theme-dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $assetVersion = function (string $relative): string {
            $path = public_path($relative);

            return file_exists($path) ? (string) filemtime($path) : '1';
        };
    @endphp

    <script>
        (function () {
            var STORAGE_KEY = 'acadalerts-theme';
            var theme = 'dark';

            try {
                var stored = window.localStorage.getItem(STORAGE_KEY);
                if (stored === 'light' || stored === 'dark') {
                    theme = stored;
                }
            } catch (error) {
            }

            var root = document.documentElement;
            root.setAttribute('data-bs-theme', theme);
            root.classList.remove('theme-dark', 'theme-light');
            root.classList.add('theme-' + theme);
        })();
    </script>

    <title>@yield('title', 'AcadAlert') - Universidad de Dagupan</title>
    
    <!-- Bootstrap 5 CSS (served locally; CDN only as a fallback) -->
    <link href="{{ asset('css/vendor/bootstrap.min.css') }}?v={{ $assetVersion('css/vendor/bootstrap.min.css') }}"
          rel="stylesheet"
          onerror="this.onerror=null;this.href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css';">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="{{ asset('css/app.css') }}?v={{ $assetVersion('css/app.css') }}" rel="stylesheet">

    <link href="{{ asset('css/theme.css') }}?v={{ $assetVersion('css/theme.css') }}" rel="stylesheet">
    
    @stack('styles')
</head>
<body>
    <div id="app">
        <nav class="navbar navbar-expand navbar-dark bg-primary fixed-top">
            <div class="container-fluid">
                <a class="navbar-brand" href="{{ route('dashboard') }}">
                    <span class="navbar-logo">
                        {{-- CHANGED: Use the existing Universidad de Dagupan seal asset. --}}
                        <br>
                        <img src="{{ asset('images/logo/udd-logo.png') }}" alt="Universidad de Dagupan">
                    </span>
                </a>

                <button class="btn btn-link text-white d-lg-none sidebar-toggle-btn" type="button"
                        id="sidebarToggleBtn" onclick="toggleSidebar()"
                        aria-controls="sidebar-wrapper" aria-expanded="false"
                        aria-label="Toggle navigation menu">
                    <i class="fas fa-bars fs-4"></i>
                </button>

                <div class="navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav me-auto">
                        @auth
                            @if(auth()->user()->isAdmin())
                                <li class="nav-item">
                                   
                                </li>
                                <li class="nav-item">
                                    
                                </li>
                                <li class="nav-item">
                                    
                                </li>
                            @endif
                            
                            @if(auth()->user()->isAcademicHead())
                                <li class="nav-item">
                                    
                                </li>
                            @endif
                        
                            
                            @if(auth()->user()->isStudent())
                                <li class="nav-item">
                                    
                                </li>
                            @endif
                        @endauth
                    </ul>
                    
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item d-flex align-items-center">
                            <button type="button" class="theme-toggle" id="themeToggleBtn"
                                    onclick="toggleTheme()"
                                    aria-pressed="true"
                                    aria-label="Switch to light theme"
                                    title="Switch to light theme">
                                <i class="fas fa-sun theme-toggle-icon-sun" aria-hidden="true"></i>
                                <i class="fas fa-moon theme-toggle-icon-moon" aria-hidden="true"></i>
                            </button>
                        </li>
                        @auth
                            @if(auth()->user()->isStudent())
                                @include('partials.student-notifications')
                            @endif

                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" 
                                   id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar-circle bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2" 
                                         style="width: 32px; height: 32px; font-weight: 600; font-size: 14px;">
                                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                                    </div>
                                    <span class="nav-user-name">{{ auth()->user()->name }}</span>
                                    <small class="d-none d-md-inline text-light opacity-75 ms-1">
                                        ({{ ucfirst(str_replace('_', ' ', auth()->user()->role)) }})
                                    </small>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('profile') }}">
                                            <i class="fas fa-user me-2"></i> Profile
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('settings') }}">
                                            <i class="fas fa-cog me-2"></i> Settings
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                                        </a>
                                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                            @csrf
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">
                                    <i class="fas fa-sign-in-alt me-1"></i> Login
                                </a>
                            </li>
                        @endauth
                    </ul>
                </div>
            </div>
        </nav>

        <div class="d-flex" id="wrapper">
            <div class="bg-dark text-white" id="sidebar-wrapper" aria-label="Main navigation">
                <div class="sidebar-heading text-center py-4 border-bottom border-secondary">
                    <span class="sidebar-logo">
                        <img src="{{ asset('images/logo/acadalert_logo.png') }}" alt="AcadAlert">
                    </span>
                </div>
                <div class="list-group list-group-flush my-3">
                    @auth
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.dashboard') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-chart-pie me-2"></i> Dashboard
                            </a>
                            <a href="{{ route('admin.users.index') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.users*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-users me-2"></i> Users
                            </a>
                            <a href="{{ route('admin.academic.index') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.academic*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-building me-2"></i> Academic Structure
                            </a>
                            <a href="{{ route('admin.risk.config') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.risk*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-sliders-h me-2"></i> Risk Settings
                            </a>
                            <a href="{{ route('admin.payments.index') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.payments*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-credit-card me-2"></i> Payments
                            </a>
                            <a href="{{ route('admin.audit.logs') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.audit*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-history me-2"></i> Audit Logs
                            </a>
                            <a href="{{ route('admin.schoolyear.index') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.schoolyear*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-calendar-alt me-2"></i> School Year
                            </a>
                            <a href="{{ route('admin.system.health') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.system*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-server me-2"></i> System Health
                            </a>
                            <a href="{{ route('admin.reports') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.reports*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-file-alt me-2"></i> End-of-Term Reports
                            </a>
                        @endif
                        
                        @if(auth()->user()->isAcademicHead())
                            @php
                                $navHeadDeptId = \App\Http\Middleware\DepartmentIsolationMiddleware::getDepartmentId(request());

                                // The period / school year the page you are on is showing, so the
                                // links below carry you to the SAME period rather than a default.
                                $navHeadPeriod = (string) (request('period') ?: 'Midterm');
                                $navHeadSchoolYear = (string) (request('school_year') ?: '2024-2025');

                                // The block in context, if the current request names one.
                                $navHeadExplicitBlock = (int) (request()->route('blockId') ?? request()->input('block_id') ?? 0);

                                $navHeadNav = app(\App\Services\AcademicHeadNavigationService::class);

                                $navHeadBlockId = (int) ($navHeadNav->resolveCurrentBlockId($navHeadDeptId, $navHeadExplicitBlock ?: null) ?? 0);
                                $navHeadBlockLabel = $navHeadNav->blockLabel($navHeadBlockId);

                                $navHeadOnBlock = request()->routeIs('academic-head.block');
                                $navHeadOnRiskScoring = request()->routeIs('academic-head.riskScoring');
                                $navHeadLatestReportId = $navHeadNav->latestReportId($navHeadDeptId);
                            @endphp

                            <div class="sidebar-section">Main</div>

                            <a href="{{ route('academic-head.department', ['period' => $navHeadPeriod, 'school_year' => $navHeadSchoolYear]) }}"
                               class="sidebar-link {{ request()->routeIs('academic-head.department') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-chart-pie"></i> Department Overview
                            </a>

                            <a href="{{ route('academic-head.blocks.index', ['period' => $navHeadPeriod, 'school_year' => $navHeadSchoolYear]) }}"
                               class="sidebar-link {{ request()->routeIs('academic-head.blocks*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-layer-group"></i> Blocks
                            </a>
                            @if($navHeadBlockId > 0)
                                <a href="{{ route('academic-head.block', ['blockId' => $navHeadBlockId, 'period' => $navHeadPeriod, 'school_year' => $navHeadSchoolYear]) }}"
                                   class="sidebar-link {{ $navHeadOnBlock ? 'active-sidebar' : '' }}"
                                   title="{{ $navHeadBlockLabel ?? 'Current block' }}">
                                    <i class="fas fa-users"></i>
                                    <span class="text-truncate">Current Block</span>
                                </a>
                            @endif

                            <a href="{{ route('academic-head.riskScoring', ['period' => $navHeadPeriod, 'school_year' => $navHeadSchoolYear]) }}"
                               class="sidebar-link {{ $navHeadOnRiskScoring ? 'active-sidebar' : '' }}">
                                <i class="fas fa-robot"></i> Risk Scoring
                            </a>

                            <a href="{{ route('academic-head.recommendations', array_filter(['period' => $navHeadPeriod, 'block_id' => $navHeadExplicitBlock ?: null])) }}"
                               class="sidebar-link {{ request()->routeIs('academic-head.recommendations*') || request()->routeIs('academic-head.recommendation*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-lightbulb"></i> Recommendations
                            </a>

                            <div class="sidebar-section">Reports</div>

                            <a href="{{ route('academic-head.reports') }}"
                               class="sidebar-link {{ request()->routeIs('academic-head.reports') || request()->routeIs('academic-head.reports.preview') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-file-alt"></i> End-of-Term Reports
                            </a>

                            @if($navHeadLatestReportId)
                                <a href="{{ route('academic-head.reports.preview', $navHeadLatestReportId) }}"
                                   class="sidebar-link {{ request()->routeIs('academic-head.reports.preview') ? 'active-sidebar' : '' }}">
                                    <i class="fas fa-file-circle-check"></i> Most Recent Report
                                </a>
                            @endif

                            <div class="sidebar-section">Account</div>

                            <a href="{{ route('profile') }}"
                               class="sidebar-link {{ request()->routeIs('profile') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-id-card"></i> My Profile
                            </a>

                            <a href="{{ route('settings') }}"
                               class="sidebar-link {{ request()->routeIs('settings') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-cog"></i> Account Settings
                            </a>

                        @endif
                        
                        @if(auth()->user()->isCounselor())
                            @php
                                $navOnCases = request()->routeIs('counselor.cases');
                                $navScope = (string) request('scope', 'all');
                            @endphp

                            <div class="sidebar-section">Main</div>

                            <a href="{{ route('counselor.dashboard') }}"
                               class="sidebar-link {{ request()->routeIs('counselor.dashboard') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-chart-pie"></i> Dashboard
                            </a>

                            <a href="{{ route('counselor.cases') }}"
                               class="sidebar-link {{ ($navOnCases && !in_array($navScope, ['open', 'resolved'], true)) || request()->routeIs('counselor.case') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-briefcase"></i> Caseload
                            </a>

                            <a href="{{ route('counselor.cases', ['scope' => 'open']) }}"
                               class="sidebar-link {{ $navOnCases && $navScope === 'open' ? 'active-sidebar' : '' }}">
                                <i class="fas fa-folder-open"></i> Open Cases
                            </a>

                            <a href="{{ route('counselor.cases', ['scope' => 'resolved']) }}"
                               class="sidebar-link {{ $navOnCases && $navScope === 'resolved' ? 'active-sidebar' : '' }}">
                                <i class="fas fa-archive"></i> Resolved Cases
                            </a>

                            <div class="sidebar-section">Reports</div>

                            <a href="{{ route('counselor.reports') }}"
                               class="sidebar-link {{ request()->routeIs('counselor.reports') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-file-alt"></i> Department Reports
                            </a>

                            <a href="{{ route('counselor.reports.mine') }}"
                               class="sidebar-link {{ request()->routeIs('counselor.reports.mine*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-file-lines"></i> My Reports
                            </a>

                            <div class="sidebar-section">Account</div>

                            <a href="{{ route('profile') }}"
                               class="sidebar-link {{ request()->routeIs('profile') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-id-card"></i> My Profile
                            </a>

                            <a href="{{ route('settings') }}"
                               class="sidebar-link {{ request()->routeIs('settings') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-cog"></i> Account Settings
                            </a>
                        @endif
                        
                        @if(auth()->user()->isStudent())
                            <a href="{{ route('student.dashboard') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('student.dashboard') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-user-graduate me-2"></i> My Dashboard
                            </a>
                            <a href="{{ route('student.grades') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('student.grades') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-book me-2"></i> My Grades
                            </a>
                            <a href="{{ route('student.attendance') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('student.attendance') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-clipboard-check me-2"></i> Attendance
                            </a>
                            <a href="{{ route('student.recommendations') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('student.recommendations') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-lightbulb me-2"></i> Recommendations
                                @php
                                    $sidebarStudentId = auth()->user()->student->id ?? 0;
                                    $sidebarCompletedIds = $sidebarStudentId
                                        ? \App\Models\StudentRecommendationTracking::where('student_id', $sidebarStudentId)
                                            ->where('is_completed', true)
                                            ->pluck('recommendation_id')
                                            ->all()
                                        : [];
                                    $pendingCount = $sidebarStudentId
                                        ? \Illuminate\Support\Facades\DB::table('intervention_recommendations')
                                            ->where('student_id', $sidebarStudentId)
                                            ->whereNotIn('id', $sidebarCompletedIds)
                                            ->count()
                                        : 0;
                                @endphp
                                @if($pendingCount > 0)
                                    <span class="badge bg-warning ms-1">{{ $pendingCount }}</span>
                                @endif
                            </a>
                            <a href="{{ route('student.counselor') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('student.counselor') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-headset me-2"></i> Contact Counselor
                            </a>
                            <a href="{{ route('profile') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('profile') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-id-card me-2"></i> My Profile
                            </a>
                            <a href="{{ route('settings') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('settings') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-cog me-2"></i> Account Settings
                            </a>
                        @endif
                    @endauth
                </div>
                
                <div class="sidebar-footer text-center text-muted small py-3 border-top border-secondary">
                    <i class="fas fa-shield-alt me-1"></i> v1.0.0
                </div>
            </div>
            
            <div id="sidebar-backdrop" class="sidebar-backdrop" onclick="toggleSidebar()" aria-hidden="true"></div>

            <div id="page-content-wrapper" class="flex-grow-1" style="padding-top: 70px; min-height: 100vh;">
                <div class="container-fluid px-4">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    
                    @if(session('warning'))
                        <div class="alert alert-warning alert-dismissible fade show mt-3" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i> {{ session('warning') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    
                    <div id="chartErrorContainer" style="display: none;"></div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-3 mb-4">
                        <h1 class="h3 mb-0 text-gray-800">@yield('page_title', 'Dashboard')</h1>
                        @yield('page_actions')
                    </div>
                    
                    @yield('content')
                </div>
            </div>
        </div>
        
        <footer class="py-3 mt-auto">
            <div class="container-fluid px-4">
                <div class="row align-items-center">
                    <div class="col-md-6 text-center text-md-start">
                        <span class="text-muted small">
                            &copy; {{ date('Y') }} <strong>AcadAlert</strong> - Universidad de Dagupan
                        </span>
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <span class="text-muted small">
                            <i class=""></i> AI-Powered Academic Risk Detection
                        </span>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <script src="{{ asset('js/vendor/bootstrap.bundle.min.js') }}?v={{ $assetVersion('js/vendor/bootstrap.bundle.min.js') }}"></script>
    <script>
        if (!window.bootstrap) {
            document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"><\/script>');
        }
    </script>

    <!-- Chart.js — LOCAL FIRST, same reasoning (a blocked chart must not break the page) -->
    <script src="{{ asset('js/vendor/chart.umd.min.js') }}?v={{ $assetVersion('js/vendor/chart.umd.min.js') }}"></script>
    <script>
        if (!window.Chart) {
            document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"><\/script>');
        }
    </script>
    
    <script src="{{ asset('js/app.js') }}?v={{ $assetVersion('js/app.js') }}"></script>
    
    <script src="{{ asset('js/charts/chart-init.js') }}?v={{ $assetVersion('js/charts/chart-init.js') }}"></script>
    
    <script>
        (function () {
            var missing = [];

            if (!window.bootstrap) {
                missing.push('Bootstrap (modals: Escalate / Run AI Risk Scoring / Reset)');
            }

            if (!window.Chart) {
                missing.push('Chart.js (charts cannot render)');
            }

            if (missing.length) {
                console.error('[AcadAlert] Page dependencies failed to load: ' + missing.join('; ')
                    + '. The page is still usable, but the listed features will not respond.');
            }
        })();
    </script>


    @auth
        @if(auth()->user()->isStudent())
            @include('partials.student-notification-scripts')
        @endif
    @endauth

    @stack('scripts')
</body>
</html>