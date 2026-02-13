<?php
require_once __DIR__ . '/../../config/connection.php';

class Database 
{
    private ?PDO $connection = null;
    private array $options;
    
    public function __construct() 
    {
        $this->setOptions();
        $this->connect();
    }

    private function setOptions(): void 
    {
        $this->options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
    }

    private function connect(): void 
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $this->options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new RuntimeException("Database connection error");
        }
    }

    private function getConnection(): PDO 
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    public function fetchAll(string $sql, array $params = []): array 
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array 
    {
        $stmt = $this->execute($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed 
    {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchColumn($column);
    }

    public function execute(string $sql, array $params = []): PDOStatement 
    {
        if (empty(trim($sql))) {
            throw new InvalidArgumentException("SQL query cannot be empty");
        }

        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage() . " | SQL: " . $sql);
            throw new RuntimeException("Database operation failed");
        }
    }

    public function insert(string $table, array $data): int 
    {
        $this->validateIdentifier($table);
        
        $columns = array_keys($data);
        $values = array_values($data);
        
        array_walk($columns, [$this, 'validateIdentifier']);
        
        $sql = sprintf(
            "INSERT INTO `%s` (%s) VALUES (%s)",
            $table,
            implode(', ', array_map(fn($col) => "`$col`", $columns)),
            implode(', ', array_fill(0, count($values), '?'))
        );

        $this->execute($sql, $values);
        return (int) $this->getConnection()->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int 
    {
        $this->validateIdentifier($table);
        
        $setParts = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $this->validateIdentifier($column);
            $setParts[] = "`$column` = ?";
            $params[] = $value;
        }
        
        $sql = sprintf(
            "UPDATE `%s` SET %s WHERE %s",
            $table,
            implode(', ', $setParts),
            $where
        );
        
        $params = array_merge($params, $whereParams);
        $stmt = $this->execute($sql, $params);
        
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int 
    {
        $this->validateIdentifier($table);
        
        $sql = "DELETE FROM `$table` WHERE $where";
        $stmt = $this->execute($sql, $params);
        
        return $stmt->rowCount();
    }

    public function exists(string $table, string $where, array $params = []): bool 
    {
        return $this->count($table, $where, $params) > 0;
    }

    public function count(string $table, string $where = "", array $params = []): int 
    {
        $this->validateIdentifier($table);
        
        $sql = "SELECT COUNT(*) FROM `$table`";
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }
        
        return (int) $this->fetchColumn($sql, $params);
    }

    private function validateIdentifier(string $name): void 
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid identifier: $name");
        }
    }

    public function beginTransaction(): bool 
    {
        return $this->getConnection()->beginTransaction();
    }

    public function commit(): bool 
    {
        return $this->getConnection()->commit();
    }

    public function rollBack(): bool 
    {
        return $this->getConnection()->rollBack();
    }

    public function lastInsertId(): string 
    {
        return $this->getConnection()->lastInsertId();
    }
}