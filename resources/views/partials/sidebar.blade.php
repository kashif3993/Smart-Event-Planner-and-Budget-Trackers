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
                <a href="#" class="nav-link active">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <span class="nav-icon">📅</span> Events
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <span class="nav-icon">⏱️</span> Timeline
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <span class="nav-icon">💰</span> Budget
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <span class="nav-icon">🧾</span> Expenses
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <span class="nav-icon">🤝</span> Vendors
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <span class="nav-icon">📈</span> Progress
                </a>
            </li>
        </ul>

        <div class="sidebar-bottom">
            <button class="btn-new-event">+ New Event</button>
            <ul class="nav-menu" style="padding: 0;">
                <li class="nav-item">
                    <a href="#" class="nav-link">
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