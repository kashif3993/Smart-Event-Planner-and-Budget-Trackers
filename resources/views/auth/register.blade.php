<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create your Smart Event Planner account and start managing professional events with ease.">
    <title>Create Account — Smart Event Planner</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/register.css') }}">
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
                <h1 class="panel-headline">Plan Events<br>with Precision.</h1>
                <p class="panel-sub">
                    Join 10,000+ professional planners managing premium corporate events &amp; weddings worldwide.
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
            <p class="step-badge">Step 1 of 2</p>
            <h2 class="form-title">Create Account</h2>
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

            {{-- Success message --}}
            @if (session('success'))
                <div class="alert alert-success" role="alert">
                    <span>✅</span>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            <form id="registerForm"
                  method="POST"
                  action="{{ route('register.store') }}"
                  enctype="multipart/form-data"
                  novalidate>
                @csrf

                {{-- Full Name --}}
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text"
                           id="full_name"
                           name="full_name"
                           placeholder="Johnathan Doe"
                           value="{{ old('full_name') }}"
                           autocomplete="name"
                           class="{{ $errors->has('full_name') ? 'is-invalid' : '' }}">
                    @error('full_name')
                        <p class="field-error">⚠ {{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email"
                           id="email"
                           name="email"
                           placeholder="john@eventpro.com"
                           value="{{ old('email') }}"
                           autocomplete="email"
                           class="{{ $errors->has('email') ? 'is-invalid' : '' }}">
                    @error('email')
                        <p class="field-error">⚠ {{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <input type="password"
                               id="password"
                               name="password"
                               placeholder="Min. 8 characters"
                               autocomplete="new-password"
                               class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
                               oninput="checkStrength(this.value)">
                        <button type="button" class="pw-toggle" id="pwToggle1" onclick="togglePw('password','pwToggle1')" aria-label="Toggle password visibility">
                            <svg id="pwIcon1" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <div class="pw-strength" id="pwStrengthBars">
                        <div class="pw-strength-bar" id="bar1"></div>
                        <div class="pw-strength-bar" id="bar2"></div>
                        <div class="pw-strength-bar" id="bar3"></div>
                        <div class="pw-strength-bar" id="bar4"></div>
                    </div>
                    <p class="pw-label" id="pwLabel"></p>
                    @error('password')
                        <p class="field-error">⚠ {{ $message }}</p>
                    @enderror
                </div>

                {{-- Confirm Password --}}
                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <div class="input-wrap">
                        <input type="password"
                               id="password_confirmation"
                               name="password_confirmation"
                               placeholder="Re-enter your password"
                               autocomplete="new-password">
                        <button type="button" class="pw-toggle" id="pwToggle2" onclick="togglePw('password_confirmation','pwToggle2')" aria-label="Toggle confirm password visibility">
                            <svg id="pwIcon2" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Profile Image --}}
                <div class="form-group">
                    <label for="profile_image">Profile Image <span class="optional">(optional)</span></label>
                    <div class="upload-zone" id="uploadZone">
                        <input type="file"
                               id="profile_image"
                               name="profile_image"
                               accept="image/jpg,image/jpeg,image/png"
                               onchange="previewImage(this)">
                        <div class="upload-icon">📷</div>
                        <p class="upload-text">Drag and drop or <a href="#">Browse files</a></p>
                        <p class="upload-hint">JPG, PNG up to 2MB</p>
                    </div>
                    <div class="upload-preview" id="uploadPreview">
                        <img id="previewImg" src="" alt="Preview">
                        <span class="upload-preview-name" id="previewName"></span>
                        <button type="button" class="upload-preview-remove" onclick="removeImage()" aria-label="Remove image">×</button>
                    </div>
                    @error('profile_image')
                        <p class="field-error">⚠ {{ $message }}</p>
                    @enderror
                </div>

                {{-- Terms --}}
                <div class="terms-row">
                    <input type="checkbox" id="terms" name="terms" value="1"
                           {{ old('terms') ? 'checked' : '' }}>
                    <label for="terms">
                        I agree to the <a href="#" id="termsLink">Terms &amp; Conditions</a> and <a href="#" id="privacyLink">Privacy Policy</a>
                    </label>
                </div>
                @error('terms')
                    <p class="field-error" style="margin-top:-18px;margin-bottom:16px">⚠ {{ $message }}</p>
                @enderror

                <button type="submit" class="btn-register" id="submitBtn">
                    <span id="btnText">Continue →</span>
                    <span class="btn-spinner" id="btnSpinner"></span>
                </button>
            </form>

            <p class="signin-link">
                Already have an account? <a href="{{ route('login') }}">Sign In</a>
            </p>
        </div>

    </div>
</div>

<script src="{{ asset('js/register.js') }}" defer></script>
</body>
</html>
