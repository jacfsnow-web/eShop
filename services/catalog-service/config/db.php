<?php
declare(strict_types=1);

namespace Config;

use PDO;
use PDOException;

class Database {
    private string $host;
    private string $username;
    private string $password;
    private string $dbName;
    private ?PDO $conn = null;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: 'mysql';
        $this->username = getenv('DB_USER') ?: 'root';
        $envPass = getenv('DB_PASS');
        $this->password = ($envPass !== false) ? $envPass : 'root_password';
        $this->dbName = getenv('DB_NAME') ?: 'db_ecommerce_catalog';
    }

    public function getConnection(): PDO {
        if ($this->conn === null) {
            $dsn = "mysql:host={$this->host};dbname={$this->dbName};charset=utf8mb4";
            try {
                $this->conn = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/problem+json');
                echo json_encode([
                    'type' => 'about:blank',
                    'title' => 'Database Connection Error',
                    'status' => 500,
                    'detail' => 'Could not connect to the database: ' . $e->getMessage(),
                    'instance' => $_SERVER['REQUEST_URI'] ?? ''
                ]);
                exit;
            }
        }
        return $this->conn;
    }
}
