<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $user_id = $_GET['user_id'] ?? 1; // Por defecto usuario 1 para pruebas
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $endpoint = $_GET['endpoint'] ?? '';
        
        switch ($endpoint) {
            case 'treatments':
                $stmt = $pdo->prepare("
                    SELECT ct.*, tp.name as package_name 
                    FROM client_treatments ct 
                    JOIN treatment_packages tp ON ct.package_id = tp.id 
                    WHERE ct.user_id = ?
                    ORDER BY ct.created_at DESC
                ");
                $stmt->execute([$user_id]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'appointments':
                $stmt = $pdo->prepare("
                    SELECT a.*, tp.name as package_name, tp.duration_minutes 
                    FROM appointments a 
                    JOIN treatment_packages tp ON a.package_id = tp.id 
                    WHERE a.user_id = ? AND a.appointment_date >= NOW()
                    ORDER BY a.appointment_date ASC
                ");
                $stmt->execute([$user_id]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'packages':
                $stmt = $pdo->prepare("SELECT * FROM treatment_packages WHERE is_active = 1 ORDER BY name");
                $stmt->execute();
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            default:
                echo json_encode(['error' => 'Endpoint no válido']);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
