# Manual Test Data (Set 3)

Covers everything added since Set 2: the `vendor_name` field on Vendor Categories,
Event PDF/Excel export, the Expenses PDF export + Budget & Spend Report, and the
single-task "Suggest with AI" button on the Task modals. Enter in order — Tasks,
Vendor Categories, and Expenses all need an Event to already exist, and Expenses
need a Vendor Category to already exist.

---

## 1. Register — `/register`

| Field | Value |
|---|---|
| Full Name | Sara Ahmed |
| Email | sara.ahmed@example.com |
| Password | BabyShower123 |
| Confirm Password | BabyShower123 |
| Profile Image | (optional, leave blank) |
| Terms & Conditions | checked |

Rules: full_name required (max 100) · email required, valid, unique · password required min 8 chars + must match confirmation · terms must be accepted.

---

## 2. Login — `/login`

| Field | Value |
|---|---|
| Email | sara.ahmed@example.com |
| Password | BabyShower123 |

> Note: visiting `/` while logged out now redirects to **`/login`** (it used to go to `/register`) — worth a quick sanity check before logging in.

---

## 3. Create Event — Events page → "New Event"

| Field | Value |
|---|---|
| Event Name | Emma's Baby Shower |
| Event Type | Baby Shower |
| Custom Event Type | (leave blank) |
| Event Date | 2026-09-12 |
| Event Time | 15:00 |
| Confirmed Guests | 40 |
| Guest Capacity | 50 |
| Venue Name | The Garden Terrace |
| Location | Islamabad, Pakistan |
| Venue Image | (optional) |
| Total Budget | 3000 |
| Budget Spent | 0 |
| Currency | USD |
| Description | Afternoon garden party with brunch and games for close family and friends. |

Rules: event_name required (max 200) · event_type required · event_date required valid date · currency required (PKR or USD).

*(Using USD here on purpose — Set 1/2 were both PKR, so this exercises the `$` symbol path and, if you also have a PKR event on this account, the currency-grouping in the Budget & Spend Report in step 8.)*

---

## 4. Add Task — inside an event → Tasks tab

**Task 1**
| Field | Value |
|---|---|
| Task Name | Order custom cake |
| Phase | Pre-Planning |
| Due Date | 2026-08-01 |
| Priority | High |
| Depends On | (leave blank) |
| Notes | Two-tier, pastel theme, confirm allergy info. |

**Task 2** (tests the dependency field)
| Field | Value |
|---|---|
| Task Name | Send e-invites |
| Phase | Preparation |
| Due Date | 2026-08-20 |
| Priority | Medium |
| Depends On | Order custom cake |
| Notes | Use Evite, track RSVPs. |

**Task 3**
| Field | Value |
|---|---|
| Task Name | Set up gift table |
| Phase | Day-Of |
| Due Date | 2026-09-12 |
| Priority | Low |
| Notes | Near the entrance, with a card box. |

**Task 4 — via "Suggest with AI" (new)**
Instead of typing this one, open the Add Task modal and click **Suggest with AI**:
- Status line should read "Asking the AI for a task…" then fill Task Name / Phase / Priority / Due Date / Notes automatically.
- Fields stay editable — tweak anything, then click **Add Task** to save (the suggestion does not auto-save).
- Requires `AI_TASK_API_KEY` in `.env`; without it you should get a clean `is-error` status message, not a JS crash.
- Try it from the **Edit Task** modal too (same button, `data-target-prefix` should scope it to that modal's fields, not the Add modal's).

Rules: task_name required (max 255) · phase required (Pre-Planning/Preparation/Day-Of) · priority required (Low/Medium/High) · dependency_task_id must be another task in the same event.

---

## 5. Add Vendor Category — inside an event → Budget/Categories tab

**Category 1**
| Field | Value |
|---|---|
| Category Name | Catering |
| Company / Shop Name | Sweet Bites Catering |
| Suggested Percentage | 30 |
| Allocated Amount | 900 |
| Notes | Brunch buffet + dessert table for 40 guests. |
| Locked | unchecked |
| AI Priority | 1 |

**Category 2**
| Field | Value |
|---|---|
| Category Name | Decor |
| Company / Shop Name | Bloom & Co Decor |
| Suggested Percentage | 25 |
| Allocated Amount | 750 |
| Notes | Balloon arch, florals, table settings. |
| Locked | unchecked |

**Category 3** (leave Company/Shop Name blank — tests that the field is truly optional)
| Field | Value |
|---|---|
| Category Name | Photography |
| Company / Shop Name | *(leave blank)* |
| Suggested Percentage | 15 |
| Allocated Amount | 450 |
| Notes | 3-hour candid coverage. |

After saving Category 3, confirm on the category card: Catering/Decor show a small storefront-icon line with the vendor name under the category name, and Photography shows **no** vendor line at all (not even empty).

Rules: category_name required (max 150) · vendor_name optional (max 150) · allocated_amount required numeric ≥ 0 · suggested_percentage optional 0–100.

---

## 6. Add Expense — inside an event → Expenses tab

**Expense 1**
| Field | Value |
|---|---|
| Category | Catering |
| Vendor/Item Name | Sweet Bites Catering — booking deposit |
| Estimated Cost | 900 |
| Actual Cost | 850 |
| Payment Status | Paid |
| Date Logged | 2026-07-19 |
| Notes | Paid in full, includes setup/cleanup. |

**Expense 2**
| Field | Value |
|---|---|
| Category | Decor |
| Vendor/Item Name | Bloom & Co Decor — advance |
| Estimated Cost | 750 |
| Actual Cost | 400 |
| Payment Status | Partially Paid |
| Date Logged | 2026-07-20 |
| Notes | 50% advance, balance on delivery. |

Rules: category_id and event_id must reference existing rows · vendor_item_name required (max 255) · estimated_cost & actual_cost required numeric ≥ 0 · payment_status one of Paid/Pending/Partially Paid.

---

## 7. Trigger the AI Panic Button & Budget Rebalancer — Budget page

**Category 4** (open/unspent — absorbs the cut)
| Field | Value |
|---|---|
| Category Name | Entertainment |
| Allocated Amount | 300 |
| Locked | unchecked |

**Category 5** (manually locked — should stay untouched)
| Field | Value |
|---|---|
| Category Name | Transport |
| Allocated Amount | 150 |
| Locked | checked |

**Expense 3** (pushes Catering to 950 spent against 900 allocated)
| Field | Value |
|---|---|
| Category | Catering |
| Vendor/Item Name | Extra dessert platter |
| Estimated Cost | 40 |
| Actual Cost | 50 |
| Payment Status | Pending |
| Date Logged | 2026-07-20 |
| Notes | Guest count went up by a few last minute. |

**What you should see on `/budget`:**
1. Red **"Budget overrun detected"** banner with a **Rebalance** button.
2. Rebalancer Sandbox → Over Budget By: **$50**. Flexible Liquidity Left = unspent headroom across Decor/Photography/Entertainment (Transport excluded, locked).
3. **Proportional**: $50 spread across Decor, Photography, Entertainment by allocation (skip anything with a `Paid` expense that removes flexibility — verify against actual app behavior for Decor since it's only `Partially Paid`).
4. **Targeted**: full $50 pulled from whichever open category has the most unspent headroom.
5. **AI-Suggested**: needs `AI_TASK_API_KEY` + internet; clean error if missing, not a crash.
6. Lock/unlock toggle recalculates the chart live.
7. **Commit Changes** → success message, page reloads, Catering now reads 950 allocated and is no longer flagged.

---

## 8. Export & Reporting (new)

**8a. Event export — event page (`/events/{event}`)**
- Click the PDF export action → downloads `emmas-baby-shower-<today>.pdf`. Confirm tasks are grouped by phase (Pre-Planning → Preparation → Day-Of) and within each phase ordered High → Medium → Low priority, then by due date.
- Click the Excel export action → downloads `emmas-baby-shower-<today>.xlsx` with two sheets: **Summary** (event details, budget figures) and **Tasks** (purple header row, one row per task incl. Source column).
- Try exporting an event that belongs to a *different* user's account (change the URL's event id) → expect a 403, not the file.

**8b. Expenses export + Budget & Spend Report — `/expenses`**
- On the Expenses page, apply a filter (e.g. Category = Catering) and click **Export PDF**. The downloaded PDF's expense list must match exactly what's shown on-screen after the filter (both use the same underlying query).
- Filename should be `expense-report-all-events-<today>.pdf` with no event filter selected, or `expense-report-emmas-baby-shower-<today>.pdf` when the Event filter/report scope is set to this event.
- Switch the **report scope** dropdown between "All Events" and a specific event:
  - All Events: detail rows break down **by event**.
  - Single event: detail rows break down **by vendor category**, and each row's sublabel shows the category's vendor name (from step 5).
- Confirm the status badge logic per row/group: `no-budget` (budget ≤ 0), `over` (spent > budget), `at-risk` (spent + still-owed > budget), `watch` (spent ≥ 90% of budget), else `on-track`.
- If your account has both this USD event and an older PKR event, confirm "All Events" groups totals **by currency** rather than summing PKR and USD together.

---

## Quick checklist

- [ ] Register a user
- [ ] Verify `/` redirects to `/login` while logged out
- [ ] Log in
- [ ] Create 1 event (try USD this time)
- [ ] Add 3 tasks manually + 1 via "Suggest with AI" (both Add and Edit modals)
- [ ] Add 3 vendor categories, one with vendor name left blank — confirm the vendor line only renders when present
- [ ] Add 2 expenses tied to those categories
- [ ] Add the extra categories + Expense 3 to trigger the Panic Button; run Proportional / Targeted / AI-Suggested, toggle a lock, commit
- [ ] Export the event as PDF and Excel; check phase/priority ordering and the two-sheet Excel layout
- [ ] Filter the Expenses page, export PDF, confirm it matches the on-screen filtered list
- [ ] Toggle report scope (All Events vs. single event) and check the status badges + currency grouping
- [ ] Confirm exporting another user's event returns 403
