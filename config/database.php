<?php
/**
 * Database Configuration for Pipeline Manager Integration
 * PHP 5.3 Compatible
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'integration_db';
    private $username = 'root';
    private $password = '';
    private $conn;
    
    public function __construct() {
        // Load environment variables if available
        if (file_exists(__DIR__ . '/.env')) {
            $env = parse_ini_file(__DIR__ . '/.env');
            $this->host = isset($env['DB_HOST']) ? $env['DB_HOST'] : $this->host;
            $this->db_name = isset($env['DB_NAME']) ? $env['DB_NAME'] : $this->db_name;
            $this->username = isset($env['DB_USER']) ? $env['DB_USER'] : $this->username;
            $this->password = isset($env['DB_PASS']) ? $env['DB_PASS'] : $this->password;
        }
    }
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
        }
        
        return $this->conn;
    }
}
?>