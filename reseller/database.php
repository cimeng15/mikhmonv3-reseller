<?php
/**
 * Mikhmon V3 - Reseller Database
 * SQLite database initialization and helper functions
 */

function getDB() {
    static $db = null;
    if ($db === null) {
        $dbPath = __DIR__ . '/../data/mikhmon_reseller.db';
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA foreign_keys=ON');
        initDatabase($db);
    }
    return $db;
}

function initDatabase($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS resellers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            name TEXT NOT NULL,
            phone TEXT DEFAULT '',
            balance REAL DEFAULT 0,
            discount REAL DEFAULT 0,
            status TEXT DEFAULT 'active',
            allowed_sessions TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reseller_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            amount REAL NOT NULL,
            balance_before REAL DEFAULT 0,
            balance_after REAL DEFAULT 0,
            description TEXT DEFAULT '',
            voucher_data TEXT DEFAULT '',
            session_name TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS reseller_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reseller_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            detail TEXT DEFAULT '',
            ip_address TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS reseller_vouchers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reseller_id INTEGER NOT NULL,
            transaction_id INTEGER,
            session_name TEXT NOT NULL,
            username TEXT NOT NULL,
            password TEXT DEFAULT '',
            profile TEXT DEFAULT '',
            comment TEXT DEFAULT '',
            router_id TEXT DEFAULT '',
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
            FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
            UNIQUE (session_name, username)
        )
    ");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_vouchers_reseller ON reseller_vouchers(reseller_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_vouchers_session ON reseller_vouchers(session_name)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_vouchers_created ON reseller_vouchers(created_at)");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_transactions_reseller ON transactions(reseller_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_transactions_type ON transactions(type)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_reseller ON reseller_logs(reseller_id)");
}
