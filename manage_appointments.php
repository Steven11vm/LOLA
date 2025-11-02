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

// Procesar acciones
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $appointment_id = $_POST['appointment_id'];
                $new_status = $_POST['status'];
                $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $appointment_id]);
                break;
            
            case 'delete_appointment':
                $appointment_id = $_POST['appointment_id'];
                $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
                $stmt->execute([$appointment_id]);
                break;
            
            case 'update_sessions':
                $appointment_id = $_POST['appointment_id'];
                $sessions = $_POST['sessions'];
                $stmt = $conn->prepare("UPDATE appointments SET sessions_planned = ? WHERE id = ?");
                $stmt->execute([$sessions, $appointment_id]);
                break;
        }
    }
}

// Obtener todas las citas
$date_filter = $_GET['date'] ?? '';
$status_filter = $_GET['status'] ?? 'pending';

$query = "
    SELECT a.*, u.full_name, u.email, tp.name as package_name, tp.price 
    FROM appointments a 
    JOIN users u ON a.user_id = u.id 
    JOIN treatment_packages tp ON a.package_id = tp.id 
    WHERE 1=1
";
$params = [];

if ($date_filter) {
    $query .= " AND a.appointment_date = ?";
    $params[] = $date_filter;
}

if ($status_filter !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMUNA - Gestionar Citas</title>
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

        .filters {
            background: rgba(235, 228, 199, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(4, 116, 117, 0.1);
        }

        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .date-input, .filter-select {
            padding: 0.75rem 1rem;
            border: 2px solid #aec2c0;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
        }

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
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(4, 116, 117, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .appointments-table {
            background: rgba(235, 228, 199, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(4, 116, 117, 0.1);
            overflow-x: auto;
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

        .status.scheduled {
            background: linear-gradient(135deg, #bee3f8 0%, #90cdf4 100%);
            color: #1a365d;
        }

        .status.completed {
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            color: #22543d;
        }

        .status.cancelled {
            background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
            color: #742a2a;
        }

        .status.confirmed {
            background: linear-gradient(135deg, #b2f5ea 0%, #81e6d9 100%);
            color: #234e52;
        }

        .status.pending {
            background: linear-gradient(135deg, #fef5e7 0%, #fed7aa 100%);
            color: #9c4221;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
            
            .filters-row {
                flex-direction: column;
            }
            
            .appointments-table {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-calendar-check"></i>Gestionar Citas</h1>
        <a href="admin_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Volver al Dashboard
        </a>
    </div>

    <div class="container">
        <div class="filters">
            <form method="GET" class="filters-row">
                <input type="date" name="date" class="date-input" value="<?php echo htmlspecialchars($date_filter); ?>">
                <select name="status" class="filter-select">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Todos los estados</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pendientes</option>
                    <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Programadas</option>
                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmadas</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completadas</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Canceladas</option>
                </select>
                <button type="submit" class="btn">
                    <i class="fas fa-filter"></i>
                    Filtrar
                </button>
                <a href="manage_appointments.php" class="btn btn-warning">
                    <i class="fas fa-times"></i>
                    Limpiar Filtros
                </a>
            </form>
        </div>

        <div class="appointments-table">
            <h2 style="color: #047475; margin-bottom: 1.5rem; font-size: 1.5rem; font-weight: 700;">
                <i class="fas fa-list"></i>
                Lista de Citas (<?php echo count($appointments); ?>)
            </h2>
            
            <?php if (empty($appointments)): ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                    <h3>No se encontraron citas</h3>
                    <p>Intenta ajustar los filtros de búsqueda</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Contacto</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Tratamiento</th>
                            <th>Precio</th>
                            <th>Sesiones</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($appointment['full_name']); ?></strong>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars($appointment['email']); ?></small>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?></td>
                            <td><?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></td>
                            <td><?php echo htmlspecialchars($appointment['package_name']); ?></td>
                            <td><strong>$<?php echo number_format($appointment['price'], 2); ?></strong></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="number" value="<?php echo $appointment['sessions_planned']; ?>" 
                                           style="width: 60px; padding: 0.25rem; border: 1px solid #aec2c0; border-radius: 4px;"
                                           onchange="updateSessions(<?php echo $appointment['id']; ?>, this.value)">
                                    <span style="font-size: 0.8rem; color: #666;">
                                        (<?php echo $appointment['sessions_completed']; ?> completadas)
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="status <?php echo $appointment['status']; ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php if ($appointment['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <input type="hidden" name="status" value="scheduled">
                                            <button type="submit" class="btn btn-small btn-success">
                                                <i class="fas fa-calendar-check"></i>
                                                Programar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($appointment['status'] === 'scheduled'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" class="btn btn-small btn-success">
                                                <i class="fas fa-check"></i>
                                                Confirmar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($appointment['status'] === 'confirmed'): ?>
                                        <button onclick="startTreatment(<?php echo $appointment['id']; ?>, <?php echo $appointment['user_id']; ?>, <?php echo $appointment['package_id']; ?>)" 
                                                class="btn btn-small" style="background: linear-gradient(135deg, #b08660 0%, #8b6914 100%);">
                                            <i class="fas fa-play"></i>
                                            Iniciar Tratamiento
                                        </button>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-small btn-success">
                                                <i class="fas fa-check-double"></i>
                                                Completar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($appointment['status'], ['scheduled', 'confirmed'])): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="btn btn-small btn-danger">
                                                <i class="fas fa-times"></i>
                                                Cancelar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta cita?')">
                                        <input type="hidden" name="action" value="delete_appointment">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                        <button type="submit" class="btn btn-small btn-danger">
                                            <i class="fas fa-trash"></i>
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function updateSessions(appointmentId, sessions) {
        fetch('update_sessions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `appointment_id=${appointmentId}&sessions=${sessions}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    function startTreatment(appointmentId, userId, packageId) {
        if (confirm('¿Deseas iniciar el tratamiento para este cliente?')) {
            fetch('start_treatment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `appointment_id=${appointmentId}&user_id=${userId}&package_id=${packageId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Tratamiento iniciado exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    }
    </script>
</body>
</html>
