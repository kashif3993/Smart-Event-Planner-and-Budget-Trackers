<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to your Smart Event Planner account.">
    <title>Sign In — Smart Event Planner</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">

        {{-- ── LEFT PANEL ── --}}
        <div class="auth-panel">
            <div>
                <div class="panel-logo">
                    <div class="panel-logo-icon">🗓️</div>
                    <span>Smart Event Planner</span>
                </div>
                <h1 class="panel-headline">Welcome<br>Back.</h1>
                <p class="panel-sub">
                    Sign in to continue managing your premium corporate events &amp; weddings seamlessly.
                </p>
                <div class="panel-divider"></div>
                <ul class="features-list">
                    <li>
                        <div class="feature-icon" style="background:rgba(245,158,11,.2)">📊</div>
                        Real-time Budget Tracking
                    </li>
                    <li>
                        <div class="feature-icon" style="background:rgba(239,68,68,.2)">🏆</div>
                        Vendor Management Portal
                    </li>
                    <li>
                        <div class="feature-icon" style="background:rgba(16,185,129,.2)">✉️</div>
                        Automated Guest Invitations
                    </li>
                    <li>
                        <div class="feature-icon" style="background:rgba(239,68,68,.2)">📍</div>
                        Smart Location Mapping
                    </li>
                </ul>
            </div>
        </div>

        {{-- ── FORM PANEL ── --}}
        <div class="auth-form-wrap">
            <h2 class="form-title">Sign In</h2>
            <div class="title-underline"></div>

            {{-- Validation errors --}}
            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <span>⚠️</span>
                    <div>
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <form id="loginForm" method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf

                {{-- Email --}}
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email"
                           id="email"
                           name="email"
                           placeholder="john@eventpro.com"
                           value="{{ old('email') }}"
                           autocomplete="email"
                           class="{{ $errors->has('email') ? 'is-invalid' : '' }}"
                           required>
                </div>

                {{-- Password --}}
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <input type="password"
                               id="password"
                               name="password"
                               placeholder="Enter your password"
                               autocomplete="current-password"
                               class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
                               required>
                        <button type="button" class="pw-toggle" id="pwToggle" onclick="togglePw('password','pwToggle')" aria-label="Toggle password visibility">
                            <svg id="pwIcon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Options --}}
                <div class="options-row">
                    <div class="remember-wrap">
                        <input type="checkbox" id="remember" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <span id="btnText">Sign In →</span>
                    <span class="btn-spinner" id="btnSpinner"></span>
                </button>
            </form>

            <p class="signup-link">
                Don't have an account? <a href="{{ route('register') }}">Create Account</a>
            </p>
        </div>

    </div>
</div>

<script src="{{ asset('js/login.js') }}" defer></script>
</body>
</html>
