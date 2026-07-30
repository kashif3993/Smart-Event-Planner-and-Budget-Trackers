<?php

namespace App\Http\Controllers;

use App\Models\EventGroup;
use App\Services\ContentionDetectionService;
use App\Services\GroupRollupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupBudgetModeController extends Controller
{
    /**
     * EG-42…EG-50 — switch a group between Distributed and Pooled budget
     * modes. Never alters recorded spend/guest/vendor data (EG-48), blocked
     * mid-contention (EG-50).
     */
    public function update(EventGroup $group, ContentionDetectionService $contention, GroupRollupService $rollup): RedirectResponse
    {
        $this->authorizeGroup($group);
        abort_if($group->isArchived(), 403);

        if ($group->isPooled() && $contention->isContended($group)) {
            return back()->with('error', 'Resolve the active budget contention before changing this group\'s budget mode.');
        }

        $data = request()->validate([
            'budget_mode' => ['required', 'in:Distributed,Pooled'],
        ]);

        if ($data['budget_mode'] === $group->budget_mode) {
            return back()->with('error', "This group is already in {$group->budget_mode} mode.");
        }

        if ($data['budget_mode'] === 'Pooled') {
            return $this->switchToPooled($group);
        }

        return $this->switchToDistributed($group, $rollup);
    }

    /**
     * EG-46 — the cap is required and pre-filled client-side with the sum of
     * existing sub-event budgets; the server independently enforces it can't
     * be set below what's already been spent across the group.
     */
    protected function switchToPooled(EventGroup $group): RedirectResponse
    {
        $data = request()->validate([
            'pooled_budget_cap' => ['required', 'numeric', 'min:0'],
        ]);

        $combinedSpend = (float) $group->events->sum('budget_spent');

        if ((float) $data['pooled_budget_cap'] < $combinedSpend) {
            return back()->with('error', 'The pooled cap can\'t be less than what\'s already been spent across the group ('.$group->currencySymbol().number_format($combinedSpend, 0).').');
        }

        $group->update([
            'budget_mode' => 'Pooled',
            'pooled_budget_cap' => $data['pooled_budget_cap'],
        ]);

        return back()->with('success', 'Switched to Pooled budget mode.');
    }

    /**
     * EG-47 — switching back requires an explicit, user-confirmed standalone
     * budget for every sub-event. The system never invents the split.
     */
    protected function switchToDistributed(EventGroup $group, GroupRollupService $rollup): RedirectResponse
    {
        $splits = request()->input('splits', []);
        $events = $group->events;

        if (count($splits) !== $events->count()) {
            return back()->with('error', 'Confirm a standalone budget for every event before switching to Distributed mode.');
        }

        foreach ($events as $event) {
            if (! isset($splits[$event->id]) || (float) $splits[$event->id] < (float) $event->budget_spent) {
                return back()->with('error', "The standalone budget for \"{$event->event_name}\" can't be less than what's already spent.");
            }
        }

        DB::transaction(function () use ($events, $splits, $group) {
            foreach ($events as $event) {
                $event->update(['total_budget' => $splits[$event->id]]);
            }

            $group->update([
                'budget_mode' => 'Distributed',
                'pooled_budget_cap' => null,
            ]);
        });

        return back()->with('success', 'Switched to Distributed budget mode.');
    }

    protected function authorizeGroup(EventGroup $group): void
    {
        abort_unless($group->user_id === Auth::id(), 403);
    }
}
