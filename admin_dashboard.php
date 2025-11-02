<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está logueado y es admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Obtener estadísticas generales
$stats = [];

// Total de usuarios
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de citas hoy
$stmt = $conn->query("SELECT COUNT(*) as total FROM appointments WHERE appointment_date = CURDATE()");
$stats['appointments_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de tratamientos activos
$stmt = $conn->query("SELECT COUNT(*) as total FROM client_treatments WHERE status = 'active'");
$stats['active_treatments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Ingresos del mes
$stmt = $conn->query("SELECT SUM(tp.price) as total FROM appointments a JOIN treatment_packages tp ON a.package_id = tp.id WHERE MONTH(a.appointment_date) = MONTH(CURDATE()) AND a.status = 'completed'");
$stats['monthly_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Obtener próximas citas
$stmt = $conn->prepare("
    SELECT a.*, u.full_name, tp.name as package_name 
    FROM appointments a 
    JOIN users u ON a.user_id = u.id 
    JOIN treatment_packages tp ON a.package_id = tp.id 
    WHERE a.appointment_date >= CURDATE() 
    ORDER BY a.appointment_date, a.appointment_time 
    LIMIT 10
");
$stmt->execute();
$upcoming_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener paquetes de tratamiento
$stmt = $conn->query("SELECT * FROM treatment_packages ORDER BY name");
$packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener usuarios recientes
$stmt = $conn->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 5");
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMUNA - Dashboard Administrador</title>
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
            line-height: 1.6;
        }

        /* Enhanced header with better typography and spacing */
        .header {
            background: linear-gradient(135deg, #047475 0%, #035859 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(4, 116, 117, 0.3);
            backdrop-filter: blur(10px);
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header h1 i {
            font-size: 1.8rem;
            color: #ebe4c7;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            font-weight: 500;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #ebe4c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #047475;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .logout-btn {
            background: linear-gradient(135deg, #b08660 0%, #9a7555 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(176, 134, 96, 0.4);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2.5rem;
        }

        /* Enhanced stats grid with better visual hierarchy */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: rgba(235, 228, 199, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(4, 116, 117, 0.1);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #047475, #b08660);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(4, 116, 117, 0.2);
        }

        .stat-card i {
            font-size: 2.5rem;
            color: #047475;
            margin-bottom: 1rem;
        }

        .stat-card h3 {
            color: #047475;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: #b08660;
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Improved dashboard grid with better spacing */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2.5rem;
            margin-bottom: 3rem;
        }

        .card {
            background: rgba(235, 228, 199, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(4, 116, 117, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(4, 116, 117, 0.15);
        }

        .card h2 {
            color: #047475;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Enhanced table styling with better readability */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(4, 116, 117, 0.1);
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(174, 194, 192, 0.3);
        }

        .table th {
            background: linear-gradient(135deg, #047475 0%, #035859 100%);
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background: rgba(174, 194, 192, 0.1);
            transform: scale(1.01);
        }

        .table tbody tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Enhanced button styles with better visual feedback */
        .btn {
            background: linear-gradient(135deg, #047475 0%, #aec2c0 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(4, 116, 117, 0.3);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .btn-danger {
            background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
        }

        /* Enhanced status badges with better visual distinction */
        .status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .status.active {
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            color: #22543d;
        }

        .status.scheduled {
            background: linear-gradient(135deg, #bee3f8 0%, #90cdf4 100%);
            color: #1a365d;
        }

        .status.completed {
            background: linear-gradient(135deg, #b2f5ea 0%, #81e6d9 100%);
            color: #234e52;
        }

        .status.inactive {
            background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
            color: #742a2a;
        }

        /* Enhanced action cards with better hover effects */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .action-card {
            background: rgba(235, 228, 199, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(4, 116, 117, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #047475, #b08660);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .action-card:hover::before {
            transform: scaleX(1);
        }

        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(4, 116, 117, 0.2);
        }

        .action-card i {
            font-size: 3rem;
            color: #047475;
            margin-bottom: 1rem;
        }

        .action-card h3 {
            color: #047475;
            margin-bottom: 1rem;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .action-card p {
            color: #4a5568;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        /* Enhanced responsive design */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 1.5rem;
            }
            
            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .actions-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        /* Added loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-hospital-alt"></i>EMUNA - Dashboard Administrador</h1>
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
            <span>Bienvenido, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Cerrar Sesión
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Enhanced statistics with icons -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3><?php echo $stats['total_users']; ?></h3>
                <p>Clientes Registrados</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar-day"></i>
                <h3><?php echo $stats['appointments_today']; ?></h3>
                <p>Citas Hoy</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-syringe"></i>
                <h3><?php echo $stats['active_treatments']; ?></h3>
                <p>Tratamientos Activos</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <h3>$<?php echo number_format($stats['monthly_revenue'], 2); ?></h3>
                <p>Ingresos del Mes</p>
            </div>
        </div>

        <!-- Dashboard Principal -->
        <div class="dashboard-grid">
            <!-- Próximas Citas -->
            <div class="card">
                <h2><i class="fas fa-calendar-alt"></i>Próximas Citas</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Tratamiento</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($upcoming_appointments)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #666; padding: 2rem;">
                                <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                No hay citas programadas
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($upcoming_appointments as $appointment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($appointment['full_name']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?></td>
                            <td><?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></td>
                            <td><?php echo htmlspecialchars($appointment['package_name']); ?></td>
                            <td><span class="status <?php echo $appointment['status']; ?>">
                                <i class="fas fa-circle"></i>
                                <?php echo ucfirst($appointment['status']); ?>
                            </span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Usuarios Recientes -->
            <div class="card">
                <h2><i class="fas fa-user-plus"></i>Usuarios Recientes</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Fecha Registro</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_users)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #666; padding: 2rem;">
                                <i class="fas fa-user-slash" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                No hay usuarios recientes
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recent_users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                            <td><span class="status <?php echo $user['status']; ?>">
                                <i class="fas fa-circle"></i>
                                <?php echo ucfirst($user['status']); ?>
                            </span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paquetes de Tratamiento -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2><i class="fas fa-box"></i>Paquetes de Tratamiento</h2>
                <a href="manage_packages.php" class="btn">
                    <i class="fas fa-plus"></i>
                    Gestionar Paquetes
                </a>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Precio</th>
                        <th>Sesiones</th>
                        <th>Duración</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($packages)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #666; padding: 2rem;">
                            <i class="fas fa-box-open" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                            No hay paquetes de tratamiento disponibles
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($packages as $package): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($package['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars(substr($package['description'], 0, 50)) . '...'; ?></td>
                        <td><strong>$<?php echo number_format($package['price'], 2); ?></strong></td>
                        <td><?php echo $package['sessions_included']; ?></td>
                        <td><?php echo $package['duration_minutes']; ?> min</td>
                        <td><span class="status <?php echo $package['is_active'] ? 'active' : 'inactive'; ?>">
                            <i class="fas fa-circle"></i>
                            <?php echo $package['is_active'] ? 'Activo' : 'Inactivo'; ?>
                        </span></td>
                        <td>
                            <a href="edit_package.php?id=<?php echo $package['id']; ?>" class="btn btn-small">
                                <i class="fas fa-edit"></i>
                                Editar
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Enhanced action cards with better icons and descriptions -->
        <div class="actions-grid">
            <div class="action-card">
                <i class="fas fa-users-cog"></i>
                <h3>Gestionar Usuarios</h3>
                <p>Ver, editar y administrar cuentas de usuarios del sistema</p>
                <a href="manage_users.php" class="btn">
                    <i class="fas fa-arrow-right"></i>
                    Ir a Usuarios
                </a>
            </div>
            <div class="action-card">
                <i class="fas fa-calendar-check"></i>
                <h3>Ver Todas las Citas</h3>
                <p>Administrar calendario completo y citas programadas</p>
                <a href="manage_appointments.php" class="btn">
                    <i class="fas fa-arrow-right"></i>
                    Ver Citas
                </a>
            </div>
            <div class="action-card">
                <i class="fas fa-heartbeat"></i>
                <h3>Gestionar Tratamientos</h3>
                <p>Administrar tratamientos activos y historial de clientes</p>
                <a href="manage_treatments.php" class="btn">
                    <i class="fas fa-arrow-right"></i>
                    Ver Tratamientos
                </a>
            </div>
            <div class="action-card">
                <i class="fas fa-chart-line"></i>
                <h3>Reportes y Estadísticas</h3>
                <p>Ver informes detallados y análisis del sistema</p>
                <a href="reports.php" class="btn">
                    <i class="fas fa-arrow-right"></i>
                    Ver Reportes
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh stats every 30 seconds
            setInterval(function() {
                // You can implement AJAX refresh here if needed
                console.log('[v0] Stats refresh interval triggered');
            }, 30000);

            // Add loading states to buttons
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (!this.classList.contains('loading')) {
                        this.innerHTML = '<div class="loading"></div> Cargando...';
                        this.classList.add('loading');
                    }
                });
            });
        });
    </script>
</body>
</html>
