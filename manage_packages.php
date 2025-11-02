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

// Procesar formulario de nuevo paquete
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_package') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $sessions = intval($_POST['sessions']);
    $duration = intval($_POST['duration']);
    $category = trim($_POST['category']);
    
    if (!empty($name) && $price > 0 && $sessions > 0 && $duration > 0) {
        $stmt = $conn->prepare("INSERT INTO treatment_packages (name, description, price, sessions_included, duration_minutes, category) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $description, $price, $sessions, $duration, $category])) {
            $success_message = "Paquete agregado exitosamente";
        } else {
            $error_message = "Error al agregar el paquete";
        }
    } else {
        $error_message = "Por favor complete todos los campos requeridos";
    }
}

// Obtener todos los paquetes
$stmt = $conn->query("SELECT * FROM treatment_packages ORDER BY created_at DESC");
$packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMUNA - Gestionar Paquetes</title>
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
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }

        .back-btn {
            background: #b08660;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .form-card {
            background: #ebe4c7;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .form-card h2 {
            color: #047475;
            margin-bottom: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            color: #047475;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        input, textarea, select {
            padding: 0.75rem;
            border: 2px solid #aec2c0;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #047475;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            background: linear-gradient(135deg, #047475, #aec2c0);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: #ebe4c7;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #aec2c0;
        }

        .table th {
            background: #047475;
            color: white;
            font-weight: 600;
        }

        .table tr:hover {
            background: rgba(174, 194, 192, 0.1);
        }

        .status {
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status.active {
            background: #d4edda;
            color: #155724;
        }

        .status.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>💉 Gestionar Paquetes de Tratamiento</h1>
        <a href="admin_dashboard.php" class="back-btn">← Volver al Dashboard</a>
    </div>

    <div class="container">
        <?php if (isset($success_message)): ?>
            <div class="alert success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Formulario para agregar nuevo paquete -->
        <div class="form-card">
            <h2>Agregar Nuevo Paquete</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_package">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Nombre del Paquete *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Categoría</label>
                        <select id="category" name="category">
                            <option value="Suero Terapia">Suero Terapia</option>
                            <option value="Tratamiento Facial">Tratamiento Facial</option>
                            <option value="Anti-Edad">Anti-Edad</option>
                            <option value="Hidratación">Hidratación</option>
                            <option value="Detox">Detox</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Precio ($) *</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="sessions">Número de Sesiones *</label>
                        <input type="number" id="sessions" name="sessions" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration">Duración (minutos) *</label>
                        <input type="number" id="duration" name="duration" min="15" step="15" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="description">Descripción</label>
                        <textarea id="description" name="description" placeholder="Describe el tratamiento y sus beneficios..."></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn">Agregar Paquete</button>
            </form>
        </div>

        <!-- Lista de paquetes existentes -->
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>Sesiones</th>
                    <th>Duración</th>
                    <th>Estado</th>
                    <th>Creado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $package): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($package['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($package['category']); ?></td>
                    <td>$<?php echo number_format($package['price'], 2); ?></td>
                    <td><?php echo $package['sessions_included']; ?></td>
                    <td><?php echo $package['duration_minutes']; ?> min</td>
                    <td><span class="status <?php echo $package['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $package['is_active'] ? 'Activo' : 'Inactivo'; ?></span></td>
                    <td><?php echo date('d/m/Y', strtotime($package['created_at'])); ?></td>
                    <td>
                        <a href="edit_package.php?id=<?php echo $package['id']; ?>" class="btn" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;">Editar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
