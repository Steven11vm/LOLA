<?php
/**
 * Configuración de conexión a la base de datos
 * Incluye este archivo en tus scripts para conectar a la BD
 */

class Database {
    private $host = 'localhost';
    private $dbname = 'emuna_system';
    private $username = 'root';
    private $password = '';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    // Método para registrar logs del sistema
    public function logAction($user_id, $action, $description, $ip_address = null, $user_agent = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO system_logs (user_id, action, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent]);
    }
    
    // Método para verificar intentos de login
    public function checkLoginAttempts($ip_address, $time_window = 15) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND success = FALSE 
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$ip_address, $time_window]);
        $result = $stmt->fetch();
        return $result['attempts'];
    }
    
    // Método para registrar intento de login
    public function logLoginAttempt($username, $ip_address, $success, $user_agent = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts (username, ip_address, success, user_agent) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$username, $ip_address, $success, $user_agent]);
    }
}
?>
