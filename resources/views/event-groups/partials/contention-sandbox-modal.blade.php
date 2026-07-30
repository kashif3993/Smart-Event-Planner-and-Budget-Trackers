<div class="modal-overlay" id="contentionSandboxModal">
    <div class="modal-dialog modal-dialog--wide">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-scale-balanced"></i> Contention Resolution Sandbox</h3>
            <button type="button" class="modal-close" data-close-modal="contentionSandboxModal">&times;</button>
        </div>

        <div class="modal-body">
            <p class="rebalance-intro">
                A non-destructive simulation of "{{ $group->name }}". Nothing changes on any event until you click "Commit Allocation".
            </p>

            <div class="pooled-health-tracker" id="sandboxHealthTracker">
                <div class="pooled-health-item">
                    <div class="pooled-health-label">Pooled Cap</div>
                    <div class="pooled-health-value" id="sandboxCapValue">—</div>
                </div>
                <div class="pooled-health-item">
                    <div class="pooled-health-label">Committed Spend</div>
                    <div class="pooled-health-value" id="sandboxSpendValue">—</div>
                </div>
                <div class="pooled-health-item">
                    <div class="pooled-health-label">Global Deficit</div>
                    <div class="pooled-health-value pooled-health-value--danger" id="sandboxDeficitValue">—</div>
                </div>
                <div class="pooled-health-item">
                    <div class="pooled-health-label">Unabsorbed</div>
                    <div class="pooled-health-value" id="sandboxUnabsorbedValue">—</div>
                </div>
            </div>

            <div class="strategy-toggle-row" id="sandboxStrategyToggles">
                <label class="strategy-toggle">
                    <input type="radio" name="sandbox_strategy" value="hierarchy" checked>
                    <span><i class="fas fa-sitemap"></i> Strict Hierarchy</span>
                </label>
                <label class="strategy-toggle">
                    <input type="radio" name="sandbox_strategy" value="ai" {{ Route::has('event-groups.contention.negotiate') ? '' : 'disabled' }}>
                    <span><i class="fas fa-robot"></i> Multi-Agent Negotiation</span>
                </label>
            </div>

            <div id="sandboxStatus" class="rebalance-status" style="display:none;"></div>

            <div class="negotiation-skip" id="sandboxSkipWrap" style="display:none;">
                <button type="button" class="btn btn-outline btn-sm" id="sandboxSkipReveal">Skip to Final Proposal</button>
            </div>
            <div class="negotiation-stream" id="sandboxNegotiationStream" style="display:none;"></div>

            <div class="cross-event-visualizer" id="sandboxVisualizer"></div>

            <div id="sandboxEventList"></div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline" data-close-modal="contentionSandboxModal">Cancel</button>
            <button type="button" class="btn btn-primary" id="sandboxCommitBtn" disabled
                {{ Route::has('event-groups.contention.commit') ? '' : 'title="Commit is not yet available"' }}>
                <i class="fas fa-check"></i> Commit Allocation
            </button>
        </div>
    </div>
</div>
