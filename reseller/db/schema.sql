-- ============================================================================
-- Mikhmon V3 Reseller System - SQLite Database Schema
-- Complete schema for multi-level reseller management
-- ============================================================================

-- Enable WAL mode for better concurrent read/write performance
PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;
PRAGMA encoding = 'UTF-8';

-- ============================================================================
-- 1. RESELLERS - Core reseller accounts
-- ============================================================================
CREATE TABLE IF NOT EXISTS resellers (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    username        TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    password_hash   TEXT    NOT NULL,
    fullname        TEXT    NOT NULL DEFAULT '',
    email           TEXT    NOT NULL DEFAULT '' COLLATE NOCASE,
    phone           TEXT    NOT NULL DEFAULT '',
    company         TEXT    NOT NULL DEFAULT '',
    address         TEXT    NOT NULL DEFAULT '',
    -- Hierarchy: NULL = top-level (directly under admin)
    parent_id       INTEGER DEFAULT NULL,
    -- Level: 1 = direct reseller, 2 = sub-reseller, etc.
    level           INTEGER NOT NULL DEFAULT 1 CHECK (level BETWEEN 1 AND 5),
    -- Status: active, suspended, disabled
    status          TEXT    NOT NULL DEFAULT 'active' CHECK (status IN ('active','suspended','disabled')),
    -- Balance / deposit (stored as integer cents to avoid float issues)
    balance         INTEGER NOT NULL DEFAULT 0,
    -- Credit limit (0 = no credit allowed, >0 = can go negative up to this amount)
    credit_limit    INTEGER NOT NULL DEFAULT 0,
    -- Markup percentage on voucher prices (applied on top of admin base price)
    markup_pct      REAL    NOT NULL DEFAULT 0.0 CHECK (markup_pct >= 0),
    -- Discount percentage from admin base price
    discount_pct    REAL    NOT NULL DEFAULT 0.0 CHECK (discount_pct >= 0 AND discount_pct <= 100),
    -- Which MikroTik sessions this reseller can access (comma-separated, '*' = all)
    allowed_sessions TEXT   NOT NULL DEFAULT '*',
    -- Which hotspot profiles this reseller can sell (comma-separated, '*' = all)
    allowed_profiles TEXT   NOT NULL DEFAULT '*',
    -- Daily/monthly voucher generation limits (0 = unlimited)
    daily_limit     INTEGER NOT NULL DEFAULT 0,
    monthly_limit   INTEGER NOT NULL DEFAULT 0,
    -- Branding
    logo_path       TEXT    NOT NULL DEFAULT '',
    theme           TEXT    NOT NULL DEFAULT 'light',
    -- API access
    api_key         TEXT    UNIQUE DEFAULT NULL,
    api_enabled     INTEGER NOT NULL DEFAULT 0 CHECK (api_enabled IN (0,1)),
    -- Timestamps
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    updated_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    last_login      TEXT    DEFAULT NULL,
    -- Notes from admin
    notes           TEXT    NOT NULL DEFAULT '',

    FOREIGN KEY (parent_id) REFERENCES resellers(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_resellers_parent     ON resellers(parent_id);
CREATE INDEX IF NOT EXISTS idx_resellers_status      ON resellers(status);
CREATE INDEX IF NOT EXISTS idx_resellers_api_key     ON resellers(api_key);
CREATE INDEX IF NOT EXISTS idx_resellers_level       ON resellers(level);

-- ============================================================================
-- 2. RESELLER SESSIONS - Track login sessions
-- ============================================================================
CREATE TABLE IF NOT EXISTS reseller_sessions (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    reseller_id     INTEGER NOT NULL,
    session_token   TEXT    NOT NULL UNIQUE,
    ip_address      TEXT    NOT NULL DEFAULT '',
    user_agent      TEXT    NOT NULL DEFAULT '',
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    expires_at      TEXT    NOT NULL,
    is_active       INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),

    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_rsess_token      ON reseller_sessions(session_token);
CREATE INDEX IF NOT EXISTS idx_rsess_reseller   ON reseller_sessions(reseller_id);

-- ============================================================================
-- 3. VOUCHER PROFILES - Admin-defined pricing for hotspot profiles
-- ============================================================================
CREATE TABLE IF NOT EXISTS voucher_profiles (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    -- Links to MikroTik hotspot user profile name
    profile_name    TEXT    NOT NULL,
    -- Which router session this profile belongs to
    session_name    TEXT    NOT NULL,
    -- Display name shown to resellers
    display_name    TEXT    NOT NULL DEFAULT '',
    -- Base price (in currency smallest unit / cents)
    base_price      INTEGER NOT NULL DEFAULT 0 CHECK (base_price >= 0),
    -- Reseller buy price (what the reseller pays, overrides discount calc)
    reseller_price  INTEGER NOT NULL DEFAULT 0 CHECK (reseller_price >= 0),
    -- Suggested sell price (what end-customer pays)
    sell_price      INTEGER NOT NULL DEFAULT 0 CHECK (sell_price >= 0),
    -- Voucher validity / user profile details cached for display
    validity        TEXT    NOT NULL DEFAULT '',
    speed_limit     TEXT    NOT NULL DEFAULT '',
    data_limit      TEXT    NOT NULL DEFAULT '',
    time_limit      TEXT    NOT NULL DEFAULT '',
    shared_users    INTEGER NOT NULL DEFAULT 1,
    -- Status
    is_active       INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
    -- Sort order for display
    sort_order      INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    updated_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),

    UNIQUE(profile_name, session_name)
);

CREATE INDEX IF NOT EXISTS idx_vprofiles_session ON voucher_profiles(session_name);
CREATE INDEX IF NOT EXISTS idx_vprofiles_active  ON voucher_profiles(is_active);

-- ============================================================================
-- 4. RESELLER PROFILE PRICING - Per-reseller price overrides
-- ============================================================================
CREATE TABLE IF NOT EXISTS reseller_profile_pricing (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    reseller_id     INTEGER NOT NULL,
    profile_id      INTEGER NOT NULL,
    -- Override prices (0 = use default from voucher_profiles)
    buy_price       INTEGER NOT NULL DEFAULT 0,
    sell_price      INTEGER NOT NULL DEFAULT 0,
    -- Can this reseller sell this profile?
    is_allowed      INTEGER NOT NULL DEFAULT 1 CHECK (is_allowed IN (0,1)),
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    updated_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),

    UNIQUE(reseller_id, profile_id),
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
    FOREIGN KEY (profile_id) REFERENCES voucher_profiles(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_rpp_reseller ON reseller_profile_pricing(reseller_id);

-- ============================================================================
-- 5. BALANCE TRANSACTIONS - Full audit trail of all money movements
-- ============================================================================
CREATE TABLE IF NOT EXISTS balance_transactions (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    reseller_id     INTEGER NOT NULL,
    -- Transaction type
    type            TEXT    NOT NULL CHECK (type IN (
                        'deposit',      -- Admin adds balance
                        'withdrawal',   -- Admin removes balance
                        'purchase',     -- Voucher purchase (debit)
                        'refund',       -- Voucher refund (credit)
                        'transfer_in',  -- Transfer from another reseller
                        'transfer_out', -- Transfer to another reseller
                        'commission',   -- Commission earned from sub-reseller
                        'adjustment'    -- Manual admin adjustment
                    )),
    -- Positive = credit, Negative = debit
    amount          INTEGER NOT NULL,
    -- Balance AFTER this transaction
    balance_after   INTEGER NOT NULL,
    -- Reference to related entities
    reference_type  TEXT    DEFAULT NULL,  -- 'voucher_sale', 'transfer', etc.
    reference_id    INTEGER DEFAULT NULL,
    -- Who performed this action
    performed_by    TEXT    NOT NULL DEFAULT 'system', -- 'admin', reseller username, 'system'
    description     TEXT    NOT NULL DEFAULT '',
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),

    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_btx_reseller    ON balance_transactions(reseller_id);
CREATE INDEX IF NOT EXISTS idx_btx_type        ON balance_transactions(type);
CREATE INDEX IF NOT EXISTS idx_btx_created     ON balance_transactions(created_at);
CREATE INDEX IF NOT EXISTS idx_btx_reference   ON balance_transactions(reference_type, reference_id);

-- ============================================================================
-- 6. VOUCHER SALES - Every voucher sold by a reseller
-- ============================================================================
CREATE TABLE IF NOT EXISTS voucher_sales (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    reseller_id     INTEGER NOT NULL,
    profile_id      INTEGER NOT NULL,
    session_name    TEXT    NOT NULL,
    -- MikroTik hotspot user details
    hotspot_username TEXT   NOT NULL,
    hotspot_password TEXT   NOT NULL DEFAULT '',
    profile_name    TEXT    NOT NULL,
    -- Pricing at time of sale (immutable snapshot)
    buy_price       INTEGER NOT NULL DEFAULT 0,
    sell_price      INTEGER NOT NULL DEFAULT 0,
    profit          INTEGER NOT NULL DEFAULT 0,
    -- Quantity (for batch generation)
    quantity        INTEGER NOT NULL DEFAULT 1,
    -- Voucher details
    comment         TEXT    NOT NULL DEFAULT '',
    server          TEXT    NOT NULL DEFAULT 'all',
    -- Status: created, printed, sold, used, expired, refunded
    status          TEXT    NOT NULL DEFAULT 'created' CHECK (status IN (
                        'created','printed','sold','used','expired','refunded'
                    )),
    -- Customer info (optional, for reseller's own records)
    customer_name   TEXT    NOT NULL DEFAULT '',
    customer_phone  TEXT    NOT NULL DEFAULT '',
    -- Timestamps
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    sold_at         TEXT    DEFAULT NULL,
    printed_at      TEXT    DEFAULT NULL,

    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
    FOREIGN KEY (profile_id) REFERENCES voucher_profiles(id) ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_vsales_reseller   ON voucher_sales(reseller_id);
CREATE INDEX IF NOT EXISTS idx_vsales_profile    ON voucher_sales(profile_id);
CREATE INDEX IF NOT EXISTS idx_vsales_session    ON voucher_sales(session_name);
CREATE INDEX IF NOT EXISTS idx_vsales_status     ON voucher_sales(status);
CREATE INDEX IF NOT EXISTS idx_vsales_created    ON voucher_sales(created_at);
CREATE INDEX IF NOT EXISTS idx_vsales_username   ON voucher_sales(hotspot_username);

-- ============================================================================
-- 7. VOUCHER BATCHES - Group generated vouchers into batches
-- ============================================================================
CREATE TABLE IF NOT EXISTS voucher_batches (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    reseller_id     INTEGER NOT NULL,
    session_name    TEXT    NOT NULL,
    profile_name    TEXT    NOT NULL,
    quantity        INTEGER NOT NULL DEFAULT 1,
    prefix          TEXT    NOT NULL DEFAULT '',
    -- Total cost of the batch
    total_cost      INTEGER NOT NULL DEFAULT 0,
    -- Comment applied to all vouchers in batch
    comment         TEXT    NOT NULL DEFAULT '',
    status          TEXT    NOT NULL DEFAULT 'completed' CHECK (status IN ('pending','completed','failed','partial')),
    error_message   TEXT    NOT NULL DEFAULT '',
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),

    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_vbatch_reseller ON voucher_batches(reseller_id);
CREATE INDEX IF NOT EXISTS idx_vbatch_created  ON voucher_batches(created_at);

-- ============================================================================
-- 8. BATCH VOUCHER ITEMS - Links batches to individual sales
-- ============================================================================
CREATE TABLE IF NOT EXISTS batch_voucher_items (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_id        INTEGER NOT NULL,
    sale_id         INTEGER NOT NULL,

    FOREIGN KEY (batch_id) REFERENCES voucher_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (sale_id)  REFERENCES voucher_sales(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_bvi_batch ON batch_voucher_items(batch_id);

-- ============================================================================
-- 9. RESELLER COMMISSIONS - Track commissions in multi-level hierarchy
-- ============================================================================
CREATE TABLE IF NOT EXISTS reseller_commissions (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    -- The reseller earning the commission (parent/upline)
    beneficiary_id  INTEGER NOT NULL,
    -- The reseller whose sale triggered it (child/downline)
    source_reseller_id INTEGER NOT NULL,
    -- The sale that triggered this commission
    sale_id         INTEGER NOT NULL,
    -- Commission amount
    amount          INTEGER NOT NULL DEFAULT 0,
    -- Commission rate at time of calculation
    rate_pct        REAL    NOT NULL DEFAULT 0.0,
    -- Level depth (1 = direct child, 2 = grandchild, etc.)
    depth           INTEGER NOT NULL DEFAULT 1,
    -- Status
    status          TEXT    NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','paid','cancelled')),
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    paid_at         TEXT    DEFAULT NULL,

    FOREIGN KEY (beneficiary_id) REFERENCES resellers(id) ON DELETE CASCADE,
    FOREIGN KEY (source_reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
    FOREIGN KEY (sale_id) REFERENCES voucher_sales(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_rcomm_beneficiary ON reseller_commissions(beneficiary_id);
CREATE INDEX IF NOT EXISTS idx_rcomm_source      ON reseller_commissions(source_reseller_id);
CREATE INDEX IF NOT EXISTS idx_rcomm_status      ON reseller_commissions(status);

-- ============================================================================
-- 10. COMMISSION RULES - Configurable commission rates per level
-- ============================================================================
CREATE TABLE IF NOT EXISTS commission_rules (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    -- NULL = global rule, reseller_id = specific reseller override
    reseller_id     INTEGER DEFAULT NULL,
    -- Depth level this rule applies to
    depth           INTEGER NOT NULL DEFAULT 1 CHECK (depth BETWEEN 1 AND 5),
    -- Commission rate percentage
    rate_pct        REAL    NOT NULL DEFAULT 0.0 CHECK (rate_pct >= 0 AND rate_pct <= 100),
    -- Optional: only for specific profile
    profile_id      INTEGER DEFAULT NULL,
    is_active       INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),

    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
    FOREIGN KEY (profile_id) REFERENCES voucher_profiles(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_crules_reseller ON commission_rules(reseller_id);

-- ============================================================================
-- 11. RESELLER ACTIVITY LOG - Full audit trail
-- ============================================================================
CREATE TABLE IF NOT EXISTS reseller_activity_log (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    reseller_id     INTEGER DEFAULT NULL,
    action          TEXT    NOT NULL,  -- 'login','logout','generate_voucher','deposit',etc.
    entity_type     TEXT    NOT NULL DEFAULT '',  -- 'voucher','balance','profile',etc.
    entity_id       INTEGER DEFAULT NULL,
    details         TEXT    NOT NULL DEFAULT '',  -- JSON details
    ip_address      TEXT    NOT NULL DEFAULT '',
    performed_by    TEXT    NOT NULL DEFAULT 'system',
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),

    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_ralog_reseller ON reseller_activity_log(reseller_id);
CREATE INDEX IF NOT EXISTS idx_ralog_action   ON reseller_activity_log(action);
CREATE INDEX IF NOT EXISTS idx_ralog_created  ON reseller_activity_log(created_at);

-- ============================================================================
-- 12. RESELLER SETTINGS - Key-value settings per reseller
-- ============================================================================
CREATE TABLE IF NOT EXISTS reseller_settings (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    reseller_id     INTEGER NOT NULL,
    setting_key     TEXT    NOT NULL,
    setting_value   TEXT    NOT NULL DEFAULT '',
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    updated_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),

    UNIQUE(reseller_id, setting_key),
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
);

-- ============================================================================
-- 13. SYSTEM SETTINGS - Global reseller system configuration
-- ============================================================================
CREATE TABLE IF NOT EXISTS reseller_system_settings (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key     TEXT    NOT NULL UNIQUE,
    setting_value   TEXT    NOT NULL DEFAULT '',
    description     TEXT    NOT NULL DEFAULT '',
    updated_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);

-- Insert default system settings
INSERT OR IGNORE INTO reseller_system_settings (setting_key, setting_value, description) VALUES
    ('reseller_enabled',         '1',     'Enable/disable the reseller system globally'),
    ('max_reseller_levels',      '3',     'Maximum depth of reseller hierarchy'),
    ('default_daily_limit',      '100',   'Default daily voucher generation limit'),
    ('default_monthly_limit',    '3000',  'Default monthly voucher generation limit'),
    ('allow_self_registration',  '0',     'Allow resellers to self-register'),
    ('require_email_verify',     '0',     'Require email verification on registration'),
    ('default_commission_l1',    '5.0',   'Default Level-1 commission rate %'),
    ('default_commission_l2',    '2.0',   'Default Level-2 commission rate %'),
    ('default_commission_l3',    '1.0',   'Default Level-3 commission rate %'),
    ('min_deposit',              '10000', 'Minimum deposit amount (in smallest unit)'),
    ('currency_symbol',          'Rp',    'Currency symbol for display'),
    ('voucher_prefix_format',    '{RESELLER}_{DATE}_{SEQ}', 'Voucher naming pattern'),
    ('session_timeout_minutes',  '120',   'Reseller session timeout in minutes'),
    ('api_rate_limit_per_min',   '60',    'API requests per minute per reseller'),
    ('low_balance_threshold',    '50000', 'Low balance warning threshold');

-- ============================================================================
-- 14. RESELLER NOTIFICATIONS - In-app notification system
-- ============================================================================
CREATE TABLE IF NOT EXISTS reseller_notifications (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    reseller_id     INTEGER NOT NULL,
    title           TEXT    NOT NULL,
    message         TEXT    NOT NULL,
    type            TEXT    NOT NULL DEFAULT 'info' CHECK (type IN ('info','warning','success','danger')),
    is_read         INTEGER NOT NULL DEFAULT 0 CHECK (is_read IN (0,1)),
    link            TEXT    NOT NULL DEFAULT '',
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),

    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_rnotif_reseller ON reseller_notifications(reseller_id, is_read);

-- ============================================================================
-- 15. DEPOSIT REQUESTS - Resellers request balance top-up
-- ============================================================================
CREATE TABLE IF NOT EXISTS deposit_requests (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    reseller_id     INTEGER NOT NULL,
    amount          INTEGER NOT NULL CHECK (amount > 0),
    payment_method  TEXT    NOT NULL DEFAULT 'bank_transfer',
    payment_proof   TEXT    NOT NULL DEFAULT '', -- file path to uploaded proof
    bank_name       TEXT    NOT NULL DEFAULT '',
    account_number  TEXT    NOT NULL DEFAULT '',
    account_name    TEXT    NOT NULL DEFAULT '',
    reference_no    TEXT    NOT NULL DEFAULT '',
    -- Status: pending, approved, rejected, cancelled
    status          TEXT    NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','approved','rejected','cancelled')),
    admin_notes     TEXT    NOT NULL DEFAULT '',
    processed_by    TEXT    NOT NULL DEFAULT '',
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    processed_at    TEXT    DEFAULT NULL,

    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_depreq_reseller ON deposit_requests(reseller_id);
CREATE INDEX IF NOT EXISTS idx_depreq_status   ON deposit_requests(status);

-- ============================================================================
-- VIEWS - Convenience views for reporting
-- ============================================================================

-- Reseller hierarchy tree view
CREATE VIEW IF NOT EXISTS v_reseller_tree AS
WITH RECURSIVE tree AS (
    SELECT id, username, fullname, parent_id, level, balance, status,
           username AS path, 0 AS depth
    FROM resellers WHERE parent_id IS NULL
    UNION ALL
    SELECT r.id, r.username, r.fullname, r.parent_id, r.level, r.balance, r.status,
           tree.path || ' > ' || r.username, tree.depth + 1
    FROM resellers r INNER JOIN tree ON r.parent_id = tree.id
)
SELECT * FROM tree ORDER BY path;

-- Daily sales summary per reseller
CREATE VIEW IF NOT EXISTS v_daily_sales_summary AS
SELECT
    vs.reseller_id,
    r.username AS reseller_name,
    date(vs.created_at) AS sale_date,
    vs.session_name,
    vs.profile_name,
    COUNT(*) AS voucher_count,
    SUM(vs.buy_price) AS total_cost,
    SUM(vs.sell_price) AS total_revenue,
    SUM(vs.profit) AS total_profit
FROM voucher_sales vs
JOIN resellers r ON r.id = vs.reseller_id
GROUP BY vs.reseller_id, date(vs.created_at), vs.session_name, vs.profile_name;

-- Monthly sales summary per reseller
CREATE VIEW IF NOT EXISTS v_monthly_sales_summary AS
SELECT
    vs.reseller_id,
    r.username AS reseller_name,
    strftime('%Y-%m', vs.created_at) AS sale_month,
    COUNT(*) AS voucher_count,
    SUM(vs.buy_price) AS total_cost,
    SUM(vs.sell_price) AS total_revenue,
    SUM(vs.profit) AS total_profit
FROM voucher_sales vs
JOIN resellers r ON r.id = vs.reseller_id
GROUP BY vs.reseller_id, strftime('%Y-%m', vs.created_at);

-- Pending commissions view
CREATE VIEW IF NOT EXISTS v_pending_commissions AS
SELECT
    rc.beneficiary_id,
    r.username AS beneficiary_name,
    COUNT(*) AS pending_count,
    SUM(rc.amount) AS pending_total
FROM reseller_commissions rc
JOIN resellers r ON r.id = rc.beneficiary_id
WHERE rc.status = 'pending'
GROUP BY rc.beneficiary_id;

-- Reseller balance overview
CREATE VIEW IF NOT EXISTS v_reseller_balance_overview AS
SELECT
    r.id,
    r.username,
    r.fullname,
    r.balance,
    r.credit_limit,
    r.status,
    r.level,
    COALESCE(p.username, 'admin') AS parent_name,
    (SELECT COUNT(*) FROM voucher_sales vs WHERE vs.reseller_id = r.id
        AND date(vs.created_at) = date('now','localtime')) AS today_sales,
    (SELECT COUNT(*) FROM voucher_sales vs WHERE vs.reseller_id = r.id
        AND strftime('%Y-%m', vs.created_at) = strftime('%Y-%m', 'now','localtime')) AS month_sales
FROM resellers r
LEFT JOIN resellers p ON p.id = r.parent_id;

-- ============================================================================
-- TRIGGERS - Automatic housekeeping
-- ============================================================================

-- Auto-update updated_at on resellers
CREATE TRIGGER IF NOT EXISTS trg_resellers_updated_at
AFTER UPDATE ON resellers
FOR EACH ROW
BEGIN
    UPDATE resellers SET updated_at = datetime('now','localtime') WHERE id = NEW.id;
END;

-- Auto-update updated_at on voucher_profiles
CREATE TRIGGER IF NOT EXISTS trg_vprofiles_updated_at
AFTER UPDATE ON voucher_profiles
FOR EACH ROW
BEGIN
    UPDATE voucher_profiles SET updated_at = datetime('now','localtime') WHERE id = NEW.id;
END;
