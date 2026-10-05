<?php
/**
 * Mikhmon V3 Reseller System - Database Connection & Initialization
 * 
 * Handles SQLite database creation, connection, and migration.
 * Uses PDO with WAL mode for concurrent access.
 */

if (substr($_SERVER["REQUEST_URI"], -14) == "database.php") {
    header("Location:../");
    exit;
}

class ResellerDB
{
    /** @var PDO */
    private static $instance = null;

    /** @var string */
    private static $dbPath = null;

    /**
     * Get the singleton PDO connection.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::$dbPath = self::getDbPath();
            $isNew = !file_exists(self::$dbPath);

            self::$instance = new PDO(
                'sqlite:' . self::$dbPath,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );

            // SQLite pragmas for performance + safety
            self::$instance->exec("PRAGMA journal_mode = WAL");
            self::$instance->exec("PRAGMA foreign_keys = ON");
            self::$instance->exec("PRAGMA busy_timeout = 5000");
            self::$instance->exec("PRAGMA synchronous = NORMAL");
            self::$instance->exec("PRAGMA cache_size = -8000"); // 8MB cache
            self::$instance->exec("PRAGMA temp_store = MEMORY");

            if ($isNew) {
                self::initializeSchema();
            }
        }

        return self::$instance;
    }

    /**
     * Get the database file path.
     */
    private static function getDbPath(): string
    {
        $dir = dirname(__DIR__) . '/db';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/reseller.db';
    }

    /**
     * Initialize the database with the schema.
     */
    private static function initializeSchema(): void
    {
        $schemaFile = dirname(__DIR__) . '/db/schema.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            self::$instance->exec($sql);
        }
    }

    /**
     * Run a query and return all results.
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Run a query and return a single row.
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Run a query and return a single scalar value.
     */
    public static function fetchValue(string $sql, array $params = [])
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Execute a statement (INSERT, UPDATE, DELETE) and return affected rows.
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Insert a row and return the last insert ID.
     */
    public static function insert(string $sql, array $params = []): int
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return (int) self::getConnection()->lastInsertId();
    }

    /**
     * Begin a transaction.
     */
    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    /**
     * Commit the current transaction.
     */
    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    /**
     * Roll back the current transaction.
     */
    public static function rollBack(): bool
    {
        return self::getConnection()->rollBack();
    }

    /**
     * Check if a transaction is active.
     */
    public static function inTransaction(): bool
    {
        return self::getConnection()->inTransaction();
    }

    /**
     * Helper: build an INSERT statement from an associative array.
     */
    public static function insertArray(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        return self::insert($sql, array_values($data));
    }

    /**
     * Helper: build an UPDATE statement from an associative array.
     */
    public static function updateArray(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = [];
        $values = [];
        foreach ($data as $col => $val) {
            $setParts[] = "{$col} = ?";
            $values[]   = $val;
        }
        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";
        return self::execute($sql, array_merge($values, $whereParams));
    }

    /**
     * Close the connection.
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
