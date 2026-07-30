<div class="group-subnav">
    <a href="{{ route('event-groups.show', $group) }}" class="{{ request()->routeIs('event-groups.show') ? 'active' : '' }}">Overview</a>
    <a href="{{ route('event-groups.timeline', $group) }}" class="{{ request()->routeIs('event-groups.timeline') ? 'active' : '' }}">Timeline</a>
    @if (Route::has('event-groups.guests'))
        <a href="{{ route('event-groups.guests', $group) }}" class="{{ request()->routeIs('event-groups.guests') ? 'active' : '' }}">Guests</a>
    @endif
    @if (Route::has('event-groups.vendors'))
        <a href="{{ route('event-groups.vendors', $group) }}" class="{{ request()->routeIs('event-groups.vendors') ? 'active' : '' }}">Vendors</a>
    @endif
    @if (Route::has('event-groups.resolutions.index') && $group->isPooled())
        <a href="{{ route('event-groups.resolutions.index', $group) }}" class="{{ request()->routeIs('event-groups.resolutions.*') ? 'active' : '' }}">Resolutions</a>
    @endif
    <a href="{{ route('event-groups.edit', $group) }}" class="{{ request()->routeIs('event-groups.edit') ? 'active' : '' }}">Settings</a>
</div>
