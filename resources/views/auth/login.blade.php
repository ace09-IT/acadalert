<!DOCTYPE html>
<html lang="en" data-bs-theme="dark" class="theme-dark">
<head>
    <meta charset="UTF-8">
    <script>
        (function () {
            var theme = 'dark';
            try {
                var stored = localStorage.getItem('acadalerts-theme');
                if (stored === 'light' || stored === 'dark') { theme = stored; }
            } catch (e) { }
            var root = document.documentElement;
            root.classList.remove('theme-dark', 'theme-light');
            root.classList.add('theme-' + theme);
            root.setAttribute('data-bs-theme', theme);
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>Login - AcadAlert | UDD</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @property --login-sweep {
            syntax: '<angle>';
            initial-value: 0deg;
            inherits: false;
        }

        :root {
            color-scheme: dark;
            /* CHANGED: Define the approved OCEAN swatches for the standalone login view. */
            --lg-darkest: #0D1B2A;
            --lg-primary: #1B4965;
            --lg-accent: #29ADB2;
            --lg-soft: #A8DADC;
            --lg-lightest: #E6F4F1;
            --lg-card-bg: rgba(13, 27, 42, 0.9);
            --lg-card-edge: rgba(168, 218, 220, 0.2);
            --lg-ink: var(--lg-lightest);
            --lg-ink-soft: rgba(230, 244, 241, 0.76);
            --lg-field-bg: rgba(13, 27, 42, 0.68);
            --lg-field-edge: rgba(168, 218, 220, 0.28);
            --lg-focus-ring: rgba(41, 173, 178, 0.28);
            --lg-link: var(--lg-soft);
            --lg-link-hover: var(--lg-accent);
            --lg-glow: rgba(41, 173, 178, 0.45);
            --lg-crest-shadow:
                drop-shadow(0 0 3px rgba(230, 244, 241, 0.75))
                drop-shadow(0 0 18px rgba(168, 218, 220, 0.62))
                drop-shadow(0 8px 24px rgba(2, 6, 23, 0.55));
            --lg-lockup-shadow:
                drop-shadow(0 0 1px rgba(255, 255, 255, 0.88))
                drop-shadow(0 0 9px rgba(168, 218, 220, 0.55));
            --lg-veil: linear-gradient(180deg, rgba(13, 27, 42, 0.70) 0%, rgba(13, 27, 42, 0.82) 55%, rgba(4, 7, 16, 0.90) 100%);
        }

        :root.theme-light {
            color-scheme: light;
            /* CHANGED: Keep light-theme card, fields, and links readable while retaining the OCEAN accents. */
            --lg-card-bg: rgba(255, 255, 255, 0.93);
            --lg-card-edge: rgba(27, 73, 101, 0.14);
            --lg-ink: var(--lg-darkest);
            --lg-ink-soft: rgba(27, 73, 101, 0.82);
            --lg-field-bg: rgba(27, 73, 101, 0.045);
            --lg-field-edge: rgba(27, 73, 101, 0.22);
            --lg-focus-ring: rgba(41, 173, 178, 0.24);
            --lg-link: var(--lg-primary);
            --lg-link-hover: #146f7b;
            --lg-glow: rgba(27, 73, 101, 0.34);
            --lg-crest-shadow:
                drop-shadow(0 0 12px rgba(41, 173, 178, 0.22))
                drop-shadow(0 8px 18px rgba(13, 27, 42, 0.20));
            --lg-lockup-shadow:
                drop-shadow(0 3px 10px rgba(13, 27, 42, 0.18));
            --lg-veil: linear-gradient(180deg, rgba(13, 27, 42, 0.56) 0%, rgba(13, 27, 42, 0.68) 55%, rgba(9, 14, 28, 0.80) 100%);
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 28px 20px;
            position: relative;
            overflow-x: hidden;
            color: var(--lg-ink);
            background-image: url('{{ asset("images/backgrounds/maincampus03.webp") }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        .login-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .login-bg-slide {
            position: absolute;
            inset: -2%;
            background-size: cover;
            background-position: center;
            opacity: 0;
            will-change: opacity, transform;
            animation: lgSlide 32s linear infinite, lgDrift 46s ease-in-out infinite alternate;
        }

        .login-bg-slide:nth-child(1) { background-image: url('{{ asset("images/backgrounds/maincampus03.webp") }}'); animation-delay: 0s, 0s; }
        .login-bg-slide:nth-child(2) { background-image: url('{{ asset("images/backgrounds/maincampus02.webp") }}'); animation-delay: 8s, -6s; }
        .login-bg-slide:nth-child(3) { background-image: url('{{ asset("images/backgrounds/bg_smll_udd.jpg") }}'); animation-delay: 16s, -12s; }
        .login-bg-slide:nth-child(4) { background-image: url('{{ asset("images/backgrounds/loginbackground.jpg") }}'); animation-delay: 24s, -18s; }

        @keyframes lgSlide {
            0%    { opacity: 0; }
            3.1%  { opacity: 1; }
            25%   { opacity: 1; }
            28.1% { opacity: 0; }
            100%  { opacity: 0; }
        }

        @keyframes lgDrift {
            from { transform: scale(1.03); }
            to   { transform: scale(1.11) translate3d(-1.2%, -1%, 0); }
        }

        .login-veil {
            position: fixed;
            inset: 0;
            z-index: 1;
            pointer-events: none;
            background-image:
                /* CHANGED: OCEAN-tinted veil preserves legibility over every slideshow image. */
                radial-gradient(120% 95% at 100% 0%, rgba(41, 173, 178, 0.20), rgba(41, 173, 178, 0) 62%),
                radial-gradient(110% 90% at 0% 100%, rgba(168, 218, 220, 0.14), rgba(168, 218, 220, 0) 60%),
                var(--lg-veil);
        }

        .login-container {
            width: 100%;
            max-width: 452px;
            position: relative;
            z-index: 2;
        }

        .login-border-wrapper {
            position: relative;
            border-radius: 26px;
            padding: 3px;
            /* CHANGED: Animated border and halo use OCEAN colors in place of blue/cyan. */
            background-color: rgba(41, 173, 178, 0.55);
            background-image: conic-gradient(
                from var(--login-sweep),
                var(--lg-soft),
                var(--lg-accent),
                var(--lg-primary),
                var(--lg-lightest),
                var(--lg-darkest),
                var(--lg-soft)
            );
            animation: lgSweep 3.6s linear infinite;
            box-shadow:
                0 0 0 1px rgba(168, 218, 220, 0.30),
                0 26px 64px -30px rgba(2, 6, 23, 0.92),
                0 0 34px var(--lg-glow);

            transition: padding 0.35s ease, box-shadow 0.35s ease, filter 0.35s ease;
        }

        .login-border-wrapper::before {
            content: '';
            position: absolute;
            inset: -12px;
            z-index: -1;
            border-radius: 38px;
            background-image: conic-gradient(
                from var(--login-sweep),
                var(--lg-soft),
                var(--lg-accent),
                var(--lg-primary),
                var(--lg-lightest),
                var(--lg-darkest),
                var(--lg-soft)
            );
            filter: blur(20px) saturate(1.5);
            opacity: 0.55;
            animation: lgHalo 5.6s ease-in-out infinite;
            transition: inset 0.35s ease, opacity 0.35s ease;
            pointer-events: none;
        }

        @keyframes lgSweep {
            to { --login-sweep: 360deg; }
        }

        @keyframes lgHalo {
            0%, 100% { opacity: 0.45; }
            50%      { opacity: 0.72; }
        }

        .login-border-wrapper:hover,
        .login-border-wrapper:focus-within {
            padding: 5px;
            filter: brightness(1.18) saturate(1.28);
            box-shadow:
                0 0 0 1px rgba(168, 218, 220, 0.60),
                0 26px 64px -28px rgba(2, 6, 23, 0.92),
                0 0 62px var(--lg-glow),
                0 0 120px rgba(41, 173, 178, 0.26);
        }

        .login-border-wrapper:hover::before,
        .login-border-wrapper:focus-within::before {
            inset: -18px;
            opacity: 0.8;
        }

        .login-card {
            position: relative;
            padding: 32px 32px 26px;
            border-radius: 24px;
            background: var(--lg-card-bg);
            -webkit-backdrop-filter: blur(18px) saturate(1.15);
            backdrop-filter: blur(18px) saturate(1.15);
            box-shadow: inset 0 0 0 1px var(--lg-card-edge);
            color: var(--lg-ink);
        }

        /* CHANGED: Any login-card links inherit theme-aware OCEAN link colors. */
        .login-card a {
            color: var(--lg-link);
        }

        .login-card a:hover,
        .login-card a:focus-visible {
            color: var(--lg-link-hover);
        }

        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            margin-bottom: 28px;
        }

        .login-crest-plate {
            flex: 0 0 auto;
            display: grid;
            place-items: center;
            width: 92px;
            height: 92px;
        }

        .login-crest-plate img {
            display: block;
            width: 92px;
            height: 92px;
            object-fit: contain;
            filter: var(--lg-crest-shadow);
        }

        /* Hairline between the two marks - a soft rule that fades at both ends, so
           it separates them without drawing a hard seam across the card. */
        .login-logo-rule {
            flex: 0 0 auto;
            align-self: stretch;
            width: 1px;
            margin: 6px 0;
            background: linear-gradient(180deg,
                transparent,
                var(--lg-card-edge) 22%,
                var(--lg-card-edge) 78%,
                transparent);
        }

        .login-brand-chip {
            flex: 0 0 auto;
            width: 100px;
            aspect-ratio: 1449 / 611;
            overflow: hidden;
            filter: var(--lg-lockup-shadow);
        }

        .login-brand-chip img {
            display: block;
            width: 105.9351%;
            max-width: none;
            height: 167.587%;
            margin-top: -12.1456%;
            margin-left: -3.3876%;
        }

        .login-card .form-label {
            margin-bottom: 0.4rem;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--lg-ink-soft);
        }

        .login-card .input-group-text {
            background-color: var(--lg-field-bg) !important;
            border-color: var(--lg-field-edge) !important;
            border-width: 1px !important;
            border-right-width: 0 !important;
            border-radius: 12px 0 0 12px !important;
            color: var(--lg-ink-soft);
        }

        .login-card .input-group .form-control {
            border-left: 0;
            border-radius: 0 12px 12px 0;
        }

        .login-card .form-control {
            background-color: var(--lg-field-bg);
            border-color: var(--lg-field-edge);
            padding: 12px 14px;
            font-size: 0.95rem;
            color: var(--lg-ink);
            transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
        }

        .login-card .form-control::placeholder {
            color: var(--lg-ink-soft);
            opacity: 0.6;
        }

        .login-card .form-control:focus {
            background-color: var(--lg-field-bg);
            border-color: var(--lg-accent);
            box-shadow: 0 0 0 4px var(--lg-focus-ring);
            color: var(--lg-ink);
        }

        .login-card .form-control.is-invalid {
            border-color: #f87171;
        }

        .login-card .form-control.is-invalid:focus {
            box-shadow: 0 0 0 4px rgba(248, 113, 113, 0.20);
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            border: 0;
            border-radius: 999px;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            color: #ffffff;
            /* CHANGED: Keep white button text high-contrast on the darker OCEAN gradient. */
            background-image: linear-gradient(135deg, var(--lg-primary) 0%, var(--lg-darkest) 100%);
            box-shadow:
                0 14px 30px -16px rgba(41, 173, 178, 0.62),
                inset 0 1px 0 rgba(255, 255, 255, 0.22);
            transition: transform 0.22s ease, box-shadow 0.22s ease, filter 0.22s ease;
        }

        .btn-login:hover,
        .btn-login:focus-visible {
            transform: translateY(-2px);
            filter: brightness(1.08);
            box-shadow:
                0 20px 40px -18px rgba(41, 173, 178, 0.78),
                0 0 34px rgba(41, 173, 178, 0.45),
                inset 0 1px 0 rgba(255, 255, 255, 0.28);
        }

        .btn-login:active {
            transform: translateY(0);
        }
        
        .login-card .form-check-input {
            background-color: var(--lg-field-bg);
            border-color: var(--lg-field-edge);
        }

        .login-card .form-check-input:checked {
            background-color: var(--lg-primary);
            border-color: var(--lg-primary);
        }

        .login-card .form-check-label {
            font-size: 0.86rem;
            color: var(--lg-ink-soft);
        }
        
        .login-card .alert {
            border: 0;
            border-radius: 12px;
            font-size: 0.88rem;
        }

        .login-card .alert-danger {
            background: rgba(220, 53, 69, 0.16);
            color: #ffd7db;
            border-left: 4px solid #f87171;
        }

        .login-card .alert-success {
            background: rgba(25, 135, 84, 0.18);
            color: #c8f5db;
            border-left: 4px solid #34d399;
        }

        .login-card .btn-close {
            filter: invert(1) grayscale(1) brightness(2);
            opacity: 0.7;
        }

        :root.theme-light .login-card .alert-danger {
            background: rgba(220, 53, 69, 0.10);
            color: #842029;
        }

        :root.theme-light .login-card .alert-success {
            background: rgba(25, 135, 84, 0.10);
            color: #0f5132;
        }

        :root.theme-light .login-card .btn-close {
            filter: none;
        }
        
        .footer-text {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 22px;
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.62);
            text-align: center;
        }

        .footer-text .footer-mark {
            display: block;
            width: 18px;
            height: auto;
            opacity: 0.9;
            filter: drop-shadow(0 0 6px rgba(41, 173, 178, 0.55));
        }


        @media (max-width: 576px) {
            .login-card {
                padding: 26px 20px 22px;
                border-radius: 20px;
            }

            .login-logo {
                gap: 14px;
                margin-bottom: 22px;
            }

            .login-crest-plate {
                width: 72px;
                height: 72px;
            }

            .login-crest-plate img {
                width: 72px;
                height: 72px;
            }

            .login-brand-chip {
                width: 78px;
            }

            .login-border-wrapper {
                border-radius: 22px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-bg-slide {
                animation: none;
                opacity: 0;
                transform: none;
            }

            .login-bg-slide:nth-child(1) {
                opacity: 1;
            }

            .login-border-wrapper,
            .login-border-wrapper::before {
                animation: none;
            }

            .login-border-wrapper::before {
                opacity: 0.55;
            }

            .login-border-wrapper:hover,
            .login-border-wrapper:focus-within,
            .btn-login {
                transition: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-bg" aria-hidden="true">
        <span class="login-bg-slide"></span>
        <span class="login-bg-slide"></span>
        <span class="login-bg-slide"></span>
        <span class="login-bg-slide"></span>
    </div>
    <div class="login-veil" aria-hidden="true"></div>

    <div class="login-container">
        <div class="login-border-wrapper">
            <div class="login-card">
                <div class="login-logo">
                    <div class="login-crest-plate">
                        <img src="{{ asset('images/logo/udd-logo.png') }}" alt="Universidad de Dagupan Logo">
                    </div>
                    <span class="login-logo-rule" aria-hidden="true"></span>
                    <span class="login-brand-chip">
                        <img src="{{ asset('images/logo/acadalert_logo.png') }}" alt="AcadAlert">
                    </span>
                </div>
                
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2 border-end-0 rounded-start-3">
                                <i class="fas fa-envelope text-muted"></i>
                            </span>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                   id="email" name="email" value="{{ old('email') }}" 
                                   placeholder="Enter your email" required autofocus>
                        </div>
                        @error('email')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2 border-end-0 rounded-start-3">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" class="form-control" id="password" 
                                   name="password" placeholder="Enter your password" required>
                        </div>
                    </div>
                    
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember">
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i> Sign In
                    </button>
                </form>
                
        </div>
        
    </div>

    <div class="footer-text">
        <img src="{{ asset('images/logo/acadalert_notxt.png') }}" alt="" class="footer-mark">
        &copy; {{ date('Y') }} AcadAlert &middot; AI-Powered Academic Risk Detection System
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>