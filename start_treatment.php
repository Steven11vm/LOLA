<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_POST && isset($_POST['appointment_id']) && isset($_POST['user_id']) && isset($_POST['package_id'])) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $appointment_id = (int)$_POST['appointment_id'];
    $user_id = (int)$_POST['user_id'];
    $package_id = (int)$_POST['package_id'];
    $admin_id = $_SESSION['user_id'];
    
    try {
        // Obtener información del paquete
        $stmt = $conn->prepare("SELECT * FROM treatment_packages WHERE id = ?");
        $stmt->execute([$package_id]);
        $package = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$package) {
            throw new Exception('Paquete no encontrado');
        }
        
        // Verificar si ya existe un tratamiento activo para este usuario y paquete
        $stmt = $conn->prepare("SELECT id FROM client_treatments WHERE user_id = ? AND package_id = ? AND status = 'active'");
        $stmt->execute([$user_id, $package_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            throw new Exception('Ya existe un tratamiento activo para este paquete');
        }
        
        // Crear el tratamiento activo
        $stmt = $conn->prepare("
            INSERT INTO client_treatments (user_id, package_id, sessions_total, sessions_completed, status, start_date, created_by) 
            VALUES (?, ?, ?, 0, 'active', CURDATE(), ?)
        ");
        $stmt->execute([$user_id, $package_id, $package['sessions_included'], $admin_id]);
        
        // Actualizar el estado de la cita a 'in_treatment'
        $stmt = $conn->prepare("UPDATE appointments SET status = 'in_treatment' WHERE id = ?");
        $stmt->execute([$appointment_id]);
        
        // Agregar nota de inicio de tratamiento
        $stmt = $conn->prepare("
            INSERT INTO treatment_notes (user_id, note_content, note_type, created_by) 
            VALUES (?, ?, 'treatment', ?)
        ");
        $note_content = "Tratamiento iniciado: " . $package['name'] . " - " . $package['sessions_included'] . " sesiones programadas";
        $stmt->execute([$user_id, $note_content, $admin_id]);
        
        echo json_encode(['success' => true, 'message' => 'Tratamiento iniciado exitosamente']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
}
?>
