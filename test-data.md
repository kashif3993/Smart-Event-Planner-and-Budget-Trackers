# Manual Test Data

Sample values for each module/form in the app, matching the exact validation rules in the code. Enter these **in order** — Tasks, Vendor Categories, and Expenses all need an Event to already exist, and Expenses need a Vendor Category to already exist.

---

## 1. Register — `/register`

| Field | Value |
|---|---|
| Full Name | Ayesha Khan |
| Email | ayesha.khan@example.com |
| Password | Password123 |
| Confirm Password | Password123 |
| Profile Image | (optional, leave blank) |
| Terms & Conditions | checked |

Rules: full_name required (max 100) · email required, valid, unique · password required min 8 chars + must match confirmation · terms must be accepted.

---

## 2. Login — `/login`

| Field | Value |
|---|---|
| Email | ayesha.khan@example.com |
| Password | Password123 |

---

## 3. Create Event — Events page → "New Event"

| Field | Value |
|---|---|
| Event Name | Ayesha & Bilal's Wedding |
| Event Type | Wedding |
| Custom Event Type | (leave blank — only required if Event Type = Custom) |
| Event Date | 2026-11-14 |
| Event Time | 18:30 |
| Confirmed Guests | 150 |
| Guest Capacity | 200 |
| Venue Name | Pearl Continental Banquet Hall |
| Location | Lahore, Pakistan |
| Venue Image | (optional) |
| Total Budget | 500000 |
| Budget Spent | 0 |
| Currency | PKR |
| Description | Traditional wedding ceremony with reception for 150 guests. |

Rules: event_name required (max 200) · event_type required (Wedding/Birthday Party/Corporate Event/Baby Shower/Graduation/Custom) · event_date required valid date · event_time optional `HH:MM` · currency required (PKR or USD).

> Note: event creation no longer auto-generates AI tasks (removed for speed) — it only creates the event + suggested vendor categories. Use the "Generate with AI" button on the event page if you want AI tasks, or add tasks manually below.

---

## 4. Add Task — inside an event → Tasks tab

**Task 1**
| Field | Value |
|---|---|
| Task Name | Book the venue |
| Phase | Pre-Planning |
| Due Date | 2026-08-01 |
| Priority | High |
| Depends On | (leave blank) |
| Notes | Confirm deposit and signed contract. |

**Task 2** (tests the dependency field)
| Field | Value |
|---|---|
| Task Name | Send invitations |
| Phase | Preparation |
| Due Date | 2026-09-15 |
| Priority | Medium |
| Depends On | Book the venue |
| Notes | Send digital + printed invites. |

**Task 3**
| Field | Value |
|---|---|
| Task Name | Confirm final headcount with caterer |
| Phase | Day-Of |
| Due Date | 2026-11-13 |
| Priority | High |
| Notes | Call caterer 24 hours before. |

Rules: task_name required (max 255) · phase required (Pre-Planning/Preparation/Day-Of) · priority required (Low/Medium/High) · dependency_task_id must be another task in the same event.

---

## 5. Add Vendor Category — inside an event → Budget/Categories tab

**Category 1**
| Field | Value |
|---|---|
| Category Name | Catering |
| Suggested Percentage | 35 |
| Allocated Amount | 175000 |
| Notes | Buffet style for 150 guests. |
| Locked | unchecked |
| AI Priority | 1 |

**Category 2**
| Field | Value |
|---|---|
| Category Name | Photography & Videography |
| Suggested Percentage | 15 |
| Allocated Amount | 75000 |
| Notes | Full-day coverage + drone shots. |
| Locked | unchecked |
| AI Priority | 2 |

**Category 3**
| Field | Value |
|---|---|
| Category Name | Decor & Flowers |
| Suggested Percentage | 20 |
| Allocated Amount | 100000 |
| Notes | Stage, centerpieces, entrance arch. |

Rules: category_name required (max 150) · allocated_amount required numeric ≥ 0 · suggested_percentage optional 0–100.

---

## 6. Add Expense — inside an event → Expenses tab

*Requires a Vendor Category to already exist (use "Catering" / "Photography & Videography" from step 5).*

**Expense 1**
| Field | Value |
|---|---|
| Category | Catering |
| Vendor/Item Name | Royal Caterers Pvt Ltd |
| Estimated Cost | 170000 |
| Actual Cost | 165000 |
| Payment Status | Partially Paid |
| Date Logged | 2026-07-20 |
| Notes | 50% advance paid, balance due 1 week before event. |

**Expense 2**
| Field | Value |
|---|---|
| Category | Photography & Videography |
| Vendor/Item Name | FrameStory Studios |
| Estimated Cost | 75000 |
| Actual Cost | 75000 |
| Payment Status | Paid |
| Date Logged | 2026-07-18 |
| Notes | Full payment via bank transfer. |

Rules: category_id and event_id must reference existing rows · vendor_item_name required (max 255) · estimated_cost & actual_cost required numeric ≥ 0 · payment_status one of Paid/Pending/Partially Paid.

---

## 7. Trigger the AI Panic Button & Budget Rebalancer — Budget page

The Rebalancer Sandbox only appears once a category is over budget, and it's most useful to
test when there's a mix of locked / paid / open categories to rebalance across. Add these two
extra vendor categories first, then log the expense that pushes Catering into the red.

**Category 4** (stays open/unspent — used to absorb the cut)
| Field | Value |
|---|---|
| Category Name | Decor & Flowers |
| Suggested Percentage | 20 |
| Allocated Amount | 100000 |
| Notes | Stage, centerpieces, entrance arch. |
| Locked | unchecked |

*(Skip if you already added this as Category 3 in step 5.)*

**Category 5** (stays open/unspent — used to absorb the cut)
| Field | Value |
|---|---|
| Category Name | Entertainment |
| Suggested Percentage | 10 |
| Allocated Amount | 50000 |
| Notes | DJ + live sound system. |
| Locked | unchecked |

**Category 6** (manually locked — should stay untouched by every strategy)
| Field | Value |
|---|---|
| Category Name | Transport |
| Suggested Percentage | 5 |
| Allocated Amount | 25000 |
| Notes | Guest shuttle service. |
| Locked | checked |

**Expense 3** (this is the one that trips the Panic Button — pushes Catering to 185,000 spent against a 175,000 allocation)
| Field | Value |
|---|---|
| Category | Catering |
| Vendor/Item Name | Last-minute appetizer upgrade |
| Estimated Cost | 15000 |
| Actual Cost | 20000 |
| Payment Status | Pending |
| Date Logged | 2026-07-20 |
| Notes | Client requested extra live counters. |

**What you should see after saving Expense 3, on the Budget page (`/budget`):**

1. The "Budget Alerts" card shows a red **"Budget overrun detected. Rebalance remaining categories to stay on track?"** banner with a **Rebalance** button.
2. Click it to open the **Rebalancer Sandbox** modal. It should show:
   - Over Budget By: **PKR 10,000** (185,000 spent − 175,000 allocated on Catering)
   - Flexible Liquidity Left: sum of unspent headroom across Photography/Decor/Entertainment/Transport
3. With **Proportional** selected (default): the PKR 10,000 deficit should be spread across Decor & Flowers and Entertainment in proportion to their allocations — Photography is skipped (it has a `Paid` expense, so it's immutable) and Transport is skipped (manually locked).
4. Switch to **Targeted**: the full PKR 10,000 should come out of whichever open category has the most unspent headroom (Decor & Flowers, since it has the biggest allocation with nothing spent) — Entertainment should be untouched.
5. Switch to **AI-Suggested**: requires `AI_TASK_API_KEY` set in `.env` (same key used for AI task generation) and outbound internet access — if it's not configured you should see a clean error message, not a crash.
6. Check/uncheck the lock icon next to Decor & Flowers and confirm the chart + numbers recalculate to exclude it.
7. Click **Commit Changes** — the modal should show a success message and reload the page. Catering's allocation should now read 185,000 (no longer flagged over budget), and the amount should have been pulled from the categories you didn't lock.

---

## Quick checklist

- [ ] Register a user
- [ ] Log in
- [ ] Create 1 event
- [ ] Add 2–3 tasks (including one with a dependency, one in each phase)
- [ ] Add 2–3 vendor categories
- [ ] Add 2 expenses tied to those categories
- [ ] Check Dashboard, Budget, Progress, and Timeline pages reflect the entered data
- [ ] Check Activity log picked up the auto-logged actions (event created, etc.)
- [ ] Add the extra categories + Expense 3 from step 7 to trigger the Panic Button
- [ ] Open the Rebalancer Sandbox and try Proportional, Targeted, and AI-Suggested strategies
- [ ] Toggle a lock icon and confirm that category is excluded from the recalculation
- [ ] Commit the rebalance and confirm the Budget page updates (banner disappears, allocations change)
