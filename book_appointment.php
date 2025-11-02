<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Obtener el paquete seleccionado
$package_id = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;

if ($package_id) {
    $stmt = $db->prepare("SELECT * FROM treatment_packages WHERE id = ? AND is_active = 1");
    $stmt->execute([$package_id]);
    $package = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$package) {
        header('Location: client_dashboard.php');
        exit();
    }
}

// Procesar el formulario de cita
if ($_POST) {
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $notes = $_POST['notes'] ?? '';
    
    try {
        // Verificar que la fecha no sea en el pasado
        if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
            throw new Exception('No puedes agendar citas en fechas pasadas');
        }
        
        // Verificar disponibilidad (simplificado)
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM appointments 
            WHERE appointment_date = ? AND appointment_time = ? AND status IN ('scheduled', 'confirmed')
        ");
        $stmt->execute([$appointment_date, $appointment_time]);
        $existing = $stmt->fetchColumn();
        
        if ($existing > 0) {
            throw new Exception('Ya existe una cita en esa fecha y hora');
        }
        
        // Crear la cita
        $stmt = $db->prepare("
            INSERT INTO appointments (user_id, package_id, appointment_date, appointment_time, duration_minutes, notes, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $user_id, 
            $package_id, 
            $appointment_date, 
            $appointment_time, 
            $package['duration_minutes'], 
            $notes
        ]);
        
        $success_message = "¡Cita agendada exitosamente!";
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMUNA - Agendar Cita</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #047475, #aec2c0);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ebe4c7;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .header {
            text-align: center;
            color: #047475;
            margin-bottom: 2rem;
        }

        .package-info {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border: 2px solid #aec2c0;
        }

        .package-name {
            color: #047475;
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .package-price {
            color: #b08660;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 1rem 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: #047475;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #aec2c0;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #047475;
        }

        .btn {
            background: linear-gradient(135deg, #047475, #b08660);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(4, 116, 117, 0.3);
        }

        .btn-secondary {
            background: #aec2c0;
            color: #047475;
            margin-top: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .back-link {
            display: inline-block;
            color: #047475;
            text-decoration: none;
            margin-bottom: 1rem;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="client_dashboard.php" class="back-link">← Volver al Dashboard</a>
        
        <div class="header">
            <h1>Agendar Cita</h1>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
                <br><br>
                <a href="client_dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
            </div>
        <?php elseif (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($package && !isset($success_message)): ?>
            <div class="package-info">
                <div class="package-name"><?php echo htmlspecialchars($package['name']); ?></div>
                <p><?php echo htmlspecialchars($package['description']); ?></p>
                <div class="package-price">$<?php echo number_format($package['price'], 2); ?></div>
                <small>
                    Duración: <?php echo $package['duration_minutes']; ?> minutos • 
                    <?php echo $package['sessions_included']; ?> sesión(es)
                </small>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="appointment_date">Fecha de la Cita:</label>
                    <input type="date" id="appointment_date" name="appointment_date" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="appointment_time">Hora de la Cita:</label>
                    <input type="time" id="appointment_time" name="appointment_time" 
                           min="08:00" max="18:00" required>
                </div>

                <div class="form-group">
                    <label for="notes">Notas Adicionales (Opcional):</label>
                    <textarea id="notes" name="notes" rows="3" 
                              placeholder="Alguna información adicional que quieras compartir..."></textarea>
                </div>

                <button type="submit" class="btn">Confirmar Cita</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
