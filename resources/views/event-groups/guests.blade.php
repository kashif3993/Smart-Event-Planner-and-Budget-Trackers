<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $group->name }} — Guests</title>
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

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif

            <div class="breadcrumb">
                <a href="{{ route('event-groups.index') }}">Event Groups</a>
                <span>/</span>
                <a href="{{ route('event-groups.show', $group) }}">{{ $group->name }}</a>
                <span>/</span>
                <span class="breadcrumb-current">Guests</span>
            </div>

            <div class="page-header">
                <div>
                    <h1 class="page-title">Combined Guest Roster</h1>
                    <p class="page-subtitle">{{ $uniqueCount }} unique people, {{ $attendanceCount }} total attendances across the group.</p>
                </div>
                <div class="page-header-actions">
                    <button type="button" class="btn btn-primary" data-open-modal="addGuestModal"><i class="fas fa-plus"></i> Add Guest</button>
                </div>
            </div>

            @include('event-groups.partials.subnav')

            @if ($duplicates->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h2 class="card-title"><i class="fas fa-clone" style="color:#d97706;"></i> Possible Duplicates</h2></div>
                    @foreach ($duplicates as $pair)
                        <div class="duplicate-pair-row">
                            <span>
                                <strong>{{ $pair['a']->name }}</strong> ({{ $pair['a']->events->pluck('event_name')->implode(', ') }})
                                and
                                <strong>{{ $pair['b']->name }}</strong> ({{ $pair['b']->events->pluck('event_name')->implode(', ') }})
                            </span>
                            <span style="display:flex;gap:8px;">
                                <form action="{{ route('event-groups.guests.merge', $group) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="survivor_id" value="{{ $pair['a']->id }}">
                                    <input type="hidden" name="duplicate_id" value="{{ $pair['b']->id }}">
                                    <button type="submit" class="btn btn-primary btn-sm">Merge — Same Person</button>
                                </form>
                                <form action="{{ route('event-groups.guests.dismissDuplicate', $group) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="guest_id_a" value="{{ $pair['a']->id }}">
                                    <input type="hidden" name="guest_id_b" value="{{ $pair['b']->id }}">
                                    <button type="submit" class="btn btn-outline btn-sm">Not the Same</button>
                                </form>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="card">
                <div class="card-header"><h2 class="card-title">Guests</h2></div>
                @if ($guests->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-user-group"></i></div>
                        <h3>No Named Guests Yet</h3>
                        <p>Add guests here to see who's attending which event, de-duplicated across the group.</p>
                    </div>
                @else
                    <div style="overflow-x:auto;">
                        <table class="roster-table">
                            <thead><tr><th>Name</th><th>Contact</th><th>Attending</th></tr></thead>
                            <tbody>
                                @foreach ($guests as $guest)
                                    <tr>
                                        <td>{{ $guest->name }}</td>
                                        <td>{{ $guest->email ?: '—' }} {{ $guest->phone ? '· '.$guest->phone : '' }}</td>
                                        <td>
                                            @foreach ($guest->events as $e)
                                                <span class="chip chip-success">{{ $e->event_name }}</span>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </main>

    <div class="modal-overlay" id="addGuestModal">
        <div class="modal-dialog modal-dialog--sm">
            <div class="modal-header">
                <h3 class="modal-title">Add Guest</h3>
                <button type="button" class="modal-close" data-close-modal="addGuestModal">&times;</button>
            </div>
            <form action="{{ route('event-groups.guests.store', $group) }}" method="POST" class="modal-body">
                @csrf
                <div class="form-group form-group--full">
                    <label for="guest_name">Name</label>
                    <input type="text" id="guest_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="guest_email">Email (optional)</label>
                    <input type="email" id="guest_email" name="email">
                </div>
                <div class="form-group">
                    <label for="guest_phone">Phone (optional)</label>
                    <input type="text" id="guest_phone" name="phone">
                </div>
                <div class="form-group form-group--full">
                    <label>Attending</label>
                    @foreach ($group->events as $event)
                        <label style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:6px;">
                            <input type="checkbox" name="event_ids[]" value="{{ $event->id }}" style="width:auto;" checked> {{ $event->event_name }}
                        </label>
                    @endforeach
                </div>
                <div class="modal-footer" style="padding:16px 0 0;border-top:none;">
                    <button type="button" class="btn btn-outline" data-close-modal="addGuestModal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Guest</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
