<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reset your Smart Event Planner password.">
    <title>Forgot Password — Smart Event Planner</title>
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
                <h1 class="panel-headline">Forgot Your<br>Password?</h1>
                <p class="panel-sub">
                    No problem. Enter the email address on your account to set a new password.
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
            <h2 class="form-title">Reset Password</h2>
            <div class="title-underline"></div>

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

            <form method="POST" action="{{ route('password.email') }}" novalidate>
                @csrf

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email"
                           id="email"
                           name="email"
                           placeholder="john@eventpro.com"
                           value="{{ old('email') }}"
                           autocomplete="email"
                           class="{{ $errors->has('email') ? 'is-invalid' : '' }}"
                           autofocus
                           required>
                </div>

                <button type="submit" class="btn-submit">
                    <span>Continue →</span>
                </button>
            </form>

            <p class="signup-link">
                Remembered your password? <a href="{{ route('login') }}">Back to Sign In</a>
            </p>
        </div>

    </div>
</div>
</body>
</html>
