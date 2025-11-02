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
                $treatment_id = $_POST['treatment_id'];
                $new_status = $_POST['status'];
                $stmt = $conn->prepare("UPDATE client_treatments SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $treatment_id]);
                break;
            
            case 'add_session':
                $treatment_id = $_POST['treatment_id'];
                $session_date = $_POST['session_date'];
                $notes = $_POST['notes'];
                
                $stmt = $conn->prepare("
                    INSERT INTO treatment_sessions (treatment_id, session_date, notes, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$treatment_id, $session_date, $notes]);
                break;
        }
    }
}

// Obtener todos los tratamientos
$status_filter = $_GET['status'] ?? 'all';

$query = "
    SELECT ct.*, u.full_name, u.email, tp.name as package_name, tp.sessions_included,
           COUNT(ts.id) as completed_sessions
    FROM client_treatments ct 
    JOIN users u ON ct.user_id = u.id 
    JOIN treatment_packages tp ON ct.package_id = tp.id 
    LEFT JOIN treatment_sessions ts ON ct.id = ts.treatment_id
    WHERE 1=1
";
$params = [];

if ($status_filter !== 'all') {
    $query .= " AND ct.status = ?";
    $params[] = $status_filter;
}

$query .= " GROUP BY ct.id ORDER BY ct.start_date DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMUNA - Gestionar Tratamientos</title>
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

        .filter-select {
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

        .treatments-grid {
            display: grid;
            gap: 2rem;
        }

        .treatment-card {
            background: rgba(235, 228, 199, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(4, 116, 117, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .treatment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(4, 116, 117, 0.15);
        }

        .treatment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid rgba(174, 194, 192, 0.3);
        }

        .treatment-info h3 {
            color: #047475;
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .treatment-info p {
            color: #666;
            margin-bottom: 0.25rem;
        }

        .treatment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            background: rgba(255, 255, 255, 0.5);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .detail-item h4 {
            color: #047475;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .detail-item p {
            color: #666;
            font-weight: 600;
        }

        .progress-bar {
            background: rgba(174, 194, 192, 0.3);
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .progress-fill {
            background: linear-gradient(135deg, #047475 0%, #aec2c0 100%);
            height: 100%;
            transition: width 0.3s ease;
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

        .status.active {
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            color: #22543d;
        }

        .status.completed {
            background: linear-gradient(135deg, #b2f5ea 0%, #81e6d9 100%);
            color: #234e52;
        }

        .status.paused {
            background: linear-gradient(135deg, #fbb6ce 0%, #f687b3 100%);
            color: #702459;
        }

        .treatment-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #ebe4c7;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 16px;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 8px 32px rgba(4, 116, 117, 0.3);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #047475;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #aec2c0;
            border-radius: 8px;
            font-size: 1rem;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
            
            .filters-row {
                flex-direction: column;
            }
            
            .treatment-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .treatment-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-heartbeat"></i>Gestionar Tratamientos</h1>
        <a href="admin_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Volver al Dashboard
        </a>
    </div>

    <div class="container">
        <div class="filters">
            <form method="GET" class="filters-row">
                <select name="status" class="filter-select">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Todos los estados</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Activos</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completados</option>
                    <option value="paused" <?php echo $status_filter === 'paused' ? 'selected' : ''; ?>>Pausados</option>
                </select>
                <button type="submit" class="btn">
                    <i class="fas fa-filter"></i>
                    Filtrar
                </button>
                <a href="manage_treatments.php" class="btn btn-warning">
                    <i class="fas fa-times"></i>
                    Limpiar Filtros
                </a>
            </form>
        </div>

        <div class="treatments-grid">
            <?php if (empty($treatments)): ?>
                <div class="treatment-card" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-heartbeat" style="font-size: 3rem; color: #666; margin-bottom: 1rem;"></i>
                    <h3 style="color: #666;">No se encontraron tratamientos</h3>
                    <p style="color: #999;">Intenta ajustar los filtros de búsqueda</p>
                </div>
            <?php else: ?>
                <?php foreach ($treatments as $treatment): ?>
                <div class="treatment-card">
                    <div class="treatment-header">
                        <div class="treatment-info">
                            <h3><?php echo htmlspecialchars($treatment['full_name']); ?></h3>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($treatment['email']); ?></p>
                            <p><i class="fas fa-syringe"></i> <?php echo htmlspecialchars($treatment['package_name']); ?></p>
                        </div>
                        <span class="status <?php echo $treatment['status']; ?>">
                            <i class="fas fa-circle"></i>
                            <?php echo ucfirst($treatment['status']); ?>
                        </span>
                    </div>

                    <div class="treatment-details">
                        <div class="detail-item">
                            <h4>Fecha de Inicio</h4>
                            <p><?php echo date('d/m/Y', strtotime($treatment['start_date'])); ?></p>
                        </div>
                        <div class="detail-item">
                            <h4>Sesiones Completadas</h4>
                            <p><?php echo $treatment['completed_sessions']; ?> / <?php echo $treatment['sessions_included']; ?></p>
                        </div>
                        <div class="detail-item">
                            <h4>Progreso</h4>
                            <p><?php echo round(($treatment['completed_sessions'] / $treatment['sessions_included']) * 100); ?>%</p>
                        </div>
                    </div>

                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($treatment['completed_sessions'] / $treatment['sessions_included']) * 100; ?>%"></div>
                    </div>

                    <div class="treatment-actions">
                        <?php if ($treatment['status'] === 'active'): ?>
                            <button onclick="openSessionModal(<?php echo $treatment['id']; ?>)" class="btn btn-small btn-success">
                                <i class="fas fa-plus"></i>
                                Agregar Sesión
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="treatment_id" value="<?php echo $treatment['id']; ?>">
                                <input type="hidden" name="status" value="paused">
                                <button type="submit" class="btn btn-small btn-warning">
                                    <i class="fas fa-pause"></i>
                                    Pausar
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($treatment['status'] === 'paused'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="treatment_id" value="<?php echo $treatment['id']; ?>">
                                <input type="hidden" name="status" value="active">
                                <button type="submit" class="btn btn-small btn-success">
                                    <i class="fas fa-play"></i>
                                    Reanudar
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($treatment['completed_sessions'] >= $treatment['sessions_included'] && $treatment['status'] !== 'completed'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="treatment_id" value="<?php echo $treatment['id']; ?>">
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" class="btn btn-small btn-success">
                                    <i class="fas fa-check"></i>
                                    Marcar Completado
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para agregar sesión -->
    <div id="sessionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeSessionModal()">&times;</span>
            <h2 style="color: #047475; margin-bottom: 1.5rem;">
                <i class="fas fa-plus"></i>
                Agregar Nueva Sesión
            </h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_session">
                <input type="hidden" name="treatment_id" id="modalTreatmentId">
                
                <div class="form-group">
                    <label for="session_date">Fecha de la Sesión:</label>
                    <input type="date" name="session_date" id="session_date" required>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notas de la Sesión:</label>
                    <textarea name="notes" id="notes" placeholder="Describe los detalles de la sesión..."></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 1.5rem;">
                    <button type="button" onclick="closeSessionModal()" class="btn btn-danger">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        Guardar Sesión
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openSessionModal(treatmentId) {
            document.getElementById('modalTreatmentId').value = treatmentId;
            document.getElementById('session_date').value = new Date().toISOString().split('T')[0];
            document.getElementById('sessionModal').style.display = 'block';
        }

        function closeSessionModal() {
            document.getElementById('sessionModal').style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('sessionModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
