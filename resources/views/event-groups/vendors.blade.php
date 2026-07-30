<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $group->name }} — Vendors</title>
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
                <span class="breadcrumb-current">Vendors</span>
            </div>

            <div class="page-header">
                <div>
                    <h1 class="page-title">Combined Vendor Roster</h1>
                    <p class="page-subtitle">Every vendor engaged across this group's events, with per-event and combined cost.</p>
                </div>
            </div>

            @include('event-groups.partials.subnav')

            <div class="card">
                @if ($vendors->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-handshake"></i></div>
                        <h3>No Vendors Logged Yet</h3>
                        <p>Vendors show up here once they're added as a category to one of this group's events.</p>
                    </div>
                @else
                    <div style="overflow-x:auto;">
                        <table class="roster-table">
                            <thead><tr><th>Vendor</th><th>Serving</th><th>Combined Total</th></tr></thead>
                            <tbody>
                                @foreach ($vendors as $vendor)
                                    <tr>
                                        <td>
                                            {{ $vendor->vendor_name }}
                                            @if ($vendor->sub_event_count > 1)
                                                <span class="chip chip-warning">{{ $vendor->sub_event_count }} events</span>
                                            @endif
                                        </td>
                                        <td>
                                            @foreach ($vendor->per_event as $row)
                                                <div style="font-size:12.5px;">
                                                    {{ $row->event?->event_name }} — {{ $group->currencySymbol() }}{{ number_format($row->cost, 0) }}
                                                </div>
                                            @endforeach
                                        </td>
                                        <td><strong>{{ $group->currencySymbol() }}{{ number_format($vendor->combined_total, 0) }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </main>

</body>
</html>
