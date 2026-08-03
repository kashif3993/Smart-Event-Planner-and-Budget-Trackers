<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events — Smart Event Planner</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/events.css') }}?v={{ time() }}">
    <script src="{{ asset('js/events.js') }}?v={{ time() }}" defer></script>
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
            @if (session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error">
                    <ul style="margin:0; padding-left:1.2em;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="page-header">
                <div>
                    <h1 class="page-title">Events</h1>
                    <p class="page-subtitle">Every event you're planning, in one place.</p>
                </div>
                <div class="page-header-actions">
                    <button type="button" class="btn btn-primary" data-open-modal="eventModal">
                        <i class="fas fa-plus"></i> New Event
                    </button>
                </div>
            </div>

            @if ($events->isEmpty())
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-calendar-days"></i></div>
                    <h3>No Events Yet</h3>
                    <p>Create your first event to start building an AI-powered task checklist.</p>
                    <button type="button" class="btn btn-primary" data-open-modal="eventModal">
                        <i class="fas fa-plus"></i> Create Your First Event
                    </button>
                </div>
            @else
                <div class="events-grid">
                    @foreach ($events as $event)
                        <div class="event-card">
                            <a href="{{ route('events.show', $event) }}" class="event-card-link">
                                <div class="event-card-media">
                                    @if ($event->venue_image)
                                        <img src="{{ asset('storage/'.$event->venue_image) }}" alt="{{ $event->event_name }}">
                                    @else
                                        <div class="venue-placeholder"><i class="fas fa-champagne-glasses"></i></div>
                                    @endif
                                    <span class="chip event-card-status status-{{ strtolower(str_replace(' ', '-', $event->status)) }}">{{ $event->status }}</span>
                                </div>
                                <div class="event-card-body">
                                    <h3 class="event-card-title">{{ $event->event_name }}</h3>
                                    <div class="event-card-meta">
                                        <i class="fas fa-calendar"></i> {{ $event->event_date->format('M d, Y') }}
                                        @if ($event->location)
                                            • <i class="fas fa-map-marker-alt"></i> {{ $event->location }}
                                        @endif
                                    </div>

                                    <div class="event-card-stats">
                                        <div>
                                            <span class="event-card-stat-label">Countdown</span>
                                            <span class="event-card-stat-value">
                                                {{ $event->days_remaining >= 0 ? $event->days_remaining.' days' : 'Passed' }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="event-card-stat-label">Guests</span>
                                            <span class="event-card-stat-value">{{ $event->guest_count }}{{ $event->max_guests ? '/'.$event->max_guests : '' }}</span>
                                        </div>
                                    </div>

                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: {{ $event->progress }}%;"></div>
                                    </div>
                                    <div class="stat-desc">{{ $event->progress }}% tasks complete</div>
                                </div>
                            </a>

                            <div class="event-card-actions">
                                <a href="{{ route('events.show', $event) }}" class="card-action-btn" title="View {{ $event->event_name }}">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button type="button" class="card-action-btn event-edit-btn" title="Edit {{ $event->event_name }}"
                                    data-open-modal="eventModalEdit"
                                    data-event-id="{{ $event->id }}"
                                    data-event-name="{{ $event->event_name }}"
                                    data-event-type="{{ $event->event_type }}"
                                    data-event-custom-type="{{ $event->custom_event_type }}"
                                    data-event-date="{{ optional($event->event_date)->toDateString() }}"
                                    data-event-time="{{ $event->event_time }}"
                                    data-event-guest-count="{{ $event->guest_count }}"
                                    data-event-max-guests="{{ $event->max_guests }}"
                                    data-event-venue-name="{{ $event->venue_name }}"
                                    data-event-location="{{ $event->location }}"
                                    data-event-venue-image="{{ $event->venue_image ? asset('storage/'.$event->venue_image) : '' }}"
                                    data-event-total-budget="{{ $event->total_budget }}"
                                    data-event-budget-spent="{{ $event->budget_spent }}"
                                    data-event-currency="{{ $event->currency }}"
                                    data-event-status="{{ $event->status }}"
                                    data-event-description="{{ $event->description }}">
                                    <i class="fas fa-pen"></i> Edit
                                </button>
                                <form action="{{ route('events.destroy', $event) }}" method="POST" class="event-card-delete-form"
                                      onsubmit="return confirm('Delete this event and all of its tasks? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="card-action-btn card-action-btn--danger" title="Delete {{ $event->event_name }}">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </main>

    @include('events.partials.event-form-modal', ['event' => null])
    @include('events.partials.event-edit-modal')

</body>
</html>
