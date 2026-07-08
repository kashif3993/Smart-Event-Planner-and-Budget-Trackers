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
                <div class="dropdown notification-dropdown">
                    <button type="button" class="icon-btn" id="notifBtn" aria-expanded="false" aria-haspopup="true">
                        <i class="fa fa-bell"></i>
                        <span class="notif-badge">3</span>
                    </button>
                    <div class="dropdown-menu notif-menu" id="notifMenu">
                        <div class="dropdown-header">Notifications</div>
                        <ul class="notif-list">
                            <li class="notif-item">
                                <span class="notif-dot"></span>
                                <div>
                                    <div class="notif-text">Tech Summit 2026 starts in 3 days</div>
                                    <div class="notif-time">2 hours ago</div>
                                </div>
                            </li>
                            <li class="notif-item">
                                <span class="notif-dot"></span>
                                <div>
                                    <div class="notif-text">Budget for Gala Dinner reached 80%</div>
                                    <div class="notif-time">Yesterday</div>
                                </div>
                            </li>
                            <li class="notif-item">
                                <span class="notif-dot"></span>
                                <div>
                                    <div class="notif-text">New vendor proposal received</div>
                                    <div class="notif-time">2 days ago</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="dropdown profile-dropdown">
                    <div class="user-profile" id="profileBtn" role="button" tabindex="0" aria-expanded="false" aria-haspopup="true">
                        <div class="avatar">
                            {{ substr(Auth::user()->full_name ?? 'U', 0, 1) }}
                        </div>
                        <span class="dropdown-caret">▼</span>
                    </div>
                    <div class="dropdown-menu profile-menu" id="profileMenu">
                        <div class="dropdown-header">
                            <strong>{{ Auth::user()->full_name ?? 'User' }}</strong>
                            <div class="dropdown-subtext">{{ Auth::user()->email ?? '' }}</div>
                        </div>
                        <a href="#" class="dropdown-item">My Profile</a>
                        <a href="#" class="dropdown-item">Settings</a>
                        <div class="dropdown-divider"></div>
                        <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
