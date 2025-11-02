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

// Obtener estadísticas para reportes
$stats = [];

// Estadísticas generales
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM appointments");
$stats['total_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM client_treatments");
$stats['total_treatments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Ingresos por mes (últimos 6 meses)
$monthly_revenue = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(tp.price), 0) as revenue 
        FROM appointments a 
        JOIN treatment_packages tp ON a.package_id = tp.id 
        WHERE DATE_FORMAT(a.appointment_date, '%Y-%m') = ? 
        AND a.status = 'completed'
    ");
    $stmt->execute([$month]);
    $revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'];
    $monthly_revenue[] = [
        'month' => date('M Y', strtotime($month . '-01')),
        'revenue' => $revenue
    ];
}

// Tratamientos más populares
$stmt = $conn->query("
    SELECT tp.name, COUNT(a.id) as bookings, SUM(tp.price) as revenue
    FROM treatment_packages tp
    LEFT JOIN appointments a ON tp.id = a.package_id
    GROUP BY tp.id, tp.name
    ORDER BY bookings DESC
    LIMIT 5
");
$popular_treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estados de citas
$stmt = $conn->query("
    SELECT status, COUNT(*) as count
    FROM appointments
    GROUP BY status
");
$appointment_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Usuarios registrados por mes (últimos 6 meses)
$user_registrations = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ? 
        AND role = 'user'
    ");
    $stmt->execute([$month]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $user_registrations[] = [
        'month' => date('M Y', strtotime($month . '-01')),
        'count' => $count
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMUNA - Reportes y Estadísticas</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(4, 116, 117, 0.3);
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .back-btn {
            background: #b08660;
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

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(176, 134, 96, 0.4);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .chart-card {
            background: rgba(235, 228, 199, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(4, 116, 117, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .chart-card h2 {
            color: #047475;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .table-card {
            background: rgba(235, 228, 199, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(4, 116, 117, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 2rem;
        }

        .table-card h2 {
            color: #047475;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
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
        }

        .table tbody tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.1);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-chart-line"></i>Reportes y Estadísticas</h1>
        <a href="admin_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Volver al Dashboard
        </a>
    </div>

    <div class="container">
        <!-- Estadísticas Generales -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3><?php echo $stats['total_users']; ?></h3>
                <p>Total de Usuarios</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar-check"></i>
                <h3><?php echo $stats['total_appointments']; ?></h3>
                <p>Total de Citas</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-heartbeat"></i>
                <h3><?php echo $stats['total_treatments']; ?></h3>
                <p>Total de Tratamientos</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-dollar-sign"></i>
                <h3>$<?php echo number_format(array_sum(array_column($monthly_revenue, 'revenue')), 2); ?></h3>
                <p>Ingresos Totales (6 meses)</p>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="charts-grid">
            <div class="chart-card">
                <h2><i class="fas fa-chart-bar"></i>Ingresos Mensuales</h2>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h2><i class="fas fa-user-plus"></i>Registros de Usuarios</h2>
                <div class="chart-container">
                    <canvas id="usersChart"></canvas>
                </div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h2><i class="fas fa-pie-chart"></i>Estados de Citas</h2>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h2><i class="fas fa-trophy"></i>Tratamientos Populares</h2>
                <div class="chart-container">
                    <canvas id="treatmentsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabla de Tratamientos Populares -->
        <div class="table-card">
            <h2><i class="fas fa-star"></i>Tratamientos Más Populares</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Tratamiento</th>
                        <th>Reservas</th>
                        <th>Ingresos Generados</th>
                        <th>Promedio por Reserva</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($popular_treatments as $treatment): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($treatment['name']); ?></strong></td>
                        <td><?php echo $treatment['bookings']; ?></td>
                        <td><strong>$<?php echo number_format($treatment['revenue'], 2); ?></strong></td>
                        <td>$<?php echo $treatment['bookings'] > 0 ? number_format($treatment['revenue'] / $treatment['bookings'], 2) : '0.00'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Configuración de colores
        const colors = {
            primary: '#047475',
            secondary: '#aec2c0',
            accent: '#b08660',
            background: '#ebe4c7'
        };

        // Gráfico de Ingresos Mensuales
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_revenue, 'month')); ?>,
                datasets: [{
                    label: 'Ingresos ($)',
                    data: <?php echo json_encode(array_column($monthly_revenue, 'revenue')); ?>,
                    borderColor: colors.primary,
                    backgroundColor: colors.primary + '20',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de Registros de Usuarios
        const usersCtx = document.getElementById('usersChart').getContext('2d');
        new Chart(usersCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($user_registrations, 'month')); ?>,
                datasets: [{
                    label: 'Nuevos Usuarios',
                    data: <?php echo json_encode(array_column($user_registrations, 'count')); ?>,
                    backgroundColor: colors.secondary,
                    borderColor: colors.primary,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Gráfico de Estados de Citas
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($appointment_status, 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($appointment_status, 'count')); ?>,
                    backgroundColor: [
                        colors.primary,
                        colors.secondary,
                        colors.accent,
                        '#e53e3e',
                        '#38a169'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfico de Tratamientos Populares
        const treatmentsCtx = document.getElementById('treatmentsChart').getContext('2d');
        new Chart(treatmentsCtx, {
            type: 'horizontalBar',
            data: {
                labels: <?php echo json_encode(array_column($popular_treatments, 'name')); ?>,
                datasets: [{
                    label: 'Reservas',
                    data: <?php echo json_encode(array_column($popular_treatments, 'bookings')); ?>,
                    backgroundColor: colors.accent,
                    borderColor: colors.primary,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
