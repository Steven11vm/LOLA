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
    WHERE a.user_id = ? AND a.appointment_date >= CURDATE() AND a.status IN ('pending', 'scheduled', 'confirmed')
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

$stmt = $db->prepare("SELECT * FROM client_files WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMUNA - Dashboard Cliente</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #047475 0%, #aec2c0 100%);
            min-height: 100vh;
            color: #2d3748;
        }

        .header {
            background: linear-gradient(135deg, #047475 0%, #035859 100%);
            color: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 4px 20px rgba(4, 116, 117, 0.3);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-welcome {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .user-name {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .user-role {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
        }

        .nav-btn {
            background: rgba(235, 228, 199, 0.2);
            color: white;
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-btn:hover {
            background: #b08660;
            transform: translateY(-2px);
        }

        .logout-btn {
            background: #b08660;
        }

        .container {
            max-width: 1400px;
            margin: 2.5rem auto;
            padding: 0 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: rgba(235, 228, 199, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(4, 116, 117, 0.1);
            border: 1px solid rgba(174, 194, 192, 0.3);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: #047475;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .stat-icon {
            font-size: 2rem;
            color: #b08660;
            margin-bottom: 1rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background: rgba(235, 228, 199, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(4, 116, 117, 0.1);
            border: 1px solid rgba(174, 194, 192, 0.3);
        }

        .card-header {
            color: #047475;
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(174, 194, 192, 0.3);
        }

        .treatment-item, .appointment-item, .package-item, .file-item {
            background: white;
            padding: 1.5rem;
            margin: 1rem 0;
            border-radius: 12px;
            border-left: 4px solid #047475;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .treatment-item:hover, .appointment-item:hover, .package-item:hover, .file-item:hover {
            transform: translateX(5px);
            box-shadow: 0 6px 20px rgba(4, 116, 117, 0.15);
        }

        .treatment-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active { 
            background: linear-gradient(135deg, #38a169 0%, #2f855a 100%); 
        }
        .status-pending { 
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%); 
        }
        .status-scheduled { 
            background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%); 
        }
        .status-confirmed { 
            background: linear-gradient(135deg, #047475 0%, #035859 100%); 
        }
        .status-completed { 
            background: linear-gradient(135deg, #718096 0%, #4a5568 100%); 
        }

        .progress-bar {
            background: rgba(174, 194, 192, 0.3);
            height: 10px;
            border-radius: 5px;
            margin: 1rem 0;
            overflow: hidden;
        }

        .progress-fill {
            background: linear-gradient(135deg, #047475 0%, #aec2c0 100%);
            height: 100%;
            transition: width 0.5s ease;
            border-radius: 5px;
        }

        .btn {
            background: linear-gradient(135deg, #047475 0%, #aec2c0 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(4, 116, 117, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #b08660 0%, #9a7555 100%);
        }

        .price {
            color: #b08660;
            font-weight: 700;
            font-size: 1.3rem;
            margin: 0.5rem 0;
        }

        .date-time {
            color: #047475;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 0.5rem 0;
        }

        .empty-state {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 3rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            border: 2px dashed #aec2c0;
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .category-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-right: 0.5rem;
        }

        .category-medical_history {
            background: #e6fffa;
            color: #047475;
        }

        .category-photos {
            background: #fef5e7;
            color: #b08660;
        }

        .category-documents {
            background: #edf2f7;
            color: #4a5568;
        }

        .category-results {
            background: #f0fff4;
            color: #38a169;
        }

        .package-item {
            background: white;
            padding: 1.5rem;
            margin: 1rem 0;
            border-radius: 12px;
            border-left: 4px solid #047475;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .package-item:hover {
            transform: translateX(5px);
            box-shadow: 0 6px 20px rgba(4, 116, 117, 0.15);
        }

        /* Added styles for better text handling and expandable descriptions */
        .package-description {
            color: #666;
            line-height: 1.6;
            margin: 0.75rem 0;
        }

        .description-preview {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .description-full {
            display: none;
        }

        .read-more-btn {
            background: none;
            border: none;
            color: #047475;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.25rem 0;
            text-decoration: underline;
            margin-top: 0.5rem;
        }

        .read-more-btn:hover {
            color: #035859;
        }

        .package-title {
            color: #047475;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            word-wrap: break-word;
        }

        .package-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            margin: 1rem 0;
            padding: 0.75rem;
            background: rgba(174, 194, 192, 0.1);
            border-radius: 8px;
        }

        .package-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #666;
        }

        .package-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(174, 194, 192, 0.2);
        }

        /* Added modal styles for package details */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: linear-gradient(135deg, #ebe4c7 0%, #f5f0e1 100%);
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(4, 116, 117, 0.3);
            animation: modalSlideIn 0.3s ease-out;
            overflow: hidden;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #047475 0%, #035859 100%);
            color: white;
            padding: 2rem;
            position: relative;
        }

        .modal-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            padding-right: 3rem;
        }

        .modal-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }

        .modal-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: #b08660;
            text-align: center;
            margin: 1rem 0;
        }

        .modal-description {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            line-height: 1.6;
            color: #4a5568;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .modal-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .modal-meta-item {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .modal-meta-icon {
            font-size: 1.5rem;
            color: #047475;
            margin-bottom: 0.5rem;
        }

        .modal-meta-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .modal-meta-value {
            font-weight: 700;
            color: #2d3748;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .nav-buttons {
                flex-wrap: wrap;
                justify-content: center;
            }

            .modal-content {
                width: 95%;
                margin: 2% auto;
            }

            .modal-meta-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-spa"></i>
                EMUNA
            </div>
            <div class="user-info">
                <div class="user-welcome">
                    <div class="user-name">Bienvenido, <?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div class="user-role">Cliente</div>
                </div>
                <div class="nav-buttons">
                    <a href="client_profile.php" class="nav-btn">
                        <i class="fas fa-user-circle"></i>
                        Mi Perfil
                    </a>
                    <a href="logout.php" class="nav-btn logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Estadísticas rápidas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-flask"></i></div>
                <div class="stat-number"><?php echo count($active_treatments); ?></div>
                <div class="stat-label">Tratamientos Activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-number"><?php echo count($upcoming_appointments); ?></div>
                <div class="stat-label">Próximas Citas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo count($completed_appointments); ?></div>
                <div class="stat-label">Citas Completadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
                <div class="stat-number"><?php echo count($recent_files); ?></div>
                <div class="stat-label">Archivos Subidos</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Tratamientos Activos -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-flask"></i>
                    Mis Tratamientos de Suero Terapia
                </div>
                <?php if (empty($active_treatments)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-flask"></i></div>
                        <h3>No tienes tratamientos activos</h3>
                        <p>Agenda tu primera cita para comenzar</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($active_treatments as $treatment): ?>
                        <?php 
                        $progress = ($treatment['sessions_completed'] / $treatment['sessions_total']) * 100;
                        ?>
                        <div class="treatment-item">
                            <h4><?php echo htmlspecialchars($treatment['package_name']); ?></h4>
                            <p><?php echo htmlspecialchars($treatment['description']); ?></p>
                            <div class="treatment-status status-active">
                                <i class="fas fa-circle"></i>
                                Activo
                            </div>
                            <div style="margin: 1rem 0;">
                                <small><strong>Progreso:</strong> <?php echo $treatment['sessions_completed']; ?>/<?php echo $treatment['sessions_total']; ?> sesiones (<?php echo round($progress); ?>%)</small>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                            <small><i class="fas fa-calendar"></i> Inicio: <?php echo date('d/m/Y', strtotime($treatment['start_date'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Próximas Citas -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-calendar-alt"></i>
                    Próximas Citas
                </div>
                <?php if (empty($upcoming_appointments)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-calendar-times"></i></div>
                        <h3>No tienes citas programadas</h3>
                        <p>Agenda una nueva cita desde los paquetes disponibles</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($upcoming_appointments as $appointment): ?>
                        <div class="appointment-item">
                            <h4><?php echo htmlspecialchars($appointment['package_name']); ?></h4>
                            <div class="date-time">
                                <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></span>
                            </div>
                            <div class="treatment-status status-<?php echo $appointment['status']; ?>">
                                <i class="fas fa-circle"></i>
                                <?php echo ucfirst($appointment['status']); ?>
                            </div>
                            <small><i class="fas fa-hourglass-half"></i> Duración: <?php echo $appointment['duration_minutes']; ?> minutos</small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Added recent files section -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-folder-open"></i>
                    Mis Archivos Recientes
                </div>
                <?php if (empty($recent_files)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-file-alt"></i></div>
                        <h3>No tienes archivos subidos</h3>
                        <p>Sube tu primera foto o documento</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_files as $file): ?>
                        <div class="file-item">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h4><?php echo htmlspecialchars($file['file_name']); ?></h4>
                                    <div style="margin: 0.5rem 0;">
                                        <span class="category-badge category-<?php echo $file['category']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $file['category'])); ?>
                                        </span>
                                        <small><?php echo number_format($file['file_size'] / 1024, 1); ?> KB</small>
                                    </div>
                                    <small><i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($file['created_at'])); ?></small>
                                </div>
                                <a href="<?php echo $file['file_path']; ?>" target="_blank" class="btn btn-secondary">
                                    <i class="fas fa-eye"></i>
                                    Ver
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="client_profile.php" class="btn">
                            <i class="fas fa-folder-open"></i>
                            Ver Todos los Archivos
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Paquetes Disponibles -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-gem"></i>
                    Paquetes Disponibles
                </div>
                <?php foreach ($available_packages as $package): ?>
                    <div class="package-item" onclick="openPackageModal(<?php echo htmlspecialchars(json_encode($package)); ?>)">
                        <h4 class="package-title"><?php echo htmlspecialchars($package['name']); ?></h4>
                        
                        <div class="package-description">
                            <div class="description-preview">
                                <?php echo htmlspecialchars(substr($package['description'], 0, 100)); ?>
                                <?php if (strlen($package['description']) > 100): ?>...<?php endif; ?>
                            </div>
                        </div>

                        <div class="price">$<?php echo number_format($package['price'], 2); ?></div>
                        
                        <div class="package-meta">
                            <div class="package-meta-item">
                                <i class="fas fa-list-ol"></i>
                                <span><?php echo $package['sessions_included']; ?> sesión(es)</span>
                            </div>
                            <div class="package-meta-item">
                                <i class="fas fa-clock"></i>
                                <span><?php echo $package['duration_minutes']; ?> min c/u</span>
                            </div>
                        </div>

                        <div style="text-align: center; margin-top: 1rem; padding: 0.5rem; background: rgba(4, 116, 117, 0.1); border-radius: 8px;">
                            <small style="color: #047475; font-weight: 600;">
                                <i class="fas fa-mouse-pointer"></i> Click para ver detalles completos
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Historial -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-history"></i>
                    Historial de Tratamientos
                </div>
                <?php if (empty($completed_appointments)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-history"></i></div>
                        <h3>No tienes historial aún</h3>
                        <p>Tus tratamientos completados aparecerán aquí</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($completed_appointments as $appointment): ?>
                        <div class="appointment-item">
                            <h4><?php echo htmlspecialchars($appointment['package_name']); ?></h4>
                            <div class="date-time">
                                <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?></span>
                            </div>
                            <div class="treatment-status status-completed">
                                <i class="fas fa-check-circle"></i>
                                Completado
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Added modal for package details -->
    <div id="packageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle"></h2>
                <button class="modal-close" onclick="closePackageModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-price" id="modalPrice"></div>
                
                <div class="modal-description" id="modalDescription"></div>
                
                <div class="modal-meta-grid">
                    <div class="modal-meta-item">
                        <div class="modal-meta-icon">
                            <i class="fas fa-list-ol"></i>
                        </div>
                        <div class="modal-meta-label">Sesiones</div>
                        <div class="modal-meta-value" id="modalSessions"></div>
                    </div>
                    <div class="modal-meta-item">
                        <div class="modal-meta-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="modal-meta-label">Duración</div>
                        <div class="modal-meta-value" id="modalDuration"></div>
                    </div>
                    <div class="modal-meta-item">
                        <div class="modal-meta-icon">
                            <i class="fas fa-tag"></i>
                        </div>
                        <div class="modal-meta-label">Categoría</div>
                        <div class="modal-meta-value" id="modalCategory"></div>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="closePackageModal()">
                        <i class="fas fa-times"></i>
                        Cerrar
                    </button>
                    <a href="#" id="modalBookBtn" class="btn">
                        <i class="fas fa-calendar-plus"></i>
                        Agendar Cita
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Updated JavaScript with modal functionality -->
    <script>
    function openPackageModal(packageData) {
        document.getElementById('modalTitle').textContent = packageData.name;
        document.getElementById('modalPrice').textContent = '$' + parseFloat(packageData.price).toLocaleString('es-CO', {minimumFractionDigits: 2});
        document.getElementById('modalDescription').textContent = packageData.description;
        document.getElementById('modalSessions').textContent = packageData.sessions_included + ' sesión(es)';
        document.getElementById('modalDuration').textContent = packageData.duration_minutes + ' min c/u';
        document.getElementById('modalCategory').textContent = packageData.category ? packageData.category.charAt(0).toUpperCase() + packageData.category.slice(1) : 'General';
        document.getElementById('modalBookBtn').href = 'book_appointment.php?package_id=' + packageData.id;
        
        document.getElementById('packageModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closePackageModal() {
        document.getElementById('packageModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('packageModal');
        if (event.target === modal) {
            closePackageModal();
        }
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closePackageModal();
        }
    });
    </script>
</body>
</html>
