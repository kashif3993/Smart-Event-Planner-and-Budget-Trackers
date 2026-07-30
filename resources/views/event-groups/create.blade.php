<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>New Event Group — Smart Event Planner</title>
    <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/event-groups.css') }}?v={{ time() }}">
    <script>
        window.eventGroupCandidateEvents = {!! json_encode($candidateEvents->map(fn ($e) => [
            'id' => $e->id,
            'name' => $e->event_name,
            'type' => $e->event_type,
            'date' => optional($e->event_date)->format('M d, Y'),
            'currency' => $e->currency,
            'total_budget' => (float) $e->total_budget,
            'guest_count' => (int) $e->guest_count,
        ])) !!};
    </script>
    <script src="{{ asset('js/event-groups.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/event-group-create.js') }}?v={{ time() }}" defer></script>
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
                <span class="breadcrumb-current">New Group</span>
            </div>

            <div class="page-header">
                <div>
                    <h1 class="page-title">Create an Event Group</h1>
                    <p class="page-subtitle">Combine 2–6 of your existing events into one managed undertaking. Nothing about them changes — you just gain a container above them.</p>
                </div>
            </div>

            @if ($errors->any())
                <div class="form-errors">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($candidateEvents->count() < 2)
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-triangle-exclamation"></i></div>
                    <h3>Not Enough Standalone Events</h3>
                    <p>You need at least two events that aren't already in a group to form a new one.</p>
                    <a href="{{ route('events.index') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Create Events</a>
                </div>
            @else
                <form action="{{ route('event-groups.store') }}" method="POST">
                    @csrf

                    <div class="card">
                        <div class="card-header"><h2 class="card-title">Group Details</h2></div>
                        <div class="form-grid">
                            <div class="form-group form-group--full">
                                <label for="name">Group Name</label>
                                <input type="text" id="name" name="name" placeholder="e.g. Amara &amp; Sam's Wedding Weekend" value="{{ old('name') }}" required>
                            </div>
                            <div class="form-group">
                                <label for="group_type">Group Type</label>
                                <select id="group_type" name="group_type" required>
                                    @foreach (['Wedding', 'Birthday Party', 'Corporate Event', 'Baby Shower', 'Graduation', 'Custom'] as $type)
                                        <option value="{{ $type }}" @selected(old('group_type') === $type)>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group" id="customGroupTypeWrap" style="display:none;">
                                <label for="custom_group_type">Custom Type</label>
                                <input type="text" id="custom_group_type" name="custom_group_type" value="{{ old('custom_group_type') }}">
                            </div>
                            <div class="form-group form-group--full">
                                <label for="description">Description (optional)</label>
                                <textarea id="description" name="description" rows="2">{{ old('description') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h2 class="card-title">Select Events (2–6)</h2></div>

                        <div class="event-select-list" id="eventSelectList">
                            @foreach ($candidateEvents as $event)
                                <label class="event-select-row" data-event-id="{{ $event->id }}">
                                    <input type="checkbox" name="event_ids[]" value="{{ $event->id }}" @checked(in_array($event->id, old('event_ids', [])))>
                                    <div class="event-select-info">
                                        <div class="event-select-name">{{ $event->event_name }}</div>
                                        <div class="event-select-sub">
                                            {{ $event->event_type }} • {{ optional($event->event_date)->format('M d, Y') }} •
                                            {{ $event->currencySymbol() }}{{ number_format($event->total_budget, 0) }} •
                                            {{ $event->currency }}
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        <div class="group-preview" id="groupPreview" style="display:none;">
                            <div class="group-preview-row"><strong>Events selected</strong><span id="previewCount">0</span></div>
                            <div class="group-preview-row"><strong>Combined budget</strong><span id="previewBudget">0</span></div>
                            <div class="group-preview-row"><strong>Combined guests</strong><span id="previewGuests">0</span></div>
                        </div>
                        <div class="rebalance-status is-error" id="currencyWarning" style="display:none;">
                            Selected events must all use the same currency.
                        </div>
                    </div>

                    <div class="modal-footer" style="border-top:none;padding:0;">
                        <a href="{{ route('event-groups.index') }}" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="createGroupBtn" disabled>
                            <i class="fas fa-layer-group"></i> Create Group
                        </button>
                    </div>
                </form>
            @endif

        </div>
    </main>

</body>
</html>
