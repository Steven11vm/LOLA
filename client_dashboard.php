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

// Obtener información del usuario
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener tratamientos activos
$stmt = $db->prepare("
    SELECT ct.*, tp.name as package_name, tp.description, tp.sessions_included, tp.duration_minutes
    FROM client_treatments ct 
    JOIN treatment_packages tp ON ct.package_id = tp.id 
    WHERE ct.user_id = ? AND ct.status = 'active'
    ORDER BY ct.start_date DESC
");
$stmt->execute([$user_id]);
$active_treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener próximas citas
$stmt = $db->prepare("
    SELECT a.*, tp.name as package_name, tp.duration_minutes
    FROM appointments a 
    JOIN treatment_packages tp ON a.package_id = tp.id 
    WHERE a.user_id = ? AND a.appointment_date >= CURDATE() AND a.status IN ('scheduled', 'confirmed')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
");
$stmt->execute([$user_id]);
$upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener paquetes disponibles
$stmt = $db->prepare("SELECT * FROM treatment_packages WHERE is_active = 1 ORDER BY category, name");
$stmt->execute();
$available_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener historial de citas completadas
$stmt = $db->prepare("
    SELECT a.*, tp.name as package_name
    FROM appointments a 
    JOIN treatment_packages tp ON a.package_id = tp.id 
    WHERE a.user_id = ? AND a.status = 'completed'
    ORDER BY a.appointment_date DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$completed_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMUNA - Dashboard Cliente</title>
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
            color: #333;
        }

        .header {
            background: #047475;
            color: #ebe4c7;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logout-btn {
            background: #b08660;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #9a7555;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background: #ebe4c7;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: 2px solid #aec2c0;
        }

        .card-header {
            color: #047475;
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 1rem;
            border-bottom: 2px solid #aec2c0;
            padding-bottom: 0.5rem;
        }

        .treatment-item, .appointment-item, .package-item {
            background: white;
            padding: 1rem;
            margin: 0.5rem 0;
            border-radius: 8px;
            border-left: 4px solid #047475;
        }

        .treatment-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            color: white;
        }

        .status-active { background: #28a745; }
        .status-scheduled { background: #007bff; }
        .status-completed { background: #6c757d; }

        .progress-bar {
            background: #aec2c0;
            height: 8px;
            border-radius: 4px;
            margin: 0.5rem 0;
            overflow: hidden;
        }

        .progress-fill {
            background: #047475;
            height: 100%;
            transition: width 0.3s ease;
        }

        .btn {
            background: linear-gradient(135deg, #047475, #b08660);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(4, 116, 117, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #aec2c0, #b08660);
        }

        .price {
            color: #b08660;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .date-time {
            color: #047475;
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            border: 2px solid #aec2c0;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #047475;
        }

        .stat-label {
            color: #666;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .container {
                padding: 0 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">EMUNA</div>
            <div class="user-info">
                <span>Bienvenido, <?php echo htmlspecialchars($user['full_name']); ?></span>
                <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Estadísticas rápidas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($active_treatments); ?></div>
                <div class="stat-label">Tratamientos Activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($upcoming_appointments); ?></div>
                <div class="stat-label">Próximas Citas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($completed_appointments); ?></div>
                <div class="stat-label">Citas Completadas</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Tratamientos Activos -->
            <div class="card">
                <div class="card-header">🧴 Mis Tratamientos de Suero Terapia</div>
                <?php if (empty($active_treatments)): ?>
                    <div class="empty-state">No tienes tratamientos activos actualmente</div>
                <?php else: ?>
                    <?php foreach ($active_treatments as $treatment): ?>
                        <?php 
                        $progress = ($treatment['sessions_completed'] / $treatment['sessions_total']) * 100;
                        ?>
                        <div class="treatment-item">
                            <h4><?php echo htmlspecialchars($treatment['package_name']); ?></h4>
                            <p><?php echo htmlspecialchars($treatment['description']); ?></p>
                            <div class="treatment-status status-active">Activo</div>
                            <div style="margin: 0.5rem 0;">
                                <small>Progreso: <?php echo $treatment['sessions_completed']; ?>/<?php echo $treatment['sessions_total']; ?> sesiones</small>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                            <small>Inicio: <?php echo date('d/m/Y', strtotime($treatment['start_date'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Próximas Citas -->
            <div class="card">
                <div class="card-header">📅 Próximas Citas</div>
                <?php if (empty($upcoming_appointments)): ?>
                    <div class="empty-state">No tienes citas programadas</div>
                <?php else: ?>
                    <?php foreach ($upcoming_appointments as $appointment): ?>
                        <div class="appointment-item">
                            <h4><?php echo htmlspecialchars($appointment['package_name']); ?></h4>
                            <div class="date-time">
                                📅 <?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?>
                                🕐 <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                            </div>
                            <div class="treatment-status status-scheduled">
                                <?php echo ucfirst($appointment['status']); ?>
                            </div>
                            <small>Duración: <?php echo $appointment['duration_minutes']; ?> minutos</small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Paquetes Disponibles -->
            <div class="card">
                <div class="card-header">💎 Paquetes Disponibles</div>
                <?php foreach ($available_packages as $package): ?>
                    <div class="package-item">
                        <h4><?php echo htmlspecialchars($package['name']); ?></h4>
                        <p><?php echo htmlspecialchars($package['description']); ?></p>
                        <div class="price">$<?php echo number_format($package['price'], 2); ?></div>
                        <small>
                            <?php echo $package['sessions_included']; ?> sesión(es) • 
                            <?php echo $package['duration_minutes']; ?> min c/u
                        </small>
                        <div style="margin-top: 1rem;">
                            <a href="book_appointment.php?package_id=<?php echo $package['id']; ?>" class="btn">
                                Agendar Cita
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Historial -->
            <div class="card">
                <div class="card-header">📋 Historial de Tratamientos</div>
                <?php if (empty($completed_appointments)): ?>
                    <div class="empty-state">No tienes historial de tratamientos aún</div>
                <?php else: ?>
                    <?php foreach ($completed_appointments as $appointment): ?>
                        <div class="appointment-item">
                            <h4><?php echo htmlspecialchars($appointment['package_name']); ?></h4>
                            <div class="date-time">
                                <?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?>
                            </div>
                            <div class="treatment-status status-completed">Completado</div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
