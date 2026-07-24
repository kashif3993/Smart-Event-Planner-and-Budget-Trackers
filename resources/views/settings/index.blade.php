<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Settings — Smart Event Planner</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/events.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ time() }}">
</head>
<body>

    @include('partials.header')
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    @include('partials.sidebar')

    <main class="main-content">
        <div class="dashboard-body">

            <div class="page-header">
                <div>
                    <h1 class="page-title">Settings</h1>
                    <p class="page-subtitle">Manage your profile, password, and account.</p>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            {{-- Profile Information --}}
            <div class="settings-card">
                <h2 class="settings-card-title">Profile Information</h2>
                <p class="settings-card-subtitle">Update your name, email, and profile photo.</p>

                <form action="{{ route('settings.updateProfile') }}" method="POST" enctype="multipart/form-data" class="settings-profile-form">
                    @csrf
                    @method('PUT')

                    <div class="settings-avatar-block">
                        @if($user->profile_image)
                            <img src="{{ asset('storage/'.$user->profile_image) }}" alt="" class="settings-avatar-preview" id="avatarPreview">
                        @else
                            <div class="settings-avatar-preview settings-avatar-preview--placeholder" id="avatarPreview">
                                {{ substr($user->full_name ?? 'U', 0, 1) }}
                            </div>
                        @endif
                        <div>
                            <label for="profile_image" class="btn btn-outline settings-upload-btn">
                                <i class="fas fa-camera"></i> Change Photo
                            </label>
                            <input type="file" id="profile_image" name="profile_image" accept="image/png, image/jpeg" class="settings-file-input">
                            <p class="settings-hint">JPG or PNG, up to 2MB.</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" maxlength="50" value="{{ old('full_name', $user->full_name) }}" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" maxlength="100" value="{{ old('email', $user->email) }}" required>
                        </div>
                    </div>

                    <div class="settings-form-footer">
                        <button type="submit" class="btn btn-primary">Save Profile</button>
                    </div>
                </form>
            </div>

            {{-- Change Password --}}
            <div class="settings-card">
                <h2 class="settings-card-title">Change Password</h2>
                <p class="settings-card-subtitle">Choose a strong password you don't use elsewhere.</p>

                <form action="{{ route('settings.updatePassword') }}" method="POST" class="settings-password-form">
                    @csrf
                    @method('PUT')

                    <div class="form-grid">
                        <div class="form-group form-group--full">
                            <label for="current_password">Current Password</label>
                            <div class="settings-pw-field">
                                <input type="password" id="current_password" name="current_password" required>
                                <button type="button" class="pw-toggle" onclick="settingsTogglePw('current_password', this)" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <div class="settings-pw-field">
                                <input type="password" id="password" name="password" minlength="8" required>
                                <button type="button" class="pw-toggle" onclick="settingsTogglePw('password', this)" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password_confirmation">Confirm New Password</label>
                            <div class="settings-pw-field">
                                <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" required>
                                <button type="button" class="pw-toggle" onclick="settingsTogglePw('password_confirmation', this)" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="settings-form-footer">
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>

            {{-- Database Backup --}}
            <div class="settings-card">
                <h2 class="settings-card-title">Database Backup</h2>
                <p class="settings-card-subtitle">Create a full database backup (.sql), stored in the app's private backup folder.</p>

                <form action="{{ route('settings.backup.create') }}" method="POST">
                    @csrf
                    <div class="settings-form-footer" style="justify-content: flex-start; margin-top: 0; margin-bottom: 20px;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-database"></i> Create Backup</button>
                    </div>
                </form>

                @if(count($backups))
                    <div class="table-responsive">
                        <table class="expenses-table report-detail-table">
                            <thead>
                                <tr>
                                    <th>Filename</th>
                                    <th>Size</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($backups as $backup)
                                    <tr>
                                        <td>{{ $backup['filename'] }}</td>
                                        <td>{{ number_format($backup['size'] / 1024, 1) }} KB</td>
                                        <td>{{ $backup['modified']->format('M d, Y g:i A') }}</td>
                                        <td>
                                            <a href="{{ route('settings.backup.download', $backup['filename']) }}" class="btn btn-outline btn-sm">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="settingsDeleteBackup('{{ Str::slug($backup['filename']) }}')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                            <form id="backup-delete-form-{{ Str::slug($backup['filename']) }}"
                                                  action="{{ route('settings.backup.destroy', $backup['filename']) }}" method="POST" style="display:none;">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="report-empty-note">No backups yet. Click "Create Backup" to make your first one.</p>
                @endif
            </div>

            {{-- Danger Zone --}}
            <div class="settings-card settings-card--danger">
                <h2 class="settings-card-title">Danger Zone</h2>
                <p class="settings-card-subtitle">Permanently delete your account and all of its events, expenses, and activity. This cannot be undone.</p>

                <div class="settings-form-footer">
                    <button type="button" class="btn btn-danger" data-open-modal="deleteAccountModal">Delete My Account</button>
                </div>
            </div>

        </div>
    </main>

    {{-- Delete Account modal --}}
    <div class="modal-overlay" id="deleteAccountModal">
        <div class="modal-dialog modal-dialog--sm">
            <div class="modal-header">
                <h3 class="modal-title">Delete Account</h3>
                <button type="button" class="modal-close" data-close-modal="deleteAccountModal">&times;</button>
            </div>

            <form action="{{ route('settings.destroyAccount') }}" method="POST" class="modal-body" id="deleteAccountForm">
                @csrf
                @method('DELETE')

                <p class="settings-danger-warning">
                    <i class="fas fa-triangle-exclamation"></i>
                    This will permanently delete your account, all events, vendor categories, expenses, and activity history. This action cannot be undone.
                </p>

                <div class="form-group form-group--full">
                    <label for="delete_current_password">Enter your current password to confirm</label>
                    <input type="password" id="delete_current_password" name="current_password" required autocomplete="current-password">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-close-modal="deleteAccountModal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>Delete My Account</button>
                </div>
            </form>
        </div>
    </div>

    <script src="{{ asset('js/events.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/settings.js') }}?v={{ time() }}" defer></script>
</body>
</html>
