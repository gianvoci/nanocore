<?php

declare(strict_types=1);

/**
 * NanoORM - A lightweight ORM for database operations
 *
 * Provides simple database table management with:
 * - Magic getters/setters for field access
 * - CRUD operations (save, update, delete)
 * - Query methods (findById, findBy)
 * - Table joins support
 *
 * @author Giancarlo Voci
 * @since 2026-02-11
 */

namespace NanoCore;

class NanoORM
{
    protected ?\PDO $pdo = null;
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fields = [];
    protected array $data = [];
    protected array $joins = [];
    protected bool $isNew = true;

    /**
     * Constructor
     *
     * @param \PDO $pdo PDO connection instance
     * @param string $table Table name
     * @param string $primaryKey Primary key field name (default: 'id')
     */
    public function __construct(\PDO $pdo, string $table, string $primaryKey = 'id')
    {
        $this->validateIdentifier($table, 'table name');
        $this->validateIdentifier($primaryKey, 'primary key');

        $this->pdo = $pdo;
        $this->table = $table;
        $this->primaryKey = $primaryKey;
        $this->loadTableSchema();
    }

    /**
     * Validate that a SQL identifier (table name, primary key) contains only safe characters.
     *
     * @param string $identifier The identifier to validate
     * @param string $context Description of what is being validated (for error messages)
     * @throws \InvalidArgumentException if the identifier is invalid
     */
    private function validateIdentifier(string $identifier, string $context): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("Invalid {$context}: '{$identifier}'");
        }
    }

    /**
     * Load table schema to identify fields
     */
    protected function loadTableSchema(): void
    {
        try {
            // Table name validated in constructor via validateIdentifier()
            $stmt = $this->pdo->query("DESCRIBE `{$this->table}`");
            $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($columns as $column) {
                $this->fields[] = $column['Field'];
            }
        } catch (\Exception $e) {
            // Fallback for SQLite or other databases
            try {
            // Table name validated in constructor via validateIdentifier()
            $stmt = $this->pdo->query("PRAGMA table_info({$this->table})");
                $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($columns as $column) {
                    $this->fields[] = $column['name'];
                }
            } catch (\Exception $e2) {
                throw new \Exception("Unable to load schema for table '{$this->table}'. MySQL: {$e->getMessage()}. SQLite: {$e2->getMessage()}");
            }
        }
    }

    /**
     * Magic getter for field access
     *
     * @param string $name Field name
     * @return mixed Field value
     */
    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Magic setter for field modification
     *
     * @param string $name Field name
     * @param mixed $value Field value
     */
    public function __set(string $name, mixed $value): void
    {
        if (!in_array($name, $this->fields) && $name !== $this->primaryKey) {
            throw new \InvalidArgumentException("Unknown field: {$name}");
        }
        $this->data[$name] = $value;
    }

    /**
     * Magic isset for field checking
     *
     * @param string $name Field name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Magic unset for field removal
     *
     * @param string $name Field name
     */
    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    /**
     * Set data from array
     *
     * @param array $data Associative array of field => value
     * @return self
     */
    public function fill(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->__set($key, $value);
        }
        return $this;
    }

    /**
     * Get all data as array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Find a record by primary key ID
     *
     * @param mixed $id The primary key value
     * @return self|null Returns self if found, null otherwise
     */
    public function findById(mixed $id): ?self
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return (clone $this)->hydrate($row);
    }

    /**
     * Find records by a specific field value.
     * Does not apply registered JOINs. Use fetchWithJoins() for joined queries.
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param int|null $limit Maximum number of records (null for all)
     * @return array Array of NanoORM instances
     */
    public function findBy(string $field, mixed $value, ?int $limit = null): array
    {
        $field = $this->validateFieldName($field);

        $sql = "SELECT * FROM `{$this->table}` WHERE {$field} = :value";
        if ($limit !== null) {
            // $limit is int type-hinted, safe to interpolate
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':value' => $value]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            // Note: Each result is a cloned instance — large result sets will consume
            // significant memory. For bulk operations, consider using fetchWithJoins()
            // or raw PDO queries.
            $results[] = (clone $this)->hydrate($row);
        }

        return $results;
    }

    /**
     * Find all records with optional conditions
     *
     * @param array $conditions Where conditions [field => value]
     * @param string $orderBy Order by clause (e.g., "created_at DESC")
     * @param int|null $limit Maximum number of records
     * @return array Array of NanoORM instances
     */
    public function findAll(array $conditions = [], string $orderBy = '', ?int $limit = null): array
    {
        $sql = $this->buildSelectQuery();
        $params = [];

        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                $field = $this->validateFieldName($field);
                $whereClauses[] = "{$field} = :{$field}";
                $params[":{$field}"] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        if ($orderBy !== '') {
            $orderBy = $this->sanitizeOrderBy($orderBy);
            if ($orderBy !== '') {
                $sql .= " ORDER BY {$orderBy}";
            }
        }

        if ($limit !== null) {
            // $limit is int type-hinted, safe to interpolate
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $results[] = (clone $this)->hydrate($row);
        }

        return $results;
    }

    /**
     * Add a JOIN clause for multi-table queries
     *
     * @param string $table Table to join
     * @param string $localKey Local field name
     * @param string $foreignKey Foreign field name
     * @param string $type JOIN type (INNER, LEFT, RIGHT, FULL, CROSS)
     * @param array $selectFields Fields to select from joined table
     * @return self
     */
    public function addJoin(
        string $table,
        string $localKey,
        string $foreignKey,
        string $type = 'INNER',
        array $selectFields = ['*']
    ): self {
        $this->validateIdentifier($table, 'join table');
        $this->validateFieldName($localKey);
        $this->validateFieldName($foreignKey);

        // Validate select fields to prevent SQL injection ('*' is a valid wildcard)
        foreach ($selectFields as $field) {
            if ($field !== '*') {
                $this->validateFieldName($field);
            }
        }

        $type = strtoupper($type);
        $allowedTypes = ['INNER', 'LEFT', 'RIGHT', 'FULL', 'CROSS'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new \InvalidArgumentException("Invalid join type: '{$type}'");
        }

        $this->joins[] = [
            'table' => $table,
            'localKey' => $localKey,
            'foreignKey' => $foreignKey,
            'type' => $type,
            'fields' => $selectFields,
        ];
        return $this;
    }

    /**
     * Execute a query with joins and return results
     *
     * @param array $conditions Additional where conditions
     * @return array Array of results with joined data
     */
    public function fetchWithJoins(array $conditions = []): array
    {
        $sql = $this->buildSelectQuery();
        $params = [];

        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                $field = $this->validateFieldName($field);
                $whereClauses[] = "{$field} = :{$field}";
                $params[":{$field}"] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Build SELECT query with joins
     *
     * @return string SQL query
     */
    protected function buildSelectQuery(): string
    {
        // Join aliases use j0_, j1_, ... prefix to avoid collisions with main table fields
        $mainFields = array_map(function ($field) {
            return "`{$this->table}`.`{$field}`";
        }, $this->fields);

        $joinClauses = [];
        $selectFields = $mainFields;

        foreach ($this->joins as $index => $join) {
            $joinAlias = "j{$index}";
            $joinFields = array_map(function ($field) use ($join, $joinAlias) {
                if ($field === '*') {
                    return "{$joinAlias}.*";
                }
                return "{$joinAlias}.{$field} AS {$joinAlias}_{$field}";
            }, $join['fields']);
            $selectFields = array_merge($selectFields, $joinFields);

            $joinClauses[] = "{$join['type']} JOIN `{$join['table']}` AS `{$joinAlias}` ON `{$this->table}`.`{$join['localKey']}` = `{$joinAlias}`.`{$join['foreignKey']}`";
        }

        $sql = "SELECT " . implode(', ', $selectFields) . " FROM `{$this->table}`";
        if (!empty($joinClauses)) {
            $sql .= " " . implode(" ", $joinClauses);
        }

        return $sql;
    }

    /**
     * Save the record (insert if new, update if existing)
     *
     * @return bool Success status
     */
    public function save(): bool
    {
        if ($this->isNew) {
            return $this->insert();
        }
        return $this->update();
    }

    /**
     * Insert a new record
     *
     * @return bool Success status
     */
    protected function insert(): bool
    {
        $data = $this->data;

        // Remove primary key if it's auto-increment
        if (isset($data[$this->primaryKey])) {
            unset($data[$this->primaryKey]);
        }

        $fields = array_keys($data);
        $quotedFields = array_map(fn($f) => "`{$f}`", $fields);
        $placeholders = array_map(function ($field) {
            return ":{$field}";
        }, $fields);

        $sql = "INSERT INTO `{$this->table}` (" . implode(', ', $quotedFields) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($data);

        if ($result && !isset($this->data[$this->primaryKey])) {
            $this->data[$this->primaryKey] = $this->pdo->lastInsertId();
        }

        $this->isNew = false;
        return $result;
    }

    /**
     * Update an existing record
     *
     * @return bool Success status
     */
    protected function update(): bool
    {
        if (!isset($this->data[$this->primaryKey])) {
            throw new \Exception("Cannot update record without primary key");
        }

        $data = $this->data;
        $id = $data[$this->primaryKey];
        unset($data[$this->primaryKey]);

        $sets = array_map(function ($field) {
            return "`{$field}` = :{$field}";
        }, array_keys($data));

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $sets) . " WHERE `{$this->primaryKey}` = :{$this->primaryKey}";
        $data[$this->primaryKey] = $id;

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    /**
     * Delete the current record
     *
     * @return bool Success status
     * @throws \Exception if primary key is not set
     */
    public function delete(): bool
    {
        if (!isset($this->data[$this->primaryKey])) {
            throw new \Exception("Cannot delete record without primary key");
        }

        $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :{$this->primaryKey}";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([":{$this->primaryKey}" => $this->data[$this->primaryKey]]);

        if ($result) {
            $this->data = [];
            $this->isNew = true;
        }

        return $result;
    }

    /**
     * Delete records by condition
     *
     * @param array $conditions Where conditions [field => value]
     * @return int Number of affected rows
     */
    public function deleteWhere(array $conditions): int
    {
        if (empty($conditions)) {
            throw new \Exception("Delete conditions cannot be empty");
        }

        $whereClauses = [];
        $params = [];
        foreach ($conditions as $field => $value) {
            $field = $this->validateFieldName($field);
            $whereClauses[] = "{$field} = :{$field}";
            $params[":{$field}"] = $value;
        }

        $sql = "DELETE FROM `{$this->table}` WHERE " . implode(' AND ', $whereClauses);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Validate that a field name contains only safe characters.
     *
     * @param string $field Field name to validate
     * @return string The validated field name
     * @throws \InvalidArgumentException if the field name is invalid
     */
    private function validateFieldName(string $field): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
            throw new \InvalidArgumentException("Invalid field name: {$field}");
        }
        return $field;
    }

    /**
     * Sanitize ORDER BY clause to prevent SQL injection.
     *
     * @param string $orderBy Raw order by string
     * @return string Sanitized order by string
     * @throws \InvalidArgumentException if any column part is invalid
     */
    private function sanitizeOrderBy(string $orderBy): string
    {
        $validDirections = ['ASC', 'DESC', 'ASC NULLS FIRST', 'DESC NULLS FIRST', 'ASC NULLS LAST', 'DESC NULLS LAST'];
        $parts = explode(',', $orderBy);
        $sanitized = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            // Split into column and optional direction
            $tokens = preg_split('/\s+/', $part);
            $column = $tokens[0];

            // Validate each segment of a dotted column name (e.g. "t.column")
            foreach (explode('.', $column) as $segment) {
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $segment)) {
                    throw new \InvalidArgumentException("Invalid ORDER BY column: '{$column}'");
                }
            }

            // Rebuild with validated direction
            if (count($tokens) > 1) {
                $direction = strtoupper(implode(' ', array_slice($tokens, 1)));
                if (!in_array($direction, $validDirections, true)) {
                    throw new \InvalidArgumentException("Invalid ORDER BY direction in: '{$part}'");
                }
                $sanitized[] = "{$column} {$direction}";
            } else {
                $sanitized[] = $column;
            }
        }

        return implode(', ', $sanitized);
    }

    /**
     * Hydrate the object with data from database
     *
     * @param array $row Database row
     * @return self
     */
    protected function hydrate(array $row): self
    {
        $this->data = $row;
        $this->isNew = false;
        return $this;
    }

    /**
     * Get the primary key value
     *
     * @return mixed
     */
    public function getId(): mixed
    {
        return $this->data[$this->primaryKey] ?? null;
    }

    /**
     * Check if record is new (not yet saved)
     *
     * @return bool
     */
    public function isNew(): bool
    {
        return $this->isNew;
    }

    /**
     * Get table name
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Resets entity data, isNew state, and registered joins.
     * Table schema is preserved.
     */
    public function clear(): self
    {
        $this->data = [];
        $this->isNew = true;
        $this->joins = [];
        return $this;
    }

    #############################
    # PAGINATION
    #############################

    /**
     * Paginate records with optional conditions and ordering.
     *
     * @param int $page Page number (1-based)
     * @param int $perPage Records per page
     * @param array $conditions Where conditions [field => value]
     * @param string $orderBy Order by clause (e.g., "created_at DESC")
     * @return array Pagination result with data, total, page, per_page, last_page
     * @throws \InvalidArgumentException if page or perPage is less than 1
     */
    public function paginate(int $page, int $perPage, array $conditions = [], string $orderBy = ''): array
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be >= 1');
        }
        if ($perPage < 1) {
            throw new \InvalidArgumentException('Per page must be >= 1');
        }
        if (!empty($this->joins)) {
            throw new \Exception("Paginate does not support joined queries");
        }

        $offset = ($page - 1) * $perPage;

        // Build WHERE clause once, reuse for both COUNT and SELECT
        $params = [];
        $whereSql = '';
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                $field = $this->validateFieldName($field);
                $whereClauses[] = "{$field} = :{$field}";
                $params[":{$field}"] = $value;
            }
            $whereSql = ' WHERE ' . implode(' AND ', $whereClauses);
        }

        // COUNT query
        $countSql = "SELECT COUNT(*) as total FROM `{$this->table}`{$whereSql}";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) ($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

        // SELECT query
        $selectSql = "SELECT * FROM `{$this->table}`{$whereSql}";

        if ($orderBy !== '') {
            $orderBy = $this->sanitizeOrderBy($orderBy);
            if ($orderBy !== '') {
                $selectSql .= " ORDER BY {$orderBy}";
            }
        }

        // $perPage and $offset are int type-hinted/computed, safe to interpolate
        $selectSql .= " LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($selectSql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $results[] = (clone $this)->hydrate($row);
        }

        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'data'      => $results,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => $lastPage,
        ];
    }
    ##############
    # TRANSACTIONS
    ##############

    /**
     * Start a database transaction
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Commit the current transaction
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Roll back the current transaction
     */
    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * Execute a callback inside a transaction.
     * Commits on success, rolls back on failure.
     *
     * @param callable $callback Code to run within the transaction
     * @return mixed The return value of the callback
     * @throws \Exception|\Error Re-thrown from the callback after rollback
     */
    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback();
            $this->pdo->commit();
            return $result;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        } catch (\Error $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    ##############
    # MIGRATIONS
    ##############

    /**
     * Run all pending migration files in a directory.
     *
     * @param \PDO $pdo Database connection
     * @param string $migrationsDir Path to directory containing .sql migration files
     * @return array List of applied migration file names
     * @throws \Exception If migrations directory not found
     * @throws \InvalidArgumentException If a migration file name is invalid
     */
    public static function migrateDir(\PDO $pdo, string $migrationsDir): array
    {
        if (!is_dir($migrationsDir)) {
            throw new \Exception("Migrations directory not found: {$migrationsDir}");
        }

        self::ensureMigrationsTable($pdo);

        $stmt = $pdo->query("SELECT name FROM migrations");
        $appliedNames = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);

        // Validate all .sql files and collect valid migration files
        $files = [];
        foreach (scandir($migrationsDir) as $fileName) {
            if (!str_ends_with($fileName, '.sql')) {
                continue;
            }
            if (!preg_match('/^\d+_[a-zA-Z0-9_]+\.sql$/', $fileName)) {
                throw new \InvalidArgumentException("Invalid migration file name: {$fileName}");
            }
            $files[] = $fileName;
        }

        sort($files);

        $appliedLookup = array_flip($appliedNames);

        $newlyApplied = [];
        foreach ($files as $fileName) {
            if (isset($appliedLookup[$fileName])) {
                continue;
            }

            $migrationFilePath = rtrim($migrationsDir, '\\/') . '/' . $fileName;
            $content = file_get_contents($migrationFilePath);
            self::executeSqlFile($pdo, $content);

            $stmt = $pdo->prepare("INSERT INTO migrations (name, applied_at) VALUES (:name, :appliedAt)");
            $stmt->execute([
                ':name'      => $fileName,
                ':appliedAt' => date('Y-m-d H:i:s'),
            ]);

            $newlyApplied[] = $fileName;
        }

        return $newlyApplied;
    }

    /**
     * Roll back the last N applied migrations using rollback files.
     *
     * @param \PDO $pdo Database connection
     * @param string $migrationsDir Path to directory containing .sql migration files
     * @param int $steps Number of migrations to roll back (default: 1)
     * @return array List of rolled-back migration file names
     * @throws \Exception If migrations directory not found or rollback file missing
     */
    public static function rollbackDir(\PDO $pdo, string $migrationsDir, int $steps = 1): array
    {
        if (!is_dir($migrationsDir)) {
            throw new \Exception("Migrations directory not found: {$migrationsDir}");
        }

        self::ensureMigrationsTable($pdo);

        $stmt = $pdo->prepare("SELECT name FROM migrations ORDER BY id DESC LIMIT :steps");
        $stmt->bindValue(':steps', $steps, \PDO::PARAM_INT);
        $stmt->execute();
        $toRollback = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);

        $rolledBack = [];
        foreach ($toRollback as $name) {
            if (!preg_match('/^\d+_[a-zA-Z0-9_]+\.sql$/', $name)) {
                throw new \InvalidArgumentException("Invalid migration file name: {$name}");
            }

            $rollbackPath = rtrim($migrationsDir, '\\/') . '/rollback/' . $name;

            if (!file_exists($rollbackPath)) {
                throw new \Exception("No rollback file found for {$name}");
            }

            $content = file_get_contents($rollbackPath);
            self::executeSqlFile($pdo, $content);

            $stmt = $pdo->prepare("DELETE FROM migrations WHERE name = :name");
            $stmt->execute([':name' => $name]);

            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * Get the status of all migrations: which are applied and which are pending.
     *
     * @param \PDO $pdo Database connection
     * @param string $migrationsDir Path to directory containing .sql migration files
     * @return array With keys 'applied' and 'pending', each an array of file names
     * @throws \Exception If migrations directory not found
     */
    public static function migrationStatus(\PDO $pdo, string $migrationsDir): array
    {
        if (!is_dir($migrationsDir)) {
            throw new \Exception("Migrations directory not found: {$migrationsDir}");
        }

        self::ensureMigrationsTable($pdo);

        $stmt = $pdo->query("SELECT name FROM migrations ORDER BY name");
        $applied = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);

        $allFiles = [];
        foreach (scandir($migrationsDir) as $fileName) {
            if (!str_ends_with($fileName, '.sql')) {
                continue;
            }
            if (!preg_match('/^\d+_[a-zA-Z0-9_]+\.sql$/', $fileName)) {
                throw new \InvalidArgumentException("Invalid migration file name: {$fileName}");
            }
            $allFiles[] = $fileName;
        }

        $pending = array_values(array_diff($allFiles, $applied));

        return [
            'applied' => $applied,
            'pending' => $pending,
        ];
    }

    /**
     * Ensure the migrations tracking table exists.
     *
     * @param \PDO $pdo Database connection
     */
    private static function ensureMigrationsTable(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, applied_at TEXT)");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL UNIQUE, applied_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        }
    }

    /**
     * Execute SQL content split by semicolons.
     * NOTE: This uses naive splitting on ';' — it does not handle semicolons
     * inside string literals or comments. Ensure migration SQL does not contain
     * literal semicolons in data values.
     *
     * For SQLite: executes each statement individually (no transaction, DDL not supported).
     * For other drivers: wraps all statements in a transaction.
     *
     * @param \PDO $pdo Database connection
     * @param string $content SQL content to execute
     * @throws \Exception|\Error On execution failure (after rollback if in transaction)
     */
    private static function executeSqlFile(\PDO $pdo, string $content): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $statements = array_filter(array_map('trim', explode(';', $content)));

        if ($driver === 'sqlite') {
            foreach ($statements as $sql) {
                $pdo->exec($sql);
            }
            return;
        }

        // Non-SQLite: wrap in transaction
        $pdo->beginTransaction();
        try {
            foreach ($statements as $sql) {
                $pdo->exec($sql);
            }
            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        } catch (\Error $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
