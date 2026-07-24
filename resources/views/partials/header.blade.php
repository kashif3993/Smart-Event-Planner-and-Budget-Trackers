        <header class="topbar">
            <div class="topbar-left">
                <button id="sidebarToggle" class="menu-bar-btn" aria-label="Toggle Menu">
                    ☰
                </button>
                <div class="search-bar">
                    <span class="search-icon">🔍</span>
                    <input type="text" placeholder="Search events...">
                </div>
            </div>
            <div class="topbar-right">
                @php
                    $notifications = Auth::check() ? \App\Models\Activity::forUser(Auth::id())->recent(5)->get() : collect();
                @endphp
                <div class="dropdown notification-dropdown">
                    <button type="button" class="icon-btn" id="notifBtn" aria-expanded="false" aria-haspopup="true">
                        <i class="fa fa-bell"></i>
                        @if($notifications->count() > 0)
                            <span class="notif-badge">{{ $notifications->count() }}</span>
                        @endif
                    </button>
                    <div class="dropdown-menu notif-menu" id="notifMenu">
                        <div class="dropdown-header">Notifications</div>
                        <ul class="notif-list">
                            @forelse($notifications as $notification)
                                <li class="notif-item">
                                    <span class="notif-dot" style="background-color: {{ $notification->color ?? '#3498db' }};"></span>
                                    <div>
                                        <div class="notif-text">{{ $notification->description }}</div>
                                        <div class="notif-time">{{ $notification->created_at->diffForHumans() }}</div>
                                    </div>
                                </li>
                            @empty
                                <li class="notif-item" style="padding: 10px; text-align: center; justify-content: center;">
                                    <div class="notif-text" style="color: #6c757d;">No new notifications</div>
                                </li>
                            @endforelse
                        </ul>
                        <div class="dropdown-divider" style="margin:0; border-top: 1px solid #e0e0e0;"></div>
                        <a href="{{ route('activity.index') }}" class="dropdown-item" style="text-align: center; font-size: 0.9rem;">View All Activity</a>
                    </div>
                </div>

                <div class="dropdown profile-dropdown">
                    <div class="user-profile" id="profileBtn" role="button" tabindex="0" aria-expanded="false" aria-haspopup="true">
                        @if(Auth::user()?->profile_image)
                            <img src="{{ asset('storage/'.Auth::user()->profile_image) }}" alt="" class="avatar avatar-img">
                        @else
                            <div class="avatar">
                                {{ substr(Auth::user()->full_name ?? 'U', 0, 1) }}
                            </div>
                        @endif
                        <span class="dropdown-caret">▼</span>
                    </div>
                    <div class="dropdown-menu profile-menu" id="profileMenu">
                        <div class="dropdown-header">
                            <strong>{{ Auth::user()->full_name ?? 'User' }}</strong>
                            <div class="dropdown-subtext">{{ Auth::user()->email ?? '' }}</div>
                        </div>
                        <a href="{{ route('settings.index') }}" class="dropdown-item">My Profile</a>
                        <a href="{{ route('settings.index') }}" class="dropdown-item">Settings</a>
                        <div class="dropdown-divider"></div>
                        <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
