<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $group->name }} — Timeline</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/event-groups.css') }}?v={{ time() }}">
    <script src="{{ asset('js/event-groups.js') }}?v={{ time() }}" defer></script>
</head>
<body>

    @include('partials.header')
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    @include('partials.sidebar')

    <main class="main-content">
        <div class="dashboard-body">

            <div class="breadcrumb">
                <a href="{{ route('event-groups.index') }}">Event Groups</a>
                <span>/</span>
                <a href="{{ route('event-groups.show', $group) }}">{{ $group->name }}</a>
                <span>/</span>
                <span class="breadcrumb-current">Timeline</span>
            </div>

            <div class="page-header">
                <div>
                    <h1 class="page-title">{{ $group->name }} — Timeline</h1>
                    <p class="page-subtitle">Every sub-event, in one chronological view.</p>
                </div>
            </div>

            @include('event-groups.partials.subnav')

            @if ($overlapDates->isNotEmpty())
                <div class="alert alert-error">
                    <i class="fas fa-triangle-exclamation"></i> Two or more events in this group share the same date — double-check this is intentional.
                </div>
            @endif

            <div class="group-timeline">
                @foreach ($events as $event)
                    @php $overlaps = $overlapDates->contains($event->event_date->toDateString()); @endphp
                    <div class="timeline-row {{ $overlaps ? 'timeline-row--overlap' : '' }}">
                        <div class="timeline-date">{{ $event->event_date->format('M d, Y') }}</div>
                        <div class="timeline-row-info">
                            <div class="timeline-row-name"><a href="{{ route('events.show', $event) }}">{{ $event->event_name }}</a></div>
                            <div class="timeline-row-type">{{ $event->event_type }} @if($event->venue_name) • {{ $event->venue_name }} @endif</div>
                        </div>
                        @if ($overlaps)
                            <span class="chip chip-warning">Same-day overlap</span>
                        @endif
                    </div>
                @endforeach
            </div>

        </div>
    </main>

</body>
</html>
