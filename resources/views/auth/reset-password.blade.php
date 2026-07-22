<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Choose a new Smart Event Planner password.">
    <title>Reset Password — Smart Event Planner</title>
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
                <h1 class="panel-headline">Choose a New<br>Password.</h1>
                <p class="panel-sub">
                    Pick something strong you haven't used before — at least 8 characters.
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

            <form method="POST" action="{{ route('password.update') }}" novalidate>
                @csrf

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email"
                           id="email"
                           name="email"
                           placeholder="john@eventpro.com"
                           value="{{ old('email', $email) }}"
                           autocomplete="email"
                           class="{{ $errors->has('email') ? 'is-invalid' : '' }}"
                           required>
                </div>

                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="input-wrap">
                        <input type="password"
                               id="password"
                               name="password"
                               placeholder="At least 8 characters"
                               autocomplete="new-password"
                               class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
                               required>
                        <button type="button" class="pw-toggle" id="pwToggle" onclick="togglePw('password','pwToggle')" aria-label="Toggle password visibility">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirm New Password</label>
                    <div class="input-wrap">
                        <input type="password"
                               id="password_confirmation"
                               name="password_confirmation"
                               placeholder="Re-enter your new password"
                               autocomplete="new-password"
                               required>
                        <button type="button" class="pw-toggle" id="pwToggle2" onclick="togglePw('password_confirmation','pwToggle2')" aria-label="Toggle password visibility">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <span>Reset Password →</span>
                </button>
            </form>

            <p class="signup-link">
                Remembered your password? <a href="{{ route('login') }}">Back to Sign In</a>
            </p>
        </div>

    </div>
</div>

<script src="{{ asset('js/login.js') }}" defer></script>
</body>
</html>
