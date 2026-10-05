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

    $db->exec("CREATE INDEX IF NOT EXISTS idx_transactions_reseller ON transactions(reseller_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_transactions_type ON transactions(type)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_logs_reseller ON reseller_logs(reseller_id)");
}
