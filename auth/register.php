<?php
/**
 * Script para registrar nuevos usuarios
 */
require_once '../config/database.php';

session_start();

$db = new Database();
$pdo = $db->getConnection();

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    $full_name = trim($_POST["full_name"]);
    
    // Validaciones
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = "Todos los campos son obligatorios.";
    } elseif ($password !== $confirm_password) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        try {
            // Verificar si el usuario ya existe
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $error = "El usuario o email ya existe.";
            } else {
                // Crear nuevo usuario
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, full_name, role) 
                    VALUES (?, ?, ?, ?, 'user')
                ");
                
                if ($stmt->execute([$username, $email, $hashed_password, $full_name])) {
                    $user_id = $pdo->lastInsertId();
                    
                    // Crear perfil vacío
                    $stmt = $pdo->prepare("INSERT INTO user_profiles (user_id) VALUES (?)");
                    $stmt->execute([$user_id]);
                    
                    // Log de registro
                    $db->logAction($user_id, 'user_registered', "Usuario registrado: $username", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                    
                    $success = "Usuario registrado exitosamente. Ya puedes iniciar sesión.";
                } else {
                    $error = "Error al registrar el usuario.";
                }
            }
        } catch (PDOException $e) {
            $error = "Error en la base de datos: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMUNA - Registro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ff69b4, #ff8dc7);
        }

        .modal {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            backdrop-filter: blur(10px);
        }

        h2 {
            color: #ff69b4;
            margin-bottom: 30px;
            font-size: 28px;
            text-align: center;
            font-weight: 600;
            text-transform: uppercase;
        }

        .error-message, .success-message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }

        .error-message {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .success-message {
            color: #28a745;
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .input-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #ff69b4;
            font-weight: 500;
            font-size: 16px;
        }

        input {
            width: 100%;
            padding: 14px;
            border: 2px solid rgba(255, 105, 180, 0.3);
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        input:focus {
            border-color: #ff69b4;
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 105, 180, 0.2);
        }

        button {
            background: linear-gradient(45deg, #ff69b4, #ff8dc7);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        button:hover {
            background: linear-gradient(45deg, #ff8dc7, #ff69b4);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 105, 180, 0.3);
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            color: #ff69b4;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="modal animate__animated animate__fadeIn">
        <form method="POST" action="">
            <h2 class="animate__animated animate__slideInDown">Registro</h2>
            
            <?php if ($error): ?>
                <div class="error-message animate__animated animate__shakeX">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message animate__animated animate__fadeIn">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <div class="input-group">
                <label for="full_name">Nombre completo:</label>
                <input type="text" id="full_name" name="full_name" required 
                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
            </div>
            
            <div class="input-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" required 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            
            <div class="input-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="input-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="input-group">
                <label for="confirm_password">Confirmar contraseña:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit">Registrarse</button>
            
            <div class="login-link">
                <p>¿Ya tienes cuenta? <a href="../login.php">Iniciar sesión</a></p>
            </div>
        </form>
    </div>
</body>
</html>
