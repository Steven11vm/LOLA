<?php
require_once 'config/database.php';

$message = '';
$error = '';

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    
    // Validaciones
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Todos los campos obligatorios deben ser completados';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } elseif (strlen($password) < 3) {
        $error = 'La contraseña debe tener al menos 3 caracteres';
    } else {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            // Verificar si el usuario ya existe
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'El usuario o email ya existe';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'user')");
                $stmt->execute([$username, $email, $password_hash, $full_name]);
                
                $user_id = $conn->lastInsertId();
                
                // Registrar en logs del sistema
                $database->logAction($user_id, 'USER_REGISTERED', "Usuario $username registrado", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? null);
                
                $message = 'Usuario registrado exitosamente. <a href="login.php">Iniciar sesión</a>';
            }
        } catch(PDOException $e) {
            $error = 'Error al registrar usuario: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - EMUNA</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            /* Applied new color palette - teal gradient background */
            background: linear-gradient(135deg, #047475, #aec2c0);
            min-height: 100vh;
        }
        .form-container {
            /* Changed background to cream color from palette */
            background: #ebe4c7;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            /* Changed text color to dark teal */
            color: #047475;
        }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
        }
        input:focus {
            /* Changed focus border to dark teal */
            border-color: #047475;
            outline: none;
        }
        button {
            width: 100%;
            padding: 15px;
            /* Applied brown-teal gradient from palette */
            background: linear-gradient(135deg, #b08660, #047475);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        button:hover {
            /* Darker hover effect with palette colors */
            background: linear-gradient(135deg, #9a7555, #036364);
        }
        .error {
            color: #dc3545;
            background-color: #f8d7da;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
        }
        .success {
            /* Updated success colors to match palette */
            color: #047475;
            background-color: #aec2c0;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #047475;
        }
        .links {
            text-align: center;
            margin-top: 20px;
        }
        .links a {
            /* Changed link color to brown from palette */
            color: #b08660;
            text-decoration: none;
            font-weight: bold;
        }
        h2 {
            text-align: center;
            /* Changed heading color to dark teal */
            color: #047475;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Registro de Usuario - EMUNA</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Usuario *</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="full_name">Nombre Completo *</label>
                <input type="text" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña *</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña *</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit">Registrar Usuario</button>
        </form>
        
        <div class="links">
            <a href="login.php">¿Ya tienes cuenta? Iniciar sesión</a>
        </div>
    </div>
</body>
</html>
