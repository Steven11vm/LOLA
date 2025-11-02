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

$client_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

// Procesar acciones
if ($_POST && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'update_profile':
                if (empty($_POST['full_name']) || empty($_POST['email'])) {
                    throw new Exception("El nombre completo y el email son obligatorios.");
                }
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, birth_date = ?, emergency_contact = ?, medical_notes = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['full_name'],
                    $_POST['email'],
                    $_POST['phone'] ?? null,
                    $_POST['address'] ?? null,
                    $_POST['birth_date'] ?? null,
                    $_POST['emergency_contact'] ?? null,
                    $_POST['medical_notes'] ?? null,
                    $client_id
                ]);
                break;

            case 'upload_file':
                if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'Uploads/clients/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $file_name = $_FILES['file']['name'];
                    $file_tmp = $_FILES['file']['tmp_name'];
                    $file_size = $_FILES['file']['size'];
                    $file_type = $_FILES['file']['type'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                    $allowed_categories = ['medical_history', 'photos', 'documents', 'results'];

                    if (!in_array($file_ext, $allowed_types)) {
                        throw new Exception("Tipo de archivo no permitido.");
                    }
                    if (!in_array($_POST['category'], $allowed_categories)) {
                        throw new Exception("Categoría de archivo no válida.");
                    }

                    $new_file_name = uniqid() . '_' . $file_name;
                    $file_path = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $stmt = $conn->prepare("INSERT INTO client_files (user_id, file_name, file_path, file_type, file_size, category, description, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $client_id,
                            $file_name,
                            $file_path,
                            $file_type,
                            $file_size,
                            $_POST['category'],
                            $_POST['description'] ?? null,
                            $_SESSION['user_id']
                        ]);
                    } else {
                        throw new Exception("Error al mover el archivo subido.");
                    }
                } else {
                    throw new Exception("Error al subir el archivo.");
                }
                break;

            case 'add_treatment':
                if (empty($_POST['package_id']) || empty($_POST['treatment_description']) || empty($_POST['sessions_total'])) {
                    throw new Exception("El paquete de tratamiento, la descripción y el total de sesiones son obligatorios.");
                }
                $stmt = $conn->prepare("INSERT INTO client_treatments (user_id, package_id, sessions_completed, status, treatment_description, medical_notes, start_date, sessions_total) VALUES (?, ?, 0, 'active', ?, ?, CURDATE(), ?)");
                $stmt->execute([
                    $client_id,
                    $_POST['package_id'],
                    $_POST['treatment_description'],
                    $_POST['medical_notes'] ?? null,
                    $_POST['sessions_total']
                ]);
                break;

            case 'update_treatment_sessions':
                if (!isset($_POST['sessions_completed']) || !isset($_POST['total_sessions']) || !isset($_POST['treatment_id'])) {
                    throw new Exception("Datos de sesiones incompletos.");
                }
                $stmt = $conn->prepare("UPDATE client_treatments SET sessions_completed = ?, status = ?, medical_notes = ? WHERE id = ?");
                $status = ($_POST['sessions_completed'] >= $_POST['total_sessions']) ? 'completed' : 'active';
                $stmt->execute([
                    $_POST['sessions_completed'],
                    $status,
                    $_POST['medical_notes'] ?? null,
                    $_POST['treatment_id']
                ]);
                break;

            case 'add_treatment_note':
                if (empty($_POST['note'])) {
                    throw new Exception("La nota es obligatoria.");
                }
                $stmt = $conn->prepare("INSERT INTO treatment_notes (user_id, note_content, note_type, created_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $client_id,
                    $_POST['note'],
                    $_POST['note_type'] ?? 'general',
                    $_SESSION['user_id']
                ]);
                break;

            case 'edit_treatment':
                if (empty($_POST['treatment_description']) || empty($_POST['sessions_total']) || empty($_POST['treatment_id'])) {
                    throw new Exception("La descripción del tratamiento, el total de sesiones y el ID son obligatorios.");
                }
                $stmt = $conn->prepare("UPDATE client_treatments SET treatment_description = ?, medical_notes = ?, sessions_total = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['treatment_description'],
                    $_POST['medical_notes'] ?? null,
                    $_POST['sessions_total'],
                    $_POST['treatment_id']
                ]);
                break;

            case 'update_appointment_status':
                if (empty($_POST['status']) || empty($_POST['appointment_id'])) {
                    throw new Exception("El estado y el ID de la cita son obligatorios.");
                }
                $stmt = $conn->prepare("UPDATE appointments SET status = ?, therapist_notes = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['status'],
                    $_POST['therapist_notes'] ?? null,
                    $_POST['appointment_id']
                ]);
                break;
        }
        // Redirect to avoid form resubmission
        header("Location: client_profile_admin.php?id=$client_id");
        exit();
    } catch (Exception $e) {
        error_log("Error en acción {$_POST['action']}: " . $e->getMessage());
        echo "<div style='background: #fee2e2; color: #dc2626; padding: 1rem; margin: 1rem; border-radius: 8px;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Obtener información del cliente
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        header('Location: manage_users.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error al obtener cliente: " . $e->getMessage());
    echo "<div style='background: #fee2e2; color: #dc2626; padding: 1rem; margin: 1rem; border-radius: 8px;'>Error al cargar el perfil del cliente.</div>";
    exit();
}

// Obtener tratamientos activos
try {
    $stmt = $conn->prepare("
        SELECT ct.*, tp.name as package_name, tp.sessions_included, tp.price 
        FROM client_treatments ct 
        JOIN treatment_packages tp ON ct.package_id = tp.id 
        WHERE ct.user_id = ? 
        ORDER BY ct.created_at DESC
    ");
    $stmt->execute([$client_id]);
    $treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener tratamientos: " . $e->getMessage());
    $treatments = [];
}

// Obtener citas
try {
    $stmt = $conn->prepare("
        SELECT a.*, tp.name as package_name 
        FROM appointments a 
        JOIN treatment_packages tp ON a.package_id = tp.id 
        WHERE a.user_id = ? 
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([$client_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener citas: " . $e->getMessage());
    $appointments = [];
}

// Obtener archivos
try {
    $stmt = $conn->prepare("SELECT * FROM client_files WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$client_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener archivos: " . $e->getMessage());
    $files = [];
}

// Obtener notas de tratamiento
try {
    $stmt = $conn->prepare("
        SELECT tn.*, u.full_name as created_by_name
        FROM treatment_notes tn 
        LEFT JOIN users u ON tn.created_by = u.id 
        WHERE tn.user_id = ? 
        ORDER BY tn.created_at DESC
    ");
    $stmt->execute([$client_id]);
    $treatment_notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener notas de tratamiento: " . $e->getMessage());
    $treatment_notes = [];
}

// Obtener paquetes de tratamiento disponibles
try {
    $stmt = $conn->prepare("SELECT * FROM treatment_packages ORDER BY name");
    $stmt->execute();
    $available_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error al obtener paquetes de tratamiento: " . $e->getMessage());
    $available_packages = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMUNA - Perfil de Cliente</title>
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

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background: rgba(235, 228, 199, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(4, 116, 117, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #047475;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #aec2c0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #047475;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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
            font-size: 0.9rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(4, 116, 117, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

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
        }

        .table tbody tr:hover {
            background: rgba(174, 194, 192, 0.1);
        }

        .status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status.active {
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            color: #22543d;
        }

        .status.pending {
            background: linear-gradient(135deg, #fef5e7 0%, #f6e05e 100%);
            color: #744210;
        }

        .status.completed {
            background: linear-gradient(135deg, #b2f5ea 0%, #81e6d9 100%);
            color: #234e52;
        }

        .status.cancelled {
            background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
            color: #742a2a;
        }

        .status.no_show {
            background: linear-gradient(135deg, #ffcccc 0%, #ff9999 100%);
            color: #990000;
        }

        .file-upload {
            border: 2px dashed #aec2c0;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .file-upload:hover {
            border-color: #047475;
            background: rgba(4, 116, 117, 0.05);
        }

        .file-list {
            display: grid;
            gap: 1rem;
            margin-top: 1rem;
        }

        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 8px;
            border: 1px solid rgba(174, 194, 192, 0.3);
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .file-icon {
            font-size: 1.5rem;
            color: #047475;
        }

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
            background: rgba(235, 228, 199, 0.98);
            margin: 5% auto;
            padding: 2rem;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(4, 116, 117, 0.3);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #047475;
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-user-edit"></i>Perfil de Cliente - <?php echo htmlspecialchars($client['full_name']); ?></h1>
        <a href="manage_users.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Volver a Usuarios
        </a>
    </div>

    <div class="container">
        <div class="profile-grid">
            <!-- Información Personal -->
            <div class="card">
                <h2><i class="fas fa-user"></i>Información Personal</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label>Nombre Completo</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($client['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($client['address'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Fecha de Nacimiento</label>
                        <input type="date" name="birth_date" value="<?php echo $client['birth_date'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Contacto de Emergencia</label>
                        <input type="text" name="emergency_contact" value="<?php echo htmlspecialchars($client['emergency_contact'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Notas Médicas</label>
                        <textarea name="medical_notes" placeholder="Alergias, condiciones médicas, medicamentos..."><?php echo htmlspecialchars($client['medical_notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        Guardar Cambios
                    </button>
                </form>
            </div>

            <!-- Subir Archivos -->
            <div class="card">
                <h2><i class="fas fa-cloud-upload-alt"></i>Subir Archivos</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_file">
                    
                    <div class="form-group">
                        <label>Categoría</label>
                        <select name="category" required>
                            <option value="">Seleccionar categoría</option>
                            <option value="medical_history">Historial Médico</option>
                            <option value="photos">Fotos</option>
                            <option value="documents">Documentos</option>
                            <option value="results">Resultados</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Descripción</label>
                        <input type="text" name="description" placeholder="Descripción del archivo">
                    </div>
                    
                    <div class="file-upload">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #047475; margin-bottom: 1rem;"></i>
                        <p>Selecciona un archivo para subir</p>
                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required style="margin-top: 1rem;">
                    </div>
                    
                    <button type="submit" class="btn" style="margin-top: 1rem;">
                        <i class="fas fa-upload"></i>
                        Subir Archivo
                    </button>
                </form>
            </div>

            <!-- Crear Tratamiento -->
            <div class="card">
                <h2><i class="fas fa-plus-square"></i>Crear Tratamiento</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_treatment">
                    
                    <div class="form-group">
                        <label>Paquete de Tratamiento</label>
                        <select name="package_id" required style="font-size: 0.9rem;">
                            <option value="">Seleccionar paquete</option>
                            <?php foreach ($available_packages as $package): ?>
                            <option value="<?php echo $package['id']; ?>">
                                <?php echo htmlspecialchars(substr($package['name'], 0, 25)); ?><?php echo strlen($package['name']) > 25 ? '...' : ''; ?> - 
                                $<?php echo number_format($package['price'], 0); ?> 
                                (<?php echo $package['sessions_included']; ?> sesiones)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #666; font-size: 0.8rem;">Paquetes disponibles compactos</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Descripción del Tratamiento</label>
                        <textarea name="treatment_description" required placeholder="Plan específico..." style="min-height: 80px;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Notas Médicas</label>
                        <textarea name="medical_notes" placeholder="Condiciones relevantes..." style="min-height: 60px;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Total de Sesiones</label>
                        <input type="number" name="sessions_total" min="1" required value="1">
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i>
                        Crear Tratamiento
                    </button>
                </form>
            </div>
        </div>

        <!-- Tratamientos Activos -->
        <div class="card">
            <h2><i class="fas fa-syringe"></i>Tratamientos Activos
                <button onclick="openTreatmentModal()" class="btn btn-success btn-small" style="float: right;">
                    <i class="fas fa-plus"></i>
                    Nuevo Tratamiento
                </button>
            </h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Tratamiento</th>
                        <th>Descripción</th>
                        <th>Sesiones</th>
                        <th>Progreso</th>
                        <th>Estado</th>
                        <th>Fecha Inicio</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($treatments)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem;">
                            <i class="fas fa-syringe" style="font-size: 2rem; margin-bottom: 1rem; display: block; color: #666;"></i>
                            No hay tratamientos activos
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($treatments as $treatment): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($treatment['package_name']); ?></strong></td>
                        <td style="max-width: 200px;">
                            <div style="max-height: 60px; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars($treatment['treatment_description'] ?? 'Sin descripción'); ?>
                            </div>
                        </td>
                        <td><?php echo $treatment['sessions_completed']; ?>/<?php echo $treatment['sessions_total']; ?></td>
                        <td>
                            <div style="background: #e2e8f0; border-radius: 10px; height: 8px; overflow: hidden;">
                                <div style="background: #047475; height: 100%; width: <?php echo ($treatment['sessions_total'] > 0 ? ($treatment['sessions_completed'] / $treatment['sessions_total']) * 100 : 0); ?>%;"></div>
                            </div>
                            <small><?php echo ($treatment['sessions_total'] > 0 ? round(($treatment['sessions_completed'] / $treatment['sessions_total']) * 100) : 0); ?>%</small>
                        </td>
                        <td><span class="status <?php echo $treatment['status']; ?>"><?php echo ucfirst($treatment['status']); ?></span></td>
                        <td><?php echo date('d/m/Y', strtotime($treatment['start_date'])); ?></td>
                        <td>
                            <button onclick="openSessionModal(<?php echo $treatment['id']; ?>, <?php echo $treatment['sessions_completed']; ?>, <?php echo $treatment['sessions_total']; ?>, '<?php echo addslashes($treatment['medical_notes'] ?? ''); ?>')" class="btn btn-small">
                                <i class="fas fa-edit"></i>
                                Sesiones
                            </button>
                            <button onclick="openEditTreatmentModal(<?php echo $treatment['id']; ?>, '<?php echo addslashes($treatment['treatment_description'] ?? ''); ?>', '<?php echo addslashes($treatment['medical_notes'] ?? ''); ?>', <?php echo $treatment['sessions_total']; ?>)" class="btn btn-small" style="background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);">
                                <i class="fas fa-pen"></i>
                                Editar
                            </button>
                            <button onclick="openNoteModal(<?php echo $treatment['id']; ?>)" class="btn btn-small" style="background: #b08660;">
                                <i class="fas fa-sticky-note"></i>
                                Nota
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Historial de Citas -->
        <div class="card">
            <h2><i class="fas fa-calendar-alt"></i>Historial de Citas</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Tratamiento</th>
                        <th>Estado</th>
                        <th>Notas</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem;">
                            <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem; display: block; color: #666;"></i>
                            No hay citas registradas
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?></td>
                        <td><?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></td>
                        <td><?php echo htmlspecialchars($appointment['package_name']); ?></td>
                        <td><span class="status <?php echo $appointment['status']; ?>"><?php echo ucfirst($appointment['status']); ?></span></td>
                        <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($appointment['notes'] ?? 'Sin notas'); ?></td>
                        <td>
                            <button onclick="openAppointmentModal(<?php echo $appointment['id']; ?>, '<?php echo $appointment['status']; ?>', '<?php echo addslashes($appointment['therapist_notes'] ?? ''); ?>')" class="btn btn-small">
                                <i class="fas fa-edit"></i>
                                Estado
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Archivos del Cliente -->
        <div class="card">
            <h2><i class="fas fa-folder-open"></i>Archivos del Cliente</h2>
            <div class="file-list">
                <?php if (empty($files)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-folder-open" style="font-size: 2rem; margin-bottom: 1rem; display: block; color: #666;"></i>
                        <p>No hay archivos subidos</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($files as $file): ?>
                    <div class="file-item">
                        <div class="file-info">
                            <i class="fas fa-file-<?php echo strpos($file['file_type'], 'image') !== false ? 'image' : (strpos($file['file_type'], 'pdf') !== false ? 'pdf' : 'alt'); ?> file-icon"></i>
                            <div>
                                <strong><?php echo htmlspecialchars($file['file_name']); ?></strong>
                                <p style="color: #666; font-size: 0.9rem;">
                                    <?php echo ucfirst($file['category']); ?> - 
                                    <?php echo date('d/m/Y H:i', strtotime($file['created_at'])); ?>
                                </p>
                                <?php if ($file['description']): ?>
                                    <p style="color: #666; font-size: 0.8rem;"><?php echo htmlspecialchars($file['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <a href="<?php echo $file['file_path']; ?>" target="_blank" class="btn btn-small">
                                <i class="fas fa-eye"></i>
                                Ver
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notas de Tratamiento -->
        <div class="card">
            <h2><i class="fas fa-sticky-note"></i>Notas de Tratamiento</h2>
            <?php if (empty($treatment_notes)): ?>
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-sticky-note" style="font-size: 2rem; margin-bottom: 1rem; display: block; color: #666;"></i>
                    <p>No hay notas de tratamiento</p>
                </div>
            <?php else: ?>
                <?php foreach ($treatment_notes as $note): ?>
                <div style="border-left: 4px solid #047475; padding: 1rem; margin-bottom: 1rem; background: rgba(255, 255, 255, 0.3); border-radius: 0 8px 8px 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <strong><?php echo ucfirst($note['note_type'] ?? 'Nota General'); ?></strong>
                        <small style="color: #666;">
                            <?php echo htmlspecialchars($note['created_by_name']); ?> - 
                            <?php echo date('d/m/Y H:i', strtotime($note['created_at'])); ?>
                        </small>
                    </div>
                    <p><?php echo nl2br(htmlspecialchars($note['note_content'])); ?></p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para agregar tratamiento -->
    <div id="treatmentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeTreatmentModal()">&times;</span>
            <h2><i class="fas fa-syringe"></i>Agregar Nuevo Tratamiento</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_treatment">
                
                <div class="form-group">
                    <label>Paquete de Tratamiento</label>
                    <select name="package_id" required>
                        <option value="">Seleccionar paquete</option>
                        <?php foreach ($available_packages as $package): ?>
                        <option value="<?php echo $package['id']; ?>">
                            <?php echo htmlspecialchars(substr($package['name'], 0, 25)); ?><?php echo strlen($package['name']) > 25 ? '...' : ''; ?> - 
                            $<?php echo number_format($package['price'], 0); ?> 
                            (<?php echo $package['sessions_included']; ?> sesiones)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #666; font-size: 0.8rem;">Paquetes disponibles compactos</small>
                </div>
                
                <div class="form-group">
                    <label>Descripción del Tratamiento</label>
                    <textarea name="treatment_description" required placeholder="Describe el plan de tratamiento específico para este cliente..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Notas Médicas Iniciales</label>
                    <textarea name="medical_notes" placeholder="Condiciones médicas relevantes, contraindicaciones, objetivos del tratamiento..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Total de Sesiones</label>
                    <input type="number" name="sessions_total" min="1" required value="1">
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Crear Tratamiento
                </button>
            </form>
        </div>
    </div>

    <!-- Modal para gestionar sesiones -->
    <div id="sessionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeSessionModal()">&times;</span>
            <h2><i class="fas fa-edit"></i>Gestionar Sesiones de Tratamiento</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_treatment_sessions">
                <input type="hidden" name="treatment_id" id="session_treatment_id">
                <input type="hidden" name="total_sessions" id="session_total_sessions">
                
                <div class="form-group">
                    <label>Sesiones Completadas</label>
                    <input type="number" name="sessions_completed" id="session_completed" min="0" required>
                    <small>Sesiones totales: <span id="session_total_display"></span></small>
                </div>
                
                <div class="form-group">
                    <label>Notas Médicas del Tratamiento</label>
                    <textarea name="medical_notes" id="session_medical_notes" placeholder="Progreso del paciente, reacciones, observaciones médicas..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Estado del Tratamiento</label>
                    <div style="padding: 1rem; background: rgba(4, 116, 117, 0.1); border-radius: 8px; margin-top: 0.5rem;">
                        <p><strong>Progreso:</strong> <span id="progress_text"></span></p>
                        <div style="background: #e2e8f0; border-radius: 10px; height: 12px; overflow: hidden; margin-top: 0.5rem;">
                            <div id="progress_bar" style="background: #047475; height: 100%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Actualizar Sesiones
                </button>
            </form>
        </div>
    </div>

    <!-- Modal para agregar notas -->
    <div id="noteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeNoteModal()">&times;</span>
            <h2><i class="fas fa-sticky-note"></i>Agregar Nota de Tratamiento</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_treatment_note">
                <input type="hidden" name="treatment_id" id="note_treatment_id">
                
                <div class="form-group">
                    <label>Tipo de Nota</label>
                    <select name="note_type" required>
                        <option value="general">General</option>
                        <option value="medical">Médica</option>
                        <option value="treatment">Tratamiento</option>
                        <option value="observation">Observación</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Nota</label>
                    <textarea name="note" required placeholder="Escribe la nota del tratamiento..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Guardar Nota
                </button>
            </form>
        </div>
    </div>

    <!-- Modal para editar tratamiento -->
    <div id="editTreatmentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditTreatmentModal()">&times;</span>
            <h2><i class="fas fa-pen"></i>Editar Tratamiento</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_treatment">
                <input type="hidden" name="treatment_id" id="edit_treatment_id">
                
                <div class="form-group">
                    <label>Descripción del Tratamiento</label>
                    <textarea name="treatment_description" id="edit_treatment_description" required placeholder="Describe el plan de tratamiento específico para este cliente..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Notas Médicas</label>
                    <textarea name="medical_notes" id="edit_medical_notes" placeholder="Condiciones médicas relevantes, contraindicaciones, objetivos del tratamiento..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Total de Sesiones</label>
                    <input type="number" name="sessions_total" id="edit_sessions_total" min="1" required>
                    <small>Ajusta el número total de sesiones para este tratamiento</small>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Guardar Cambios
                </button>
            </form>
        </div>
    </div>

    <!-- Modal para cambiar estado de cita -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAppointmentModal()">&times;</span>
            <h2><i class="fas fa-calendar-check"></i>Cambiar Estado de Cita</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_appointment_status">
                <input type="hidden" name="appointment_id" id="appointment_id">
                
                <div class="form-group">
                    <label>Estado de la Cita</label>
                    <select name="status" id="appointment_status" required>
                        <option value="pending">Pendiente</option>
                        <option value="scheduled">Programada</option>
                        <option value="confirmed">Confirmada</option>
                        <option value="completed">Completada</option>
                        <option value="cancelled">Cancelada</option>
                        <option value="no_show">No se presentó</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Notas del Terapeuta</label>
                    <textarea name="therapist_notes" id="therapist_notes" placeholder="Observaciones sobre la cita, tratamiento realizado, reacciones del paciente..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Actualizar Estado
                </button>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        function openTreatmentModal() {
            document.getElementById('treatmentModal').style.display = 'block';
        }

        function closeTreatmentModal() {
            document.getElementById('treatmentModal').style.display = 'none';
        }

        function openEditTreatmentModal(treatmentId, description, medicalNotes, sessionsTotal) {
            document.getElementById('editTreatmentModal').style.display = 'block';
            document.getElementById('edit_treatment_id').value = treatmentId;
            document.getElementById('edit_treatment_description').value = description;
            document.getElementById('edit_medical_notes').value = medicalNotes;
            document.getElementById('edit_sessions_total').value = sessionsTotal;
        }

        function closeEditTreatmentModal() {
            document.getElementById('editTreatmentModal').style.display = 'none';
        }

        function openSessionModal(treatmentId, completed, total, medicalNotes) {
            document.getElementById('sessionModal').style.display = 'block';
            document.getElementById('session_treatment_id').value = treatmentId;
            document.getElementById('session_completed').value = completed;
            document.getElementById('session_total_sessions').value = total;
            document.getElementById('session_total_display').textContent = total;
            document.getElementById('session_medical_notes').value = medicalNotes;
            updateProgress(completed, total);
            
            // Update progress when sessions change
            document.getElementById('session_completed').addEventListener('input', function() {
                updateProgress(this.value, total);
            });
        }

        function closeSessionModal() {
            document.getElementById('sessionModal').style.display = 'none';
        }

        function openNoteModal(treatmentId = null) {
            document.getElementById('noteModal').style.display = 'block';
            if (treatmentId) {
                document.getElementById('note_treatment_id').value = treatmentId;
            }
        }

        function closeNoteModal() {
            document.getElementById('noteModal').style.display = 'none';
        }

        function openAppointmentModal(appointmentId, currentStatus, therapistNotes) {
            document.getElementById('appointmentModal').style.display = 'block';
            document.getElementById('appointment_id').value = appointmentId;
            document.getElementById('appointment_status').value = currentStatus;
            document.getElementById('therapist_notes').value = therapistNotes;
        }

        function closeAppointmentModal() {
            document.getElementById('appointmentModal').style.display = 'none';
        }

        function updateProgress(completed, total) {
            const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;
            document.getElementById('progress_text').textContent = `${completed}/${total} sesiones (${percentage}%)`;
            document.getElementById('progress_bar').style.width = percentage + '%';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['treatmentModal', 'sessionModal', 'noteModal', 'editTreatmentModal', 'appointmentModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>