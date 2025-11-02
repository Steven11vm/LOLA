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
            case 'toggle_status':
                $user_id = $_POST['user_id'];
                $new_status = $_POST['status'] === 'active' ? 'inactive' : 'active';
                $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $user_id]);
                break;
            
            case 'delete_user':
                $user_id = $_POST['user_id'];
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                $stmt->execute([$user_id]);
                break;
        }
    }
}

// Obtener todos los usuarios
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';

$query = "SELECT * FROM users WHERE role = 'user'";
$params = [];

if ($search) {
    $query .= " AND (full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMUNA - Gestionar Usuarios</title>
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

        .search-box {
            flex: 1;
            min-width: 300px;
            padding: 0.75rem 1rem;
            border: 2px solid #aec2c0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .search-box:focus {
            outline: none;
            border-color: #047475;
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

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .users-grid {
            display: grid;
            gap: 1.5rem;
        }

        .user-card {
            background: rgba(235, 228, 199, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(4, 116, 117, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(4, 116, 117, 0.15);
        }

        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .user-info h3 {
            color: #047475;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .user-info p {
            color: #666;
            margin-bottom: 0.25rem;
        }

        .user-actions {
            display: flex;
            gap: 0.5rem;
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

        .status.inactive {
            background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
            color: #742a2a;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
            
            .filters-row {
                flex-direction: column;
            }
            
            .search-box {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-users-cog"></i>Gestionar Usuarios</h1>
        <a href="admin_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Volver al Dashboard
        </a>
    </div>

    <div class="container">
        <div class="filters">
            <form method="GET" class="filters-row">
                <input type="text" name="search" class="search-box" placeholder="Buscar por nombre o email..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="filter" class="filter-select">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Todos los usuarios</option>
                    <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Usuarios activos</option>
                    <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>Usuarios inactivos</option>
                </select>
                <button type="submit" class="btn">
                    <i class="fas fa-search"></i>
                    Buscar
                </button>
            </form>
        </div>

        <div class="users-grid">
            <?php if (empty($users)): ?>
                <div class="user-card" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-user-slash" style="font-size: 3rem; color: #666; margin-bottom: 1rem;"></i>
                    <h3 style="color: #666;">No se encontraron usuarios</h3>
                    <p style="color: #999;">Intenta ajustar los filtros de búsqueda</p>
                </div>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                <div class="user-card">
                    <div class="user-header">
                        <div class="user-info">
                            <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'No especificado'); ?></p>
                            <p><i class="fas fa-calendar"></i> Registrado: <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></p>
                        </div>
                        <div class="user-actions">
                            <span class="status <?php echo $user['status']; ?>">
                                <i class="fas fa-circle"></i>
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="user-actions">
                        <a href="client_profile_admin.php?id=<?php echo $user['id']; ?>" class="btn btn-small">
                            <i class="fas fa-user-edit"></i>
                            Ver Perfil
                        </a>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <input type="hidden" name="status" value="<?php echo $user['status']; ?>">
                            <button type="submit" class="btn btn-small <?php echo $user['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>">
                                <i class="fas fa-<?php echo $user['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
                                <?php echo $user['status'] === 'active' ? 'Desactivar' : 'Activar'; ?>
                            </button>
                        </form>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este usuario?')">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" class="btn btn-small btn-danger">
                                <i class="fas fa-trash"></i>
                                Eliminar
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
