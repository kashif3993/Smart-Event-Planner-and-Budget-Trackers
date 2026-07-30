<aside class="sidebar">
        <div class="logo">
            <div class="logo-icon">E</div>
            <div>
                <div class="logo-text">EventPro</div>
                <div class="logo-subtext">Premium Planner</div>
            </div>
        </div>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('events.index') }}" class="nav-link {{ request()->routeIs('events.*') ? 'active' : '' }}">
                    <span class="nav-icon">📅</span> Events
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('timeline.index') }}" class="nav-link {{ request()->routeIs('timeline.*') ? 'active' : '' }}">
                    <span class="nav-icon">⏱️</span> Timeline
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('budget.index') }}" class="nav-link {{ request()->routeIs('budget.*') ? 'active' : '' }}">
                    <span class="nav-icon">💰</span> Budget
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('event-groups.index') }}" class="nav-link {{ request()->routeIs('event-groups.*') ? 'active' : '' }}">
                    <span class="nav-icon">🗂️</span> Groups
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('organization.index') }}" class="nav-link {{ request()->routeIs('organization.*') ? 'active' : '' }}">
                    <span class="nav-icon">🏢</span> Organization
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('expenses.index') }}" class="nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                    <span class="nav-icon">🧾</span> Expenses
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('vendor-categories.index') }}" class="nav-link {{ request()->routeIs('vendor-categories.*') ? 'active' : '' }}">
                    <span class="nav-icon">🤝</span> Vendors
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('progress.index') }}" class="nav-link {{ request()->routeIs('progress.*') ? 'active' : '' }}">
                    <span class="nav-icon">📈</span> Progress
                </a>
            </li>
        </ul>

        <div class="sidebar-bottom">
            <a href="{{ route('events.index') }}?new=1" class="btn-new-event" style="text-decoration:none;">+ New Event</a>
            <ul class="nav-menu" style="padding: 0;">
                <li class="nav-item">
                    <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                        <span class="nav-icon">⚙️</span> Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <span class="nav-icon">❓</span> Support
                    </a>
                </li>
                <li class="nav-item">
                    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                        @csrf
                        <button type="submit" class="nav-link" style="border:none; background:none; width:100%; text-align:left; cursor:pointer;">
                            <span class="nav-icon">🚪</span> Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </aside>