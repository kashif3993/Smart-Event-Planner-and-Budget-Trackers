@php
    $deficitFormatted = $group->currencySymbol().number_format($contentionData['global_deficit'], 0);
    $eventNames = $contentionData['events']->pluck('name')->implode(', ');
@endphp
<div class="panic-banner panic-banner--critical">
    <div>
        <div class="panic-banner-title"><i class="fas fa-triangle-exclamation"></i> Budget contention in "{{ $group->name }}"</div>
        <div class="panic-banner-sub">{{ $eventNames }} are drawing on the same pool, which is now short by {{ $deficitFormatted }}.</div>
    </div>
    @if (Route::has('event-groups.contention.snapshot'))
        @if (request()->routeIs('event-groups.show') && (isset($group) && request()->route('group')?->is($group)))
            <button type="button" class="btn btn-primary btn-sm" data-open-modal="contentionSandboxModal">Open Resolution Sandbox</button>
        @else
            <a href="{{ route('event-groups.show', $group) }}" class="btn btn-primary btn-sm">Open Resolution Sandbox</a>
        @endif
    @endif
</div>
