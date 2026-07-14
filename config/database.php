<?php
// config/database.php - Database configuration file

class Database {
    private $host = 'localhost';
    private $db_name = 'church_management';
    private $username = 'Elijah'; // Your MySQL username
    private $password = 'livingstone@05533C'; // Your MySQL password (add if you have one)
    private $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            error_log("Connection error: " . $e->getMessage());
            return null;
        }

        return $this->conn;
    }
}

// Also create a simple function to get DB connection
function getDbConnection() {
    $database = new Database();
    return $database->getConnection();
}
?>