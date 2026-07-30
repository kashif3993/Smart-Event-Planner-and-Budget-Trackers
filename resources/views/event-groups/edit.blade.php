<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $group->name }} — Settings</title>
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
                <span class="breadcrumb-current">Settings</span>
            </div>

            <div class="page-header">
                <div>
                    <h1 class="page-title">Group Settings</h1>
                </div>
            </div>

            @include('event-groups.partials.subnav')

            @if ($errors->any())
                <div class="form-errors">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><h2 class="card-title">Details</h2></div>
                <form action="{{ route('event-groups.update', $group) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="form-grid">
                        <div class="form-group form-group--full">
                            <label for="name">Group Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $group->name) }}" required>
                        </div>
                        <div class="form-group">
                            <label for="group_type">Group Type</label>
                            <select id="group_type" name="group_type" required>
                                @foreach (['Wedding', 'Birthday Party', 'Corporate Event', 'Baby Shower', 'Graduation', 'Custom'] as $type)
                                    <option value="{{ $type }}" @selected(old('group_type', $group->group_type) === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="custom_group_type">Custom Type</label>
                            <input type="text" id="custom_group_type" name="custom_group_type" value="{{ old('custom_group_type', $group->custom_group_type) }}">
                        </div>
                        <div class="form-group form-group--full">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="2">{{ old('description', $group->description) }}</textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save Changes</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title" style="color:var(--danger);">Dissolve Group</h2></div>
                <p style="font-size:13.5px;color:var(--text-muted);margin-bottom:16px;">
                    Every event returns to standalone status with all of its data intact. The group itself is removed. This can't be undone.
                </p>
                <button type="button" class="btn btn-outline" style="border-color:var(--danger);color:var(--danger);" data-open-modal="dissolveGroupModal">
                    <i class="fas fa-triangle-exclamation"></i> Dissolve This Group
                </button>
            </div>

        </div>
    </main>

    <div class="modal-overlay" id="dissolveGroupModal">
        <div class="modal-dialog modal-dialog--sm">
            <div class="modal-header">
                <h3 class="modal-title">Dissolve "{{ $group->name }}"</h3>
                <button type="button" class="modal-close" data-close-modal="dissolveGroupModal">&times;</button>
            </div>
            <form action="{{ route('event-groups.destroy', $group) }}" method="POST" class="modal-body">
                @csrf
                @method('DELETE')

                @if ($group->isPooled())
                    <p style="font-size:13.5px;margin-bottom:14px;">
                        This group is in Pooled mode — confirm each event's standalone budget before dissolving. The system won't invent a split for you.
                    </p>
                    @foreach ($group->events as $event)
                        <div class="split-row">
                            <span>{{ $event->event_name }} <small style="color:var(--text-muted);">(already spent {{ $group->currencySymbol() }}{{ number_format($event->budget_spent, 0) }})</small></span>
                            <input type="number" step="0.01" min="{{ $event->budget_spent }}" name="splits[{{ $event->id }}]" value="{{ $event->total_budget }}" required>
                        </div>
                    @endforeach
                @else
                    <p style="font-size:13.5px;">Every event in this group returns to standalone status with its existing budget untouched.</p>
                @endif

                <div class="modal-footer" style="padding:16px 0 0;border-top:none;">
                    <button type="button" class="btn btn-outline" data-close-modal="dissolveGroupModal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background-color:var(--danger);">Dissolve</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
