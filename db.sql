-- ==========================================
-- SMART EVENT PLANNER + BUDGET TRACKER
-- + AI "PANIC BUTTON" & DYNAMIC BUDGET REBALANCER
-- Complete Combined Database Schema
-- ==========================================
-- This merges your existing baseline schema with the new tables/columns
-- required by the Panic Button PRD (v1.0). Naming conventions, id types
-- (INT AUTO_INCREMENT) and DECIMAL precisions match your original file
-- exactly, so this is a drop-in replacement for it.
--
-- Changes vs your original file are marked with "-- NEW:" comments.
-- ==========================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS budget_rebalance_audit_log;
DROP TABLE IF EXISTS rebalance_session_categories;
DROP TABLE IF EXISTS rebalance_sessions;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS vendor_categories;
DROP TABLE IF EXISTS timeline_milestones;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS ai_templates;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================
-- USERS
-- ==========================================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    profile_image VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==========================================
-- EVENTS
-- ==========================================

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,

    event_name VARCHAR(200) NOT NULL,

    event_type ENUM(
        'Wedding',
        'Birthday Party',
        'Corporate Event',
        'Baby Shower',
        'Graduation',
        'Custom'
    ) NOT NULL,

    custom_event_type VARCHAR(100) NULL,

    event_date DATE NOT NULL,

    guest_count INT DEFAULT 0,

    venue_name VARCHAR(255) NULL,

    location VARCHAR(255) NULL,

    total_budget DECIMAL(12,2) DEFAULT 0,

    currency ENUM('PKR','USD') DEFAULT 'PKR',

    description TEXT NULL,

    status ENUM(
        'Planning',
        'In Progress',
        'Completed',
        'Cancelled'
    ) DEFAULT 'Planning',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_events_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE
);

-- ==========================================
-- TASKS
-- ==========================================

CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,

    event_id INT NOT NULL,

    task_name VARCHAR(255) NOT NULL,

    phase ENUM(
        'Pre-Planning',
        'Preparation',
        'Day-Of'
    ) NOT NULL,

    due_date DATE NULL,

    priority ENUM(
        'Low',
        'Medium',
        'High'
    ) DEFAULT 'Medium',

    dependency_task_id INT NULL,

    source ENUM(
        'AI',
        'Manual'
    ) DEFAULT 'AI',

    status ENUM(
        'Pending',
        'Completed',
        'Skipped'
    ) DEFAULT 'Pending',

    notes TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_tasks_event
    FOREIGN KEY (event_id)
    REFERENCES events(id)
    ON DELETE CASCADE,

    CONSTRAINT fk_tasks_dependency
    FOREIGN KEY (dependency_task_id)
    REFERENCES tasks(id)
    ON DELETE SET NULL
);

-- ==========================================
-- VENDOR CATEGORIES
-- NEW: is_locked + ai_slash_priority added for the Rebalancer Sandbox
-- (3.1 "Lock" Category Toggles / 3.2 AI-Optimized Strategy)
-- ==========================================

CREATE TABLE vendor_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,

    event_id INT NOT NULL,

    category_name VARCHAR(150) NOT NULL,

    suggested_percentage DECIMAL(5,2) DEFAULT 0,

    allocated_amount DECIMAL(12,2) DEFAULT 0,

    notes TEXT NULL,

    -- NEW: user-controlled lock icon next to a category in the sandbox.
    -- When TRUE, allocated_amount is immutable during rebalance recalculation.
    is_locked BOOLEAN NOT NULL DEFAULT FALSE,

    -- NEW: lower number = slashed first by the AI-Optimized strategy
    -- (e.g. Decor/Entertainment = 1, Catering/Venue = 5). NULL = no preference set.
    ai_slash_priority TINYINT UNSIGNED NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_category_event
    FOREIGN KEY (event_id)
    REFERENCES events(id)
    ON DELETE CASCADE
);

-- ==========================================
-- EXPENSES
-- (unchanged from your original - actual_cost and payment_status
-- already live here, at the line-item level, per your existing design)
-- ==========================================

CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,

    event_id INT NOT NULL,

    category_id INT NOT NULL,

    vendor_item_name VARCHAR(255) NOT NULL,

    estimated_cost DECIMAL(12,2) DEFAULT 0,

    actual_cost DECIMAL(12,2) DEFAULT 0,

    payment_status ENUM(
        'Paid',
        'Pending',
        'Partially Paid'
    ) DEFAULT 'Pending',

    date_logged DATE NULL,

    notes TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_expense_event
    FOREIGN KEY (event_id)
    REFERENCES events(id)
    ON DELETE CASCADE,

    CONSTRAINT fk_expense_category
    FOREIGN KEY (category_id)
    REFERENCES vendor_categories(id)
    ON DELETE CASCADE
);

-- ==========================================
-- TIMELINE MILESTONES
-- ==========================================

CREATE TABLE timeline_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,

    event_id INT NOT NULL,

    title VARCHAR(255) NOT NULL,

    description TEXT NULL,

    milestone_date DATE NOT NULL,

    status ENUM(
        'Pending',
        'Completed'
    ) DEFAULT 'Pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_timeline_event
    FOREIGN KEY (event_id)
    REFERENCES events(id)
    ON DELETE CASCADE
);

-- ==========================================
-- AI TASK TEMPLATES
-- ==========================================

CREATE TABLE ai_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,

    event_type VARCHAR(100) NOT NULL,

    task_name VARCHAR(255) NOT NULL,

    phase ENUM(
        'Pre-Planning',
        'Preparation',
        'Day-Of'
    ) NOT NULL,

    days_before_event INT NOT NULL,

    priority ENUM(
        'Low',
        'Medium',
        'High'
    ) DEFAULT 'Medium',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- ACTIVITY LOGS
-- ==========================================

CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    event_id INT NULL,

    action VARCHAR(255) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_log_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE,

    CONSTRAINT fk_log_event
    FOREIGN KEY (event_id)
    REFERENCES events(id)
    ON DELETE SET NULL
);

-- ==========================================
-- NEW: REBALANCE SESSIONS
-- One row per time a user opens the "Rebalancer Sandbox" (3.1).
-- Nothing here touches vendor_categories/expenses until committed -
-- this is the non-destructive simulation layer the PRD requires.
-- ==========================================

CREATE TABLE rebalance_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,

    event_id INT NOT NULL,

    -- the category that went over budget and fired the Panic Button (2.1 The Trigger)
    triggered_by_category_id INT NOT NULL,

    strategy ENUM(
        'Proportional',
        'Targeted',
        'AI-Optimized'
    ) DEFAULT 'Proportional',

    -- Total Deficit = SUM(actual_cost - allocated_amount) across over-budget categories (3.1)
    deficit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,

    -- total unspent budget across mutable categories, available to absorb the deficit
    flexible_liquidity_amount DECIMAL(12,2) NOT NULL DEFAULT 0,

    status ENUM(
        'Sandbox',
        'Committed',
        'Discarded'
    ) DEFAULT 'Sandbox',

    -- event_type, guest_count, and category spending sent to the LLM for AI-Optimized runs (3.2)
    ai_request_payload JSON NULL,

    -- raw optimized percentages returned by the LLM
    ai_response_payload JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    committed_at TIMESTAMP NULL,

    CONSTRAINT fk_rebalance_session_event
    FOREIGN KEY (event_id)
    REFERENCES events(id)
    ON DELETE CASCADE,

    CONSTRAINT fk_rebalance_session_trigger_category
    FOREIGN KEY (triggered_by_category_id)
    REFERENCES vendor_categories(id)
    ON DELETE CASCADE
);

-- ==========================================
-- NEW: REBALANCE SESSION CATEGORIES
-- Line items behind the Interactive Comparison Chart (3.1):
-- current vs. proposed allocation for every category considered
-- in a given sandbox session.
-- ==========================================

CREATE TABLE rebalance_session_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,

    rebalance_session_id INT NOT NULL,

    vendor_category_id INT NOT NULL,

    -- snapshot of the Lock Toggle state at simulation time
    is_locked_in_session BOOLEAN NOT NULL DEFAULT FALSE,

    -- FALSE if the category had any 'Paid' expense OR was locked in this session (3.2)
    is_mutable BOOLEAN NOT NULL DEFAULT TRUE,

    original_allocated_amount DECIMAL(12,2) NOT NULL DEFAULT 0,

    original_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,

    -- maps to 'suggested_budget_percentage' / 'allocated_amount' in the
    -- POST /rebalance-preview response (4.2)
    proposed_allocated_amount DECIMAL(12,2) NOT NULL DEFAULT 0,

    proposed_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_rsc_session
    FOREIGN KEY (rebalance_session_id)
    REFERENCES rebalance_sessions(id)
    ON DELETE CASCADE,

    CONSTRAINT fk_rsc_category
    FOREIGN KEY (vendor_category_id)
    REFERENCES vendor_categories(id)
    ON DELETE CASCADE,

    CONSTRAINT uq_session_category UNIQUE (rebalance_session_id, vendor_category_id)
);

-- ==========================================
-- NEW: BUDGET REBALANCE AUDIT LOG
-- Permanent record written only when POST /rebalance-commit succeeds.
-- Never alters expenses/actual_cost, per the PRD's Out-of-Scope rule
-- that historical logged costs are never touched - only future
-- allocated_amount changes.
-- ==========================================

CREATE TABLE budget_rebalance_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,

    event_id INT NOT NULL,

    rebalance_session_id INT NOT NULL,

    vendor_category_id INT NOT NULL,

    previous_allocated_amount DECIMAL(12,2) NOT NULL,

    new_allocated_amount DECIMAL(12,2) NOT NULL,

    strategy_applied ENUM(
        'Proportional',
        'Targeted',
        'AI-Optimized'
    ) NOT NULL,

    changed_by_user_id INT NULL,

    committed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_bral_event
    FOREIGN KEY (event_id)
    REFERENCES events(id)
    ON DELETE CASCADE,

    CONSTRAINT fk_bral_session
    FOREIGN KEY (rebalance_session_id)
    REFERENCES rebalance_sessions(id)
    ON DELETE CASCADE,

    CONSTRAINT fk_bral_category
    FOREIGN KEY (vendor_category_id)
    REFERENCES vendor_categories(id)
    ON DELETE CASCADE,

    CONSTRAINT fk_bral_user
    FOREIGN KEY (changed_by_user_id)
    REFERENCES users(id)
    ON DELETE SET NULL
);

-- ==========================================
-- NEW: BUDGET TRACKER DASHBOARD VIEW (3.6)
-- Aggregates expenses per category (since actual_cost/payment_status
-- live at the expense line-item level in your schema) and computes the
-- over-budget flag that fires the Panic Button banner (2.2 The Alert).
-- ==========================================

CREATE OR REPLACE VIEW vw_budget_tracker_dashboard AS
SELECT
    vc.id                                   AS vendor_category_id,
    vc.event_id,
    vc.category_name,
    vc.allocated_amount,
    vc.suggested_percentage,
    vc.is_locked,
    COALESCE(SUM(ex.actual_cost), 0)                                   AS actual_cost,
    (vc.allocated_amount - COALESCE(SUM(ex.actual_cost), 0))           AS remaining_amount,
    (COALESCE(SUM(ex.actual_cost), 0) > vc.allocated_amount)           AS is_over_budget,
    GREATEST(COALESCE(SUM(ex.actual_cost), 0) - vc.allocated_amount, 0) AS overrun_amount,
    -- a category is treated as immutable if it's locked OR has at least one
    -- fully paid expense (money already paid out can't be clawed back)
    (
        vc.is_locked = FALSE
        AND SUM(CASE WHEN ex.payment_status = 'Paid' THEN 1 ELSE 0 END) = 0
    )                                                                   AS is_mutable
FROM vendor_categories vc
LEFT JOIN expenses ex ON ex.category_id = vc.id
GROUP BY vc.id, vc.event_id, vc.category_name, vc.allocated_amount,
         vc.suggested_percentage, vc.is_locked;

-- ==========================================
-- NEW: EVENT BUDGET HEALTH VIEW
-- Backs the "Overrun Analytics Banner" (3.1): total deficit and total
-- flexible liquidity across all mutable categories for an event.
-- ==========================================

CREATE OR REPLACE VIEW vw_event_budget_health AS
SELECT
    e.id                                                             AS event_id,
    e.event_name,
    e.total_budget,
    e.currency,
    SUM(bt.actual_cost)                                              AS total_actual_cost,
    SUM(bt.overrun_amount)                                           AS total_deficit,
    SUM(CASE WHEN bt.is_mutable THEN GREATEST(bt.remaining_amount, 0) ELSE 0 END) AS total_flexible_liquidity
FROM events e
JOIN vw_budget_tracker_dashboard bt ON bt.event_id = e.id
GROUP BY e.id, e.event_name, e.total_budget, e.currency;

-- ==========================================
-- SAMPLE AI TEMPLATES
-- ==========================================

INSERT INTO ai_templates
(event_type, task_name, phase, days_before_event, priority)
VALUES

('Wedding','Book Venue','Pre-Planning',90,'High'),
('Wedding','Hire Photographer','Preparation',60,'High'),
('Wedding','Send Invitations','Preparation',45,'Medium'),
('Wedding','Finalize Catering','Preparation',15,'High'),
('Wedding','Coordinate Vendors','Day-Of',1,'High'),

('Birthday Party','Book Venue','Pre-Planning',30,'High'),
('Birthday Party','Order Cake','Preparation',7,'Medium'),
('Birthday Party','Decorations Setup','Day-Of',1,'High'),

('Corporate Event','Book Conference Hall','Pre-Planning',60,'High'),
('Corporate Event','Invite Guests','Preparation',30,'Medium'),
('Corporate Event','Prepare Presentation','Preparation',7,'High'),

('Baby Shower','Select Theme','Pre-Planning',30,'Medium'),
('Baby Shower','Order Decorations','Preparation',10,'Medium'),

('Graduation','Book Venue','Pre-Planning',45,'High'),
('Graduation','Invite Guests','Preparation',20,'Medium');
