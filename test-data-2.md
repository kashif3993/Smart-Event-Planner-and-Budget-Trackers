# Manual Test Data (Set 2)

Another sample dataset for each module/form, matching the exact validation rules in the code. Enter these **in order** — Tasks, Vendor Categories, and Expenses all need an Event to already exist, and Expenses need a Vendor Category to already exist.

---

## 1. Register — `/register`

| Field | Value |
|---|---|
| Full Name | Hassan Raza |
| Email | hassan.raza@example.com |
| Password | Corporate123 |
| Confirm Password | Corporate123 |
| Profile Image | (optional, leave blank) |
| Terms & Conditions | checked |

Rules: full_name required (max 100) · email required, valid, unique · password required min 8 chars + must match confirmation · terms must be accepted.

---

## 2. Login — `/login`

| Field | Value |
|---|---|
| Email | hassan.raza@example.com |
| Password | Corporate123 |

---

## 3. Create Event — Events page → "New Event"

| Field | Value |
|---|---|
| Event Name | TechNova Annual Conference 2026 |
| Event Type | Corporate Event |
| Custom Event Type | (leave blank — only required if Event Type = Custom) |
| Event Date | 2026-10-05 |
| Event Time | 09:00 |
| Confirmed Guests | 300 |
| Guest Capacity | 350 |
| Venue Name | Expo Centre Karachi |
| Location | Karachi, Pakistan |
| Venue Image | (optional) |
| Total Budget | 1200000 |
| Budget Spent | 0 |
| Currency | PKR |
| Description | Two-day tech conference with keynote speakers, workshops, and an exhibition hall. |

Rules: event_name required (max 200) · event_type required (Wedding/Birthday Party/Corporate Event/Baby Shower/Graduation/Custom) · event_date required valid date · event_time optional `HH:MM` · currency required (PKR or USD).

> Note: event creation no longer auto-generates AI tasks (removed for speed) — it only creates the event + suggested vendor categories. Use the "Generate with AI" button on the event page if you want AI tasks, or add tasks manually below.

---

## 4. Add Task — inside an event → Tasks tab

**Task 1**
| Field | Value |
|---|---|
| Task Name | Book keynote speakers |
| Phase | Pre-Planning |
| Due Date | 2026-08-10 |
| Priority | High |
| Depends On | (leave blank) |
| Notes | Confirm 3 speakers and their travel arrangements. |

**Task 2** (tests the dependency field)
| Field | Value |
|---|---|
| Task Name | Send sponsor invitations |
| Phase | Preparation |
| Due Date | 2026-09-01 |
| Priority | Medium |
| Depends On | Book keynote speakers |
| Notes | Reach out to 10 potential sponsors. |

**Task 3**
| Field | Value |
|---|---|
| Task Name | Set up registration desk |
| Phase | Day-Of |
| Due Date | 2026-10-05 |
| Priority | High |
| Notes | Arrive 2 hours early to test badge printers. |

Rules: task_name required (max 255) · phase required (Pre-Planning/Preparation/Day-Of) · priority required (Low/Medium/High) · dependency_task_id must be another task in the same event.

---

## 5. Add Vendor Category — inside an event → Budget/Categories tab

**Category 1**
| Field | Value |
|---|---|
| Category Name | Venue Rental |
| Suggested Percentage | 30 |
| Allocated Amount | 360000 |
| Notes | Two-day hall booking including AV setup. |
| Locked | unchecked |
| AI Priority | 1 |

**Category 2**
| Field | Value |
|---|---|
| Category Name | Catering |
| Suggested Percentage | 25 |
| Allocated Amount | 300000 |
| Notes | Lunch + tea breaks for 300 guests, both days. |
| Locked | unchecked |
| AI Priority | 2 |

**Category 3**
| Field | Value |
|---|---|
| Category Name | Marketing & Signage |
| Suggested Percentage | 10 |
| Allocated Amount | 120000 |
| Notes | Banners, digital ads, printed programs. |

Rules: category_name required (max 150) · allocated_amount required numeric ≥ 0 · suggested_percentage optional 0–100.

---

## 6. Add Expense — inside an event → Expenses tab

*Requires a Vendor Category to already exist (use "Venue Rental" / "Catering" from step 5).*

**Expense 1**
| Field | Value |
|---|---|
| Category | Venue Rental |
| Vendor/Item Name | Expo Centre Karachi Booking |
| Estimated Cost | 350000 |
| Actual Cost | 360000 |
| Payment Status | Paid |
| Date Logged | 2026-07-20 |
| Notes | Full payment made in advance to secure the hall. |

**Expense 2**
| Field | Value |
|---|---|
| Category | Catering |
| Vendor/Item Name | Metro Catering Services |
| Estimated Cost | 300000 |
| Actual Cost | 280000 |
| Payment Status | Partially Paid |
| Date Logged | 2026-07-19 |
| Notes | 40% advance paid, balance due after event. |

Rules: category_id and event_id must reference existing rows · vendor_item_name required (max 255) · estimated_cost & actual_cost required numeric ≥ 0 · payment_status one of Paid/Pending/Partially Paid.

---

## 7. Trigger the AI Panic Button & Budget Rebalancer — Budget page

The Rebalancer Sandbox only appears once a category is over budget, and it's most useful to
test when there's a mix of locked / paid / open categories to rebalance across. Add these two
extra vendor categories first, then log the expense that pushes Venue Rental into the red.

**Category 4** (stays open/unspent — used to absorb the cut)
| Field | Value |
|---|---|
| Category Name | Marketing & Signage |
| Suggested Percentage | 10 |
| Allocated Amount | 120000 |
| Notes | Banners, digital ads, printed programs. |
| Locked | unchecked |

*(Skip if you already added this as Category 3 in step 5.)*

**Category 5** (stays open/unspent — used to absorb the cut)
| Field | Value |
|---|---|
| Category Name | Speaker Gifts & Swag |
| Suggested Percentage | 8 |
| Allocated Amount | 96000 |
| Notes | Gift hampers and branded merchandise for attendees. |
| Locked | unchecked |

**Category 6** (manually locked — should stay untouched by every strategy)
| Field | Value |
|---|---|
| Category Name | Security & Logistics |
| Suggested Percentage | 6 |
| Allocated Amount | 72000 |
| Notes | Event security staff and crowd control. |
| Locked | checked |

**Expense 3** (this is the one that trips the Panic Button — pushes Venue Rental to 375,000 spent against a 360,000 allocation)
| Field | Value |
|---|---|
| Category | Venue Rental |
| Vendor/Item Name | Last-minute stage extension |
| Estimated Cost | 10000 |
| Actual Cost | 15000 |
| Payment Status | Pending |
| Date Logged | 2026-07-20 |
| Notes | Extra platform space requested by AV vendor. |

**What you should see after saving Expense 3, on the Budget page (`/budget`):**

1. The "Budget Alerts" card shows a red **"Budget overrun detected. Rebalance remaining categories to stay on track?"** banner with a **Rebalance** button.
2. Click it to open the **Rebalancer Sandbox** modal. It should show:
   - Over Budget By: **PKR 15,000** (375,000 spent − 360,000 allocated on Venue Rental)
   - Flexible Liquidity Left: sum of unspent headroom across Catering/Marketing/Speaker Gifts/Security
3. With **Proportional** selected (default): the PKR 15,000 deficit should be spread across Marketing & Signage and Speaker Gifts & Swag in proportion to their allocations — Catering is skipped (it has a `Partially Paid` expense reducing flexibility, verify against actual app behavior) and Security & Logistics is skipped (manually locked).
4. Switch to **Targeted**: the full PKR 15,000 should come out of whichever open category has the most unspent headroom (Marketing & Signage, since it has the biggest untouched allocation) — Speaker Gifts & Swag should be untouched.
5. Switch to **AI-Suggested**: requires `AI_TASK_API_KEY` set in `.env` (same key used for AI task generation) and outbound internet access — if it's not configured you should see a clean error message, not a crash.
6. Check/uncheck the lock icon next to Marketing & Signage and confirm the chart + numbers recalculate to exclude it.
7. Click **Commit Changes** — the modal should show a success message and reload the page. Venue Rental's allocation should now read 375,000 (no longer flagged over budget), and the amount should have been pulled from the categories you didn't lock.

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
