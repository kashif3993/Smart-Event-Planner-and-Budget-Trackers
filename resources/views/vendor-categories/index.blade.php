<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Vendor Categories — Smart Event Planner</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/events.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/categories.css') }}?v={{ time() }}">
    <script src="{{ asset('js/events.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/categories.js') }}?v={{ time() }}" defer></script>
</head>
<body>

    @include('partials.header')

    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    @include('partials.sidebar')

    <main class="main-content">
        <div class="dashboard-body">

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error">{{ $errors->first() }}</div>
            @endif

            <div class="page-header">
                <div>
                    <h1 class="page-title">Vendor Categories</h1>
                    <p class="page-subtitle">Manage your vendor allocation and monitor budget consumption across departments.</p>
                </div>
                <div class="page-header-actions" style="margin-bottom: 15px;">
                    @if ($userEvents->isNotEmpty())
                        <form method="GET" action="{{ route('vendor-categories.index') }}" id="eventSwitchForm">
                            <select name="event" class="event-switch-select" onchange="this.form.submit()">
                                @foreach ($userEvents as $evt)
                                    <option value="{{ $evt->id }}" @selected($selectedEvent && $selectedEvent->id === $evt->id)>
                                        {{ $evt->event_name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                        <button type="button" class="btn btn-outline" title="Coming soon" disabled>
                            <i class="fas fa-filter"></i> <span class="btn-text">Filter</span>
                        </button>
                        <button type="button" class="btn btn-outline" onclick="window.print()">
                            <i class="fas fa-download"></i> <span class="btn-text">Export Report</span>
                        </button>
                        <button type="button" class="btn btn-primary" data-open-modal="categoryModalAdd">
                            <i class="fas fa-plus"></i> New Category
                        </button>
                    @endif
                </div>
            </div>

            @if ($userEvents->isEmpty())
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-champagne-glasses"></i></div>
                    <h3>No Events Yet</h3>
                    <p>Create an event first, then come back here to plan its vendor budget.</p>
                    <a href="{{ route('events.index') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create an Event
                    </a>
                </div>
            @else
                {{-- Summary Stats --}}
                <div class="stats-grid category-stats-grid">
                    <div class="stat-card">
                        <div class="stat-title">Total Allocated</div>
                        <div class="stat-value" style="font-size:24px;">
                            {{ $selectedEvent->currencySymbol() }}{{ number_format($totalAllocated, 0) }}
                        </div>
                        <div class="stat-desc">{{ $totalSuggestedDisplay }}% of budget mapped</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Total Spent</div>
                        <div class="stat-value" style="font-size:24px; color: {{ $totalSpent > $totalAllocated ? 'var(--danger)' : 'var(--primary)' }};">
                            {{ $selectedEvent->currencySymbol() }}{{ number_format($totalSpent, 0) }}
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: {{ $totalAllocated > 0 ? min(($totalSpent / $totalAllocated) * 100, 100) : 0 }}%; {{ $totalSpent > $totalAllocated ? 'background-color: var(--danger);' : '' }}"></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Event Budget</div>
                        <div class="stat-value" style="font-size:24px;">
                            {{ $selectedEvent->currencySymbol() }}{{ number_format((float) $selectedEvent->total_budget, 0) }}
                        </div>
                        @php $unallocated = (float) $selectedEvent->total_budget - $totalAllocated; @endphp
                        <div class="stat-desc {{ $unallocated < 0 ? 'danger' : '' }}">
                            {{ $selectedEvent->currencySymbol() }}{{ number_format(abs($unallocated), 0) }}
                            {{ $unallocated < 0 ? 'over-allocated' : 'unallocated' }}
                        </div>
                    </div>
                </div>

                {{-- Category Cards --}}
                <div class="category-grid">
                    @foreach ($categories as $category)
                        @php
                            $cat = strtolower($category->category_name);
                            $iconClass = 'fa-building'; $iconBg = '#e0e7ff'; $iconColor = '#4f46e5';
                            if (str_contains($cat, 'cater')) { $iconClass = 'fa-utensils'; $iconBg = '#fef9c3'; $iconColor = '#ca8a04'; }
                            elseif (str_contains($cat, 'venue')) { $iconClass = 'fa-map-marker-alt'; $iconBg = '#dbeafe'; $iconColor = '#2563eb'; }
                            elseif (str_contains($cat, 'decor')) { $iconClass = 'fa-palette'; $iconBg = '#fce7f3'; $iconColor = '#db2777'; }
                            elseif (str_contains($cat, 'entertain') || str_contains($cat, 'music')) { $iconClass = 'fa-music'; $iconBg = '#ede9fe'; $iconColor = '#7c3aed'; }
                            elseif (str_contains($cat, 'photo')) { $iconClass = 'fa-camera'; $iconBg = '#ccfbf1'; $iconColor = '#0d9488'; }
                            elseif (str_contains($cat, 'travel')) { $iconClass = 'fa-plane'; $iconBg = '#fee2e2'; $iconColor = '#ef4444'; }
                            elseif (str_contains($cat, 'market')) { $iconClass = 'fa-bullhorn'; $iconBg = '#ede9fe'; $iconColor = '#7c3aed'; }
                        @endphp
                        <div class="category-card {{ $category->is_over_budget ? 'is-over' : ($category->is_almost_depleted ? 'is-warning' : '') }}">
                            <div class="category-card-actions">
                                <button type="button"
                                        class="icon-action-btn lock-btn {{ $category->is_locked ? 'is-locked' : '' }}"
                                        data-toggle-lock-url="{{ route('vendor-categories.toggleLock', $category) }}"
                                        title="{{ $category->is_locked ? 'Locked — click to unlock' : 'Click to lock this category' }}">
                                    <i class="fas {{ $category->is_locked ? 'fa-lock' : 'fa-lock-open' }}"></i>
                                </button>
                                <button type="button" class="icon-action-btn category-edit-btn"
                                        data-open-modal="categoryModalEdit"
                                        data-id="{{ $category->id }}"
                                        data-name="{{ $category->category_name }}"
                                        data-suggested="{{ rtrim(rtrim($category->suggested_percentage, '0'), '.') }}"
                                        data-allocated="{{ rtrim(rtrim($category->allocated_amount, '0'), '.') }}"
                                        data-notes="{{ $category->notes }}"
                                        data-locked="{{ $category->is_locked ? 1 : 0 }}"
                                        data-priority="{{ $category->ai_slash_priority }}"
                                        title="Edit category">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="icon-action-btn icon-action-btn--danger"
                                        onclick="deleteVendorCategory({{ $category->id }})" title="Delete category">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>

                            <div class="category-card-top">
                                <div class="category-icon" style="background:{{ $iconBg }};color:{{ $iconColor }};">
                                    <i class="fas {{ $iconClass }}"></i>
                                </div>
                                <div class="category-heading">
                                    <h3 class="category-name">{{ $category->category_name }}</h3>
                                    <span class="allocation-badge">{{ $category->suggested_display }}%</span>
                                    <span class="allocation-caption">Allocation</span>
                                </div>
                            </div>

                            <div class="category-figures">
                                <div>
                                    <span class="figure-label">Budget Cap</span>
                                    <span class="figure-value">{{ $selectedEvent->currencySymbol() }}{{ number_format($category->allocated_amount, 0) }}</span>
                                </div>
                                <div class="figure-right">
                                    <span class="figure-label">Spent</span>
                                    <span class="figure-value {{ $category->is_over_budget ? 'text-danger' : '' }}">{{ $selectedEvent->currencySymbol() }}{{ number_format($category->spent, 0) }}</span>
                                </div>
                            </div>

                            <div class="category-status-row">
                                @if ($category->is_over_budget)
                                    <span class="status-label status-danger"><i class="fas fa-triangle-exclamation"></i> Over Budget</span>
                                @elseif ($category->is_almost_depleted)
                                    <span class="status-label status-warning">Almost Depleted</span>
                                @else
                                    <span class="status-label status-muted">Utilization</span>
                                @endif
                                <span class="status-percent">{{ number_format($category->utilization, 0) }}%</span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill {{ $category->is_over_budget ? 'fill-danger' : ($category->is_almost_depleted ? 'fill-warning' : 'fill-ok') }}"
                                     style="width: {{ min($category->utilization, 100) }}%;"></div>
                            </div>

                            <div class="category-remaining-row">
                                <span class="figure-label">Remaining</span>
                                <span class="figure-value {{ $category->remaining < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ $category->remaining < 0 ? '-' : '' }}{{ $selectedEvent->currencySymbol() }}{{ number_format(abs($category->remaining), 0) }}
                                </span>
                            </div>

                            @if ($category->ai_slash_priority)
                                <div class="slash-priority-tag"><i class="fas fa-scissors"></i> AI slash priority: {{ $category->ai_slash_priority }}</div>
                            @endif

                            @if ($category->notes)
                                <p class="category-notes">{{ Str::limit($category->notes, 60) }}</p>
                            @endif
                        </div>

                        <form id="category-delete-form-{{ $category->id }}"
                              action="{{ route('vendor-categories.destroy', $category) }}" method="POST" style="display:none;">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endforeach

                    <button type="button" class="category-card category-card--add" data-open-modal="categoryModalAdd">
                        <div class="add-card-icon"><i class="fas fa-plus"></i></div>
                        <h3>New Category</h3>
                        <p>Define a new department and allocate event funds.</p>
                    </button>
                </div>
            @endif

        </div>
    </main>

    @if ($selectedEvent)
        {{-- Add Category modal --}}
        <div class="modal-overlay" id="categoryModalAdd">
            <div class="modal-dialog modal-dialog--sm">
                <div class="modal-header">
                    <h3 class="modal-title">Add Vendor Category</h3>
                    <button type="button" class="modal-close" data-close-modal="categoryModalAdd">&times;</button>
                </div>

                <form action="{{ route('vendor-categories.store') }}" method="POST" class="modal-body">
                    @csrf
                    <input type="hidden" name="event_id" value="{{ $selectedEvent->id }}">

                    <div class="form-grid">
                        <div class="form-group form-group--full">
                            <label for="add_category_name">Category Name</label>
                            <input type="text" id="add_category_name" name="category_name" placeholder="e.g. Catering" maxlength="150" required>
                        </div>

                        <div class="form-group">
                            <label for="add_suggested_percentage">Suggested % of Budget</label>
                            <input type="number" id="add_suggested_percentage" name="suggested_percentage" min="0" max="100" step="0.01" placeholder="e.g. 35">
                        </div>

                        <div class="form-group">
                            <label for="add_allocated_amount">Budget Cap ({{ $selectedEvent->currency }})</label>
                            <input type="number" id="add_allocated_amount" name="allocated_amount" min="0" step="0.01" placeholder="0.00" required>
                        </div>

                        <div class="form-group">
                            <label for="add_ai_slash_priority">AI Slash Priority</label>
                            <input type="number" id="add_ai_slash_priority" name="ai_slash_priority" min="1" max="255" placeholder="1 = cut first">
                        </div>

                        <div class="form-group form-group--checkbox">
                            <label for="add_is_locked">
                                <input type="checkbox" id="add_is_locked" name="is_locked" value="1"> Lock this category
                            </label>
                        </div>

                        <div class="form-group form-group--full">
                            <label for="add_notes">Notes</label>
                            <textarea id="add_notes" name="notes" rows="2" placeholder="Optional notes"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-close-modal="categoryModalAdd">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Category</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Category modal - fields populated by categories.js from the clicked card's data attributes --}}
        <div class="modal-overlay" id="categoryModalEdit">
            <div class="modal-dialog modal-dialog--sm">
                <div class="modal-header">
                    <h3 class="modal-title">Edit Vendor Category</h3>
                    <button type="button" class="modal-close" data-close-modal="categoryModalEdit">&times;</button>
                </div>

                <form id="categoryEditForm" data-action-template="{{ route('vendor-categories.update', '__CATEGORY__') }}"
                      action="{{ route('vendor-categories.update', '__CATEGORY__') }}" method="POST" class="modal-body">
                    @csrf
                    @method('PUT')

                    <div class="form-grid">
                        <div class="form-group form-group--full">
                            <label for="edit_category_name">Category Name</label>
                            <input type="text" id="edit_category_name" name="category_name" maxlength="150" required>
                        </div>

                        <div class="form-group">
                            <label for="edit_suggested_percentage">Suggested % of Budget</label>
                            <input type="number" id="edit_suggested_percentage" name="suggested_percentage" min="0" max="100" step="0.01">
                        </div>

                        <div class="form-group">
                            <label for="edit_allocated_amount">Budget Cap ({{ $selectedEvent->currency }})</label>
                            <input type="number" id="edit_allocated_amount" name="allocated_amount" min="0" step="0.01" required>
                        </div>

                        <div class="form-group">
                            <label for="edit_ai_slash_priority">AI Slash Priority</label>
                            <input type="number" id="edit_ai_slash_priority" name="ai_slash_priority" min="1" max="255" placeholder="1 = cut first">
                        </div>

                        <div class="form-group form-group--checkbox">
                            <label for="edit_is_locked">
                                <input type="checkbox" id="edit_is_locked" name="is_locked" value="1"> Lock this category
                            </label>
                        </div>

                        <div class="form-group form-group--full">
                            <label for="edit_notes">Notes</label>
                            <textarea id="edit_notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer modal-footer--split">
                        <button type="button" class="btn-delete-event" style="width:auto;" onclick="deleteVendorCategoryFromEdit()">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        <div>
                            <button type="button" class="btn btn-outline" data-close-modal="categoryModalEdit">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

</body>
</html>
