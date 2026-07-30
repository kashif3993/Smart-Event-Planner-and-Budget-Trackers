<div class="modal-overlay" id="budgetModeModal">
    <div class="modal-dialog modal-dialog--sm">
        <div class="modal-header">
            <h3 class="modal-title">Switch Budget Mode</h3>
            <button type="button" class="modal-close" data-close-modal="budgetModeModal">&times;</button>
        </div>
        <form action="{{ route('event-groups.budgetMode.update', $group) }}" method="POST" class="modal-body">
            @csrf
            @method('PUT')
            <input type="hidden" name="budget_mode" id="targetBudgetModeInput" value="{{ $group->isPooled() ? 'Distributed' : 'Pooled' }}">

            @if (! $group->isPooled())
                <p id="toPooledIntro" style="font-size:13.5px;margin-bottom:14px;">
                    A single shared cap that every event in this group draws against freely, instead of each keeping its own fixed budget.
                </p>
                <div class="form-group form-group--full">
                    <label for="pooled_budget_cap">Pooled Budget Cap ({{ $group->currency }})</label>
                    <input type="number" step="0.01" min="0" id="pooled_budget_cap" name="pooled_budget_cap" value="{{ $group->events->sum('total_budget') }}" required>
                </div>
            @else
                <p style="font-size:13.5px;margin-bottom:14px;">
                    Confirm each event's standalone budget. The system won't invent a split — pick amounts that add up the way you intend.
                </p>
                @foreach ($group->events as $event)
                    <div class="split-row">
                        <span>{{ $event->event_name }} <small style="color:var(--text-muted);">(spent {{ $group->currencySymbol() }}{{ number_format($event->budget_spent, 0) }})</small></span>
                        <input type="number" step="0.01" min="{{ $event->budget_spent }}" name="splits[{{ $event->id }}]" value="{{ $event->total_budget }}" required>
                    </div>
                @endforeach
            @endif

            <div class="modal-footer" style="padding:16px 0 0;border-top:none;">
                <button type="button" class="btn btn-outline" data-close-modal="budgetModeModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm Switch</button>
            </div>
        </form>
    </div>
</div>
