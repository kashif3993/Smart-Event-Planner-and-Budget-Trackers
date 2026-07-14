<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EventPro - Premium Planner')</title>

    <link rel="stylesheet" href="{{ asset('assets/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ time() }}">

    @yield('page_css')
</head>
<body>
    <div class="app-container">

        {{-- Header / Topbar --}}
        <header class="topbar">
            <button id="mobile-menu-btn" class="mobile-menu-btn" aria-label="Toggle Menu">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 12h18M3 6h18M3 18h18"/>
                </svg>
            </button>

            <div class="search-bar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
                <input type="text" placeholder="Search events, tasks, or budgets...">
            </div>

            <div class="topbar-actions">
                {{-- Notifications Dropdown --}}
                <div class="dropdown notification-dropdown">
                    <button class="icon-btn notifications active-notify" id="notifBtn"
                            aria-expanded="false" aria-haspopup="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                    </button>
                    <div class="dropdown-menu notif-menu" aria-labelledby="notifBtn">
                        <div class="dropdown-header">Notifications</div>
                        <ul class="notif-list">
                            <li>No new notifications</li>
                        </ul>
                    </div>
                </div>

                {{-- User Profile Dropdown --}}
                <div class="dropdown profile-dropdown">
                    <div class="user-profile" id="profileBtn"
                         aria-expanded="false" aria-haspopup="true" style="cursor: pointer;">
                        <img src="{{ auth()->user()->profile_image
                                    ? asset(auth()->user()->profile_image)
                                    : 'https://i.pravatar.cc/150?img=11' }}"
                             alt="User Profile">
                        <span class="user-name-text">{{ auth()->user()->name ?? 'User' }}</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </div>
                    <div class="dropdown-menu profile-menu" aria-labelledby="profileBtn">
                        <div class="dropdown-header">
                            <strong>{{ auth()->user()->name ?? 'User' }}</strong>
                        </div>
                        <a href="#" class="dropdown-item">My Profile</a>
                        <a href="#" class="dropdown-item">Settings</a>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger"
                                    style="background:none;border:none;width:100%;text-align:left;cursor:pointer;">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- Sidebar --}}
        @include('partials.sidebar')

        {{-- Page Content --}}
        @yield('content')

    </div>{{-- End .app-container --}}

    <script src="{{ asset('assets/js/script.js') }}?v={{ time() }}"></script>
    @yield('page_js')

</body>
</html>
