<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_POST && isset($_POST['appointment_id']) && isset($_POST['sessions'])) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $appointment_id = $_POST['appointment_id'];
    $sessions = intval($_POST['sessions']);
    
    $stmt = $conn->prepare("UPDATE appointments SET sessions_planned = ? WHERE id = ?");
    $result = $stmt->execute([$sessions, $appointment_id]);
    
    echo json_encode(['success' => $result]);
} else {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
}
?>
