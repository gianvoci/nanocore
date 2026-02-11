<?php

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
        $this->pdo = $pdo;
        $this->table = $table;
        $this->primaryKey = $primaryKey;
        $this->loadTableSchema();
    }

    /**
     * Load table schema to identify fields
     */
    protected function loadTableSchema(): void
    {
        try {
            $stmt = $this->pdo->query("DESCRIBE {$this->table}");
            $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($columns as $column) {
                $this->fields[] = $column['Field'];
            }
        } catch (\Exception $e) {
            // Fallback for SQLite or other databases
            try {
                $stmt = $this->pdo->query("PRAGMA table_info({$this->table})");
                $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($columns as $column) {
                    $this->fields[] = $column['name'];
                }
            } catch (\Exception $e2) {
                throw new \Exception("Unable to load schema for table: {$this->table}");
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
    public function __set(string $name, $value): void
    {
        if (in_array($name, $this->fields) || $name === $this->primaryKey) {
            $this->data[$name] = $value;
        }
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
    public function findById($id): ?self
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Find records by a specific field value
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param int|null $limit Maximum number of records (null for all)
     * @return array Array of NanoORM instances
     */
    public function findBy(string $field, $value, ?int $limit = null): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$field} = :value";
        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':value' => $value]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
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
                $whereClauses[] = "{$field} = :{$field}";
                $params[":{$field}"] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit !== null) {
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
     * @param string $type JOIN type (INNER, LEFT, RIGHT)
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
        $this->joins[] = [
            'table' => $table,
            'localKey' => $localKey,
            'foreignKey' => $foreignKey,
            'type' => strtoupper($type),
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
        $mainFields = array_map(function ($field) {
            return "{$this->table}.{$field}";
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

            $joinClauses[] = "{$join['type']} JOIN {$join['table']} AS {$joinAlias} ON {$this->table}.{$join['localKey']} = {$joinAlias}.{$join['foreignKey']}";
        }

        $sql = "SELECT " . implode(', ', $selectFields) . " FROM {$this->table}";
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
        $placeholders = array_map(function ($field) {
            return ":{$field}";
        }, $fields);

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

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
            return "{$field} = :{$field}";
        }, array_keys($data));

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE {$this->primaryKey} = :id";
        $data['id'] = $id;

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

        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([':id' => $this->data[$this->primaryKey]]);

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
            $whereClauses[] = "{$field} = :{$field}";
            $params[":{$field}"] = $value;
        }

        $sql = "DELETE FROM {$this->table} WHERE " . implode(' AND ', $whereClauses);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
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
    public function getId()
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
     * Clear all data and reset to new state
     *
     * @return self
     */
    public function clear(): self
    {
        $this->data = [];
        $this->isNew = true;
        $this->joins = [];
        return $this;
    }
}
