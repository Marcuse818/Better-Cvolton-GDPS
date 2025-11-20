<?php
 require_once __DIR__ . '/../../config/connection.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $connection;
    private $options;
    private $last_stmt;
    private $query_log = [];
    private $max_query_log = 100;
    private $is_connected = false;

    public function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        
        $this->set_options();
        $this->validate_config();
        $this->open_connection();
    }

    private function set_options(): void {
        $this->options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'"
        ];
    }
    
    private function validate_config(): void {
        $required = [ 'host', 'db_name', 'username' ];

        foreach ($required as $param) {
            if (empty($this->$param)) throw new InvalidArgumentException("Database $param is not configured");
        }

        if (strlen($this->password) < 8) throw new InvalidArgumentException("Database passowrd is too short");
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $this->db_name)) throw new InvalidArgumentException('Invalid database name');
    }

    public function validate_connection_params(): void {
        if (preg_match('/[^a-zA-Z0-9.\-:]/', $this->host)) throw new InvalidArgumentException('Invalid database host');
        if (preg_match('/[^a-zA-Z0-9_]/', $this->db_name)) throw new InvalidArgumentException('Invalid database name characters');
        if (strlen($this->host) > 255 || strlen($this->db_name) > 64) throw new InvalidArgumentException("Database connection parameters too long");
        if ($this->host == "localhost" || $this->host == "127.0.0.1") $this->log_security_warning("Database connection to localhost");
    }

    private function log_security_warning(string $msg, $data = ""): void {
        $warning = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'security_warning',
            'message' => $msg,
            'data' => $data,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown' 
        ];

        error_log('Database Warning' . json_encode($warning));

        file_put_contents(
            __DIR__ . '/database.log',
            json_encode($warning) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private function handle_query_error(string $context, string $sql, array $params, PDOException $e): void {
        $error_info = [
            'context' => $context,
            'sql' => $sql,
            'params'=> $params,
            'error'=> $e->getMessage(),
            'code'=> $e->getCode()
        ];

        $this->log_error($context);

        if ($e->getCode() == '23000') throw new RuntimeException('Database constraint violation');
        if ($e->getCode() == '42000') throw new RuntimeException('Database syntax error');

        throw new RuntimeException('Database operation failed.');
    }

    public function open_connection(): PDO {
        if ($this->is_connected) return $this->connection;

        try {
            $this->validate_connection_params();

            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $this->options);
            $this->is_connected = true;
            $this->log_query("CONNECT", "Database connected established");
            
            return $this->connection;
            
        } catch (PDOException $e) {
            $this->log_error("Connection failed", [
                'error' => $e->getMessage(),
                'host' => $this->host,
                'db_name'=> $this->db_name
            ]);

            throw new RuntimeException("Database connection error");
        }
    }

    public function fetch_all(string $sql, array $params = []): array {
        $this->validate_query($sql, $params);

        try {
            $stmt = $this->connection->prepare($sql);
            $this->bind_params($stmt, $params);
            $stmt->execute();
            
            $this->last_stmt = $stmt;
            $this->log_query("SELECT", $sql, $params);
            $result = $stmt->fetchAll();

            return $this->sanitize_result($result); 
        } catch (PDOException $e) {
            $this->handle_query_error("Fetch all error", $sql, $params, $e);
            return [];
        }
    }

    public function fetch_one(string $sql, array $params = []) {
        $this->validate_query($sql, $params);

        try {
            $stmt = $this->connection->prepare($sql);
            $this->bind_params($stmt, $params);
            $stmt->execute();
            
            $this->last_stmt = $stmt;
            $this->log_query("SELECT", $sql, $params);
            $result = $stmt->fetch();

            return $result ? $this->sanitize_row($result) : null;
        } catch (PDOException $e) {
           $this->handle_query_error("Fetch one error", $sql, $params, $e);
        }
    }

    public function fetch_column(string $sql, array $params = [], int $column_number = 0) {
        $this->validate_query($sql, $params);

        try {
            $stmt = $this->connection->prepare($sql);
            $this->bind_params($stmt, $params);
            $stmt->execute();

            $this->last_stmt = $stmt;
            $this->log_query("FETCH_COLUMN", $sql, $params);
            $result = $stmt->fetchColumn($column_number);

            return $this->sanitize_value($result);
        }
        catch (PDOException $e) {
           $this->handle_query_error("Fetch column error", $sql, $params, $e);
        }
    }
    
    public function execute(string $sql, array $params = []): bool {
        $this->validate_query($sql, $params);
        $this->validate_write_operation($sql);

        try {
            $stmt = $this->connection->prepare($sql);
            $this->bind_params($stmt, $params);
            $result = $stmt->execute();
            
            $this->last_stmt = $stmt;
            $this->log_query("EXECUTE", $sql, $params);

            return $result;
        } catch (PDOException $e) {
            $this->handle_query_error("Execute error", $sql, $params, $e);
            return false;
        }
    }

    public function insert(string $sql, array $params = []): int {
        $this->validate_query($sql, $params);

        if (!preg_match('/^\s*INSERT\s+/i', $sql)) {
            throw new InvalidArgumentException('Insert method can only be used with INSERT queries');
        }

        try {
            $stmt = $this->connection->prepare($sql);
            $this->bind_params($stmt, $params);
            $stmt->execute();
            
            $this->last_stmt = $stmt;
            $this->log_query("INSERT", $sql, $params);

            return (int)$this->connection->lastInsertId();
        } catch (PDOException $e) {
           $this->handle_query_error("Insert error", $sql, $params, $e);
            return 0;
        }
    }

    public function row_count(): int {
        try {
            if ($this->last_stmt instanceof PDOStatement) return $this->last_stmt->rowCount();

            return 0;
        } catch (Exception $e) {
            $this->log_error("RowCount error: " + $e->getMessage());
            return 0;
        }
    }

    private function bind_params(PDOStatement $stmt, array $params): void {
        foreach ($params as $key => $value) {
            $param_type = $this->get_param_type($value);
            $param_name = is_int($key) ? $key + 1 : $key;
            
            $this->validate_param_value($value, $param_name);

            $stmt->bindValue($param_name, $value, $param_type);
        }
    }

    private function validate_param_value($value, $param_name): void {
        if (is_string($value) && strlen($value) > 1000000) {
            throw new InvalidArgumentException('Parameter value too large for: ' . $param_name);
        }
        if (is_string($value) && preg_match('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/', $value)) {
            $this->log_security_warning('Binary data in text parameeter', $param_name);
        }

        $suspicious_patterns = [
            '/(\bUNION\s+ALL\b)/i',
            '/(\bSELECT\s+\*\b)/i',
            '/(\bINSERT\s+INTO\b)/i',
            '/(\bDROP\s+TABLE\b)/i',
            '/(\bOR\s+1=1\b)/i',
            '/(\b--\b)/',
            '/(;\\s*\\w+)/'
        ];

        if (is_string($value)) {
            foreach ($suspicious_patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    $this->log_security_warning('Potential SQL injection pattern in parameter', [
                        'param' => $param_name,
                        'pattern' => $pattern
                    ]);
                }
            }
        }
    }

    private function get_param_type($value): int {
        if (is_int($value)) return PDO::PARAM_INT;
        if (is_bool($value)) return PDO::PARAM_BOOL;
        if (is_null($value)) return PDO::PARAM_NULL;
        if (is_resource($value)) return PDO::PARAM_LOB;
        return PDO::PARAM_STR;
    }

    public function exists(string $table, string $where, array $params = []): bool {
        if (!preg_match('/^[a-zA-Z_] [a-zA-Z0-9_]*$/', $table)) {
            throw new InvalidArgumentException('Invalid table: ' . $table);
        }

        $sql = "SELECT COUNT(*) FROM {$this->quote_identifier($table)} WHERE $where";
        
        try {
            return $this->fetch_column($sql, $params) > 0;
        } catch (PDOException $e) {
            $this->log_error("Exists check error for table: " . $table);
            return false;
        }
    }

    public function count(string $table, string $where = "", array $params = []): int {
        if (!preg_match('/^[a-zA-Z_] [a-zA-Z0-9_]*$/', $table)) {
            throw new InvalidArgumentException('Invalid table: ' . $table);
        }

        $sql = "SELECT COUNT(*) FROM {$this->quote_identifier($table)}";
        
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }
        
        try {
            return (int) $this->fetch_column($sql, $params);
        } catch (PDOException $e) {
            $this->log_error("Count error for table: " . $table);
            return 0;
        }
    }

    public function quote_identifier(string $identifier): string {
        if (!preg_match('/^[a-zA-Z_] [a-zA-Z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException('Invalid indentifier: ' . $identifier);
        }

        return "`" . str_replace("`", "``", $identifier) . "`";
    }

    private function sanitize_result(array $result): array {
        return array_map([$this, 'sanitize_row'], $result);
    }

    private function sanitize_row(array $row): array {
        return array_map([$this, 'sanitize_value'], $row);
    }

    private function sanitize_value($value) {
        if (is_string($value)) {
            $value = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/', '', $value);
            $value = str_replace('\0', '', $value);
        }

        return $value;
    }

    private function validate_query(string $sql, array $params): void {
        if (empty(trim($sql))) throw new InvalidArgumentException("SQL query cannot be empty");
        if (strlen($sql) > 10000) throw new InvalidArgumentException("SQL query too long");

        $dangerous_patterns = [
            '/\bDROP\s+(DATABASE|TABLE)\b/i',
            '/\bTRUNCATE\s+TABLE\b/i',
            '/\bALTER\s+TABLE\b/i',
            '/\bCREATE\s+(DATABASE|TABLE)\b/i',
            '/\bGRANT\b/i',
            '/\bREVOKE\b/i',
            '/\bPROCEDURE\b/i',
            '/\bFUNCTION\b/i',
            '/\bEXEC\b/i',
            '/\bUNION\s+SELECT\b/i'
        ];

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                $this->log_security_warning('Potentieally dangerous query detected', $sql);
                throw new InvalidArgumentException('Potentially dangerous database operation detected');
            }
        }

        $expected_params = substr_count($sql, ':');
        $question_params = substr_count($sql, '?');
        $total_expected = $expected_params + $question_params;

        if ($total_expected !== count($params)) throw new InvalidArgumentException("Parameter count mismatch. Expected: $total_expected, Got: " . count($params));
    }

    private function validate_write_operation(string $sql): void {
        $write_keywords = ['INSERT', 'UPDATE', 'DELETE', 'REPLACE'];
        $is_write_operation = false;
        
        foreach ($write_keywords as $keyword) {
            if (preg_match('/^\s*' . $keyword . '\s+/i', $sql)) {
                $is_write_operation = true;
                break;
            }
        }

        if (!$is_write_operation) {
            throw new InvalidArgumentException('Write operation expected but not found in query');
        }
    }

    public function log_query(string $type, string $sql, array $params = []): void {
        if (count($this->query_log) >= $this->max_query_log) array_shift($this->query_log);

        $this->query_log = [
            'timestamp' => microtime(true),
            'type' => $type,
            'sql'=> $sql,
            'params'=> $params,
            'backtrace' => $this->get_safe_backtrace()
        ];
    }

    private function get_safe_backtrace(): array {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $safe_trace = [];

        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && strpos($trace['file'], "Database.php") === false) {
                $safe_trace[] = [
                    'file' => basename($trace['file']),
                    'line'=> $trace['line'],
                    'function'=> $trace['function']
                ];
            }
        }

        return $safe_trace;
    }
    private function log_error(string $message, array $context = []): void {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context'=> $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        error_log("Database Error: " . json_encode($log_entry));
        
        if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE === true) {
            file_put_contents(
                __DIR__ . "/database_errors.log",
                json_encode($log_entry) . PHP_EOL,
                FILE_APPEND | LOCAL_CREDS
            );
        }
    }

    public function get_query_log(): array {
        if (!defined("DEVELOPMENT_MODE") || DEVELOPMENT_MODE !== true) return [];
        return $this->query_log;
    }

    public function clear_query_log(): void {
        $this->query_log = [];
    }

    public function close_connection(): void {
        if ($this->is_connected) {
            $this->last_stmt = null;
            $this->is_connected = false;
            $this->connection = null;
        }
    }

    public function __destruct() {
        $this->close_connection();
    }

    public function __sleep() {
        throw new RuntimeException('Database objects cannot be serialized');
    }

    public function __clone() {
        throw new RuntimeException('Database objects cannot be cloned');
    }
}