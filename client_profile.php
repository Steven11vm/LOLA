<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];

// Procesar subida de archivos
if ($_POST && isset($_FILES['file'])) {
    $upload_dir = 'uploads/clients/' . $user_id . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file = $_FILES['file'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_type = $file['type'];
    $category = $_POST['category'];
    $description = $_POST['description'];
    
    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
    $new_file_name = uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . $new_file_name;
    
    if (move_uploaded_file($file_tmp, $file_path)) {
        $stmt = $conn->prepare("INSERT INTO client_files (user_id, file_name, file_path, file_type, file_size, category, description, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $file_name, $file_path, $file_type, $file_size, $category, $description, $user_id]);
        $success_message = "Archivo subido exitosamente";
    } else {
        $error_message = "Error al subir el archivo";
    }
}

// Obtener archivos del cliente
$stmt = $conn->prepare("SELECT * FROM client_files WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$client_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener información del usuario
$stmt = $conn->prepare("SELECT u.*, p.* FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMUNA - Mi Perfil</title>
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .card {
            background: rgba(235, 228, 199, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(4, 116, 117, 0.1);
        }

        .card h2 {
            color: #047475;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #047475;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
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
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(4, 116, 117, 0.3);
        }

        .file-item {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            color: #047475;
        }

        .file-meta {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .category-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
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

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #feb2b2;
        }

        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-user-circle"></i>Mi Perfil</h1>
        <a href="user_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Volver al Dashboard
        </a>
    </div>

    <div class="container">
        <div class="card">
            <h2><i class="fas fa-upload"></i>Subir Archivos</h2>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Archivo</label>
                    <input type="file" name="file" class="form-control" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                </div>
                
                <div class="form-group">
                    <label>Categoría</label>
                    <select name="category" class="form-control" required>
                        <option value="medical_history">Historia Médica</option>
                        <option value="photos">Fotos</option>
                        <option value="documents">Documentos</option>
                        <option value="results">Resultados</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Descripción del archivo..."></textarea>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-upload"></i>
                    Subir Archivo
                </button>
            </form>
        </div>

        <div class="card">
            <h2><i class="fas fa-folder-open"></i>Mis Archivos (<?php echo count($client_files); ?>)</h2>
            
            <?php if (empty($client_files)): ?>
                <div style="text-align: center; padding: 2rem; color: #666;">
                    <i class="fas fa-file-alt" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                    <h3>No tienes archivos subidos</h3>
                    <p>Sube tu primera foto o documento</p>
                </div>
            <?php else: ?>
                <?php foreach ($client_files as $file): ?>
                <div class="file-item">
                    <div class="file-info">
                        <div class="file-name"><?php echo htmlspecialchars($file['file_name']); ?></div>
                        <div class="file-meta">
                            <span class="category-badge category-<?php echo $file['category']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $file['category'])); ?>
                            </span>
                            • <?php echo number_format($file['file_size'] / 1024, 1); ?> KB
                            • <?php echo date('d/m/Y H:i', strtotime($file['created_at'])); ?>
                        </div>
                        <?php if ($file['description']): ?>
                            <div style="margin-top: 0.5rem; font-size: 0.9rem; color: #555;">
                                <?php echo htmlspecialchars($file['description']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="<?php echo $file['file_path']; ?>" target="_blank" class="btn" style="padding: 0.5rem 1rem;">
                            <i class="fas fa-eye"></i>
                            Ver
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
