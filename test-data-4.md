# Manual Test Data (Set 4)

Covers the new **Event Groups & Pooled Budget Contention Rebalancer** feature — the biggest drop yet.
A "wedding weekend" persona walks through forming a group from existing events, the combined guest/vendor
rosters, switching to Pooled budget mode, tripping contention, and resolving it with both negotiation
strategies. Enter in order — Event Groups need at least 2 existing events, and contention needs the group
to already be in Pooled mode.

---

## 1. Register — `/register`

| Field | Value |
|---|---|
| Full Name | Zara Malik |
| Email | zara.malik@example.com |
| Password | WeddingWeekend26 |
| Confirm Password | WeddingWeekend26 |
| Profile Image | (optional, leave blank) |
| Terms & Conditions | checked |

## 2. Login — `/login`

| Field | Value |
|---|---|
| Email | zara.malik@example.com |
| Password | WeddingWeekend26 |

---

## 3. Create 3 Events — Events page → "New Event"

All three share one currency (**PKR**) and one `event_type` (**Wedding**, weight 3) on purpose — with weight held equal, the Strict Hierarchy ranking later is driven purely by how soon each event happens, which is the cleanest way to see it work.

**Event 1 — Rehearsal Dinner**
| Field | Value |
|---|---|
| Event Name | Rehearsal Dinner |
| Event Type | Wedding |
| Event Date | *(today + 10 days)* |
| Event Time | 19:00 |
| Confirmed Guests | 30 |
| Guest Capacity | 40 |
| Venue Name | Rosewood Terrace |
| Location | Lahore, Pakistan |
| Total Budget | 150000 |
| Budget Spent | 0 |
| Currency | PKR |
| Description | Intimate dinner for close family the night before. |

**Event 2 — Ceremony**
| Field | Value |
|---|---|
| Event Name | Ceremony |
| Event Type | Wedding |
| Event Date | *(today + 11 days)* |
| Event Time | 17:00 |
| Confirmed Guests | 200 |
| Guest Capacity | 250 |
| Venue Name | Grand Emerald Hall |
| Location | Lahore, Pakistan |
| Total Budget | 200000 |
| Budget Spent | 0 |
| Currency | PKR |
| Description | Main ceremony and reception. |

**Event 3 — Farewell Brunch**
| Field | Value |
|---|---|
| Event Name | Farewell Brunch |
| Event Type | Wedding |
| Event Date | *(today + 12 days)* |
| Event Time | 11:00 |
| Confirmed Guests | 60 |
| Guest Capacity | 80 |
| Venue Name | Rosewood Terrace |
| Location | Lahore, Pakistan |
| Total Budget | 100000 |
| Budget Spent | 0 |
| Currency | PKR |
| Description | Casual send-off brunch the morning after. |

Rules: event_name required (max 200) · event_type required · event_date required valid date · currency required (PKR or USD).

---

## 4. Form the Group — `/event-groups/create`

Select **Rehearsal Dinner** and **Ceremony** only (leave Farewell Brunch out for now — it gets added afterward to test the second entry point).

| Field | Value |
|---|---|
| Group Name | Zara & Ahsan's Wedding Weekend |
| Group Type | Wedding |
| Description | Rehearsal dinner, ceremony, and farewell brunch across one weekend. |
| Events selected | Rehearsal Dinner, Ceremony |

Rules: name required (max 200) · group_type required · custom_group_type required only if group_type = Custom · event_ids required, between **2 and 6**, must belong to you, must not already be in another group, and must all share one currency.

**What you should see:** a live preview panel showing combined budget (350,000) and combined guest count (230) before you submit. After creating, you land on the group overview in **Distributed** mode by default — confirm nothing about either event changed (open Rehearsal Dinner directly; its own budget/guests are untouched).

Leave **Farewell Brunch** standalone for now — it comes into the group later (Section 12), after the pooled-budget scenario below is resolved, so it doesn't skew the deficit math while you're following along.

---

## 5. Combined Guest Roster — group page → Guests tab

**Guest 1** (attends two sub-events at once — tests EG-32)
| Field | Value |
|---|---|
| Name | Hina Farooq |
| Email | hina.farooq@example.com |
| Phone | 03001234567 |
| Attending | Rehearsal Dinner, Ceremony |

**Guest 2** (same name added separately under a different event — creates a duplicate candidate)
| Field | Value |
|---|---|
| Name | Bilal Sheikh |
| Attending | Rehearsal Dinner |

**Guest 3**
| Field | Value |
|---|---|
| Name | Bilal Sheikh |
| Attending | Ceremony |

**Guest 4 / 5** (a second duplicate pair, so you can test both actions)
| Field | Value |
|---|---|
| Name | Nadia Iqbal |
| Attending | Ceremony |

then again:
| Field | Value |
|---|---|
| Name | Nadia Iqbal |
| Attending | Rehearsal Dinner |

Rules: name required (max 150) · email optional valid · phone optional (max 30) · at least one event must be checked.

**What you should see:** a "Possible Duplicates" card listing both Bilal Sheikh and Nadia Iqbal pairs. Click **Merge — Same Person** on the Bilal pair (confirm the roster now shows one Bilal Sheikh attending both events, total guest count drops by one). Click **Not the Same** on the Nadia pair (confirm it disappears from the duplicates card and does **not** reappear on refresh).

---

## 6. Combined Vendor Roster — add categories, then check the Vendors tab

Add these Vendor Categories (Budget/Categories tab, same as always) so the same caterer shows up serving two sub-events:

**On Rehearsal Dinner:**
| Field | Value |
|---|---|
| Category Name | Catering |
| Company / Shop Name | Saffron Kitchen |
| Allocated Amount | 90000 |

**On Ceremony:**
| Field | Value |
|---|---|
| Category Name | Catering |
| Company / Shop Name | Saffron Kitchen |
| Allocated Amount | 80000 |
| — second category — | |
| Category Name | Venue |
| Company / Shop Name | *(leave blank)* |
| Allocated Amount | 120000 |

The Vendors tab totals what's actually been **spent** (logged expenses), not allocated amounts — so log one small expense against each Catering category to give it a real figure:

| Event | Category | Vendor/Item Name | Estimated Cost | Actual Cost | Payment Status |
|---|---|---|---|---|---|
| Rehearsal Dinner | Catering | Saffron Kitchen — advance | 90000 | 30000 | Partially Paid |
| Ceremony | Catering | Saffron Kitchen — deposit | 80000 | 20000 | Partially Paid |

Then open the group's **Vendors** tab: "Saffron Kitchen" should appear once with a **2 events** chip, per-event cost (30,000 / 20,000), and a combined total of **50,000**.

---

## 7. Switch to Pooled Mode — group overview → "Switch Mode"

| Field | Value |
|---|---|
| Pooled Budget Cap | 335000 |

The field pre-fills with 350,000 (sum of the group's two current events); type over it with 335000. Rules: required, numeric, and can't be set below what's already been spent across the group (0 right now, so anything ≥ 0 is accepted).

**What you should see:** mode badge switches to "Pooled Budget", and the overview's Combined Budget stat now reads the cap (335,000) instead of the sum of event budgets.

---

## 8. Trip Contention — edit each event's Budget Spent

Open **Edit** on each of the two grouped events and set:

| Event | Total Budget | Budget Spent | Result |
|---|---|---|---|
| Rehearsal Dinner | 150000 | 140000 | 93% — projecting |
| Ceremony | 200000 | 195000 | 97.5% — projecting |

(Leave Farewell Brunch alone — it's still standalone at this point, not in the group at all.)

**What you should see, on both the Dashboard and the group overview:** a critical red banner reading "Budget contention in 'Zara & Ahsan's Wedding Weekend'" naming Rehearsal Dinner and Ceremony with a Global Deficit of **PKR 15,000** (combined cost 350,000 − cap 335,000). It looks and reads noticeably different from the single-event orange overspend banner.

---

## 9. Contention Resolution Sandbox — click "Open Resolution Sandbox"

**Strict Hierarchy (default):**
- Ceremony should rank **#1** (concedes first) and Rehearsal Dinner **#2**. Both score `weight ÷ days remaining`, and since both are Wedding-type (equal weight 3), the only thing that differs is runway — Ceremony is one day further out (11 days vs. 10), giving it the lower score, so it's the one asked to give ground first. If you run this on a later day than you created the events, the gap holds either way since it's the same 1-day difference throughout.
- Ceremony's proposed concession should be **5,000** (its full headroom — 200,000 budget minus 195,000 spent), and the remainder cascades to Rehearsal Dinner for the other **10,000** (its full headroom too). Unabsorbed should read **0** — the deficit is fully covered.

**Test the immunity lock:** check "Immune" on Rehearsal Dinner, watch it re-run automatically. Now only Ceremony can concede (capped at 5,000) — the Unabsorbed figure should show **10,000** remaining, and a status message should explain the impasse. Try checking "Immune" on Ceremony too (the only other contending event) — it should be **blocked** with a message that at least one event must stay eligible. Uncheck Rehearsal Dinner's immunity again before continuing.

**Multi-Agent Negotiation:** switch the strategy toggle.
- If `AI_TASK_API_KEY` is set in `.env`: watch the negotiation stream progressively reveal a plain-language rationale for each event, then a proposal (you can click "Skip to Final Proposal" to jump ahead).
- If it's **not** set: you should see a clear "isn't configured yet" message and the sandbox should automatically fall back to a Strict Hierarchy result — never a blank screen or a raw error.

**Manual override:** switch back to Strict Hierarchy, then type a different amount into Ceremony's concession field (e.g. 3000). Confirm the Unabsorbed figure updates immediately to reflect the shortfall this creates.

---

## 10. Commit

Reset back to the plain Strict Hierarchy proposal (no immunity, no manual edits — full absorption) and click **Commit Allocation**.

**What you should see:**
- Success message, sandbox closes, page reloads.
- Ceremony's budget is now **195,000** (200,000 − 5,000); its Venue/Catering categories should have shrunk proportionally to fit.
- Rehearsal Dinner's budget is now **140,000** (150,000 − 10,000); its Catering category shrunk proportionally too.
- The contention banner is gone from both the Dashboard and the group overview.

## 11. Resolution History — group page → Resolutions tab

Confirm the commit you just made is listed: strategy used, the PKR 15,000 deficit, and the full rationale text for both events, still there after a refresh.

---

## 12. Lifecycle checks (bonus)

Do these in order — switching back to Distributed *before* adding Brunch back in avoids re-triggering a second contention while you're just testing membership/lifecycle actions.

- **Switch Mode → Distributed**: it should require you to type in a standalone budget for each event (pre-filled with the current, post-commit figures — 140,000 and 195,000) rather than inventing a split itself.
- **Add Farewell Brunch** to the group (group overview → **+ Add Event**) — this is the second way to form a group's membership (the first was the creation wizard back in Section 4). Confirm the combined budget updates immediately to include it (140,000 + 195,000 + 100,000 = 435,000).
- **Detach** Farewell Brunch again (group overview → Remove). Confirm it becomes a fully standalone event with its own budget untouched, and the group's combined figures update immediately back down.
- Try **Dissolve This Group** (Settings tab) — confirm both remaining events return to standalone status with all their data (tasks, categories, expenses) intact, and the group itself disappears from `/event-groups`.

---

## Quick checklist

- [ ] Register + log in
- [ ] Create 3 Wedding-type PKR events with the exact dates/budgets above
- [ ] Create a group from 2 of them via the wizard; confirm the live preview and that grouping didn't alter either event
- [ ] Add 5 guests; confirm both duplicate pairs surface; merge one pair, dismiss the other and confirm it doesn't resurface
- [ ] Add matching vendor categories on two events; confirm the Vendors tab shows one combined row with a 2-events chip
- [ ] Switch the group to Pooled mode with a 335,000 cap
- [ ] Edit both grouped events' budgets to trip contention; confirm the banner names only those two
- [ ] Open the sandbox; confirm Strict Hierarchy ranking, cascade, and full absorption
- [ ] Lock an event immune, confirm the residual shortfall message; try locking every event and confirm it's blocked
- [ ] Try Multi-Agent Negotiation with and without `AI_TASK_API_KEY` set; confirm the automatic fallback message when it's missing
- [ ] Manually override a concession and confirm the tracker updates live
- [ ] Commit; confirm both events' budgets and categories updated, and the banner cleared everywhere
- [ ] Check the Resolutions tab shows the committed history with full rationale
- [ ] Switch back to Distributed with a manual split, add Farewell Brunch via "+ Add Event", detach it again, then dissolve the group and confirm every event survives intact
