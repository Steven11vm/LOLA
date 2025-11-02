<?php
/**
 * Sistema de login actualizado con base de datos
 */
require_once 'config/database.php';

session_start();

$db = new Database();
$pdo = $db->getConnection();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    // Verificar intentos de login
    $attempts = $db->checkLoginAttempts($ip_address);
    if ($attempts >= 5) {
        $error = "Demasiados intentos fallidos. Intenta de nuevo en 15 minutos.";
    } elseif (empty($username) || empty($password)) {
        $error = "Por favor, ingresa tanto el usuario como la contraseña.";
    } else {
        try {
            // Buscar usuario en la base de datos
            $stmt = $pdo->prepare("SELECT id, username, password, role, status FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $error = "Tu cuenta está inactiva. Contacta al administrador.";
                    $db->logLoginAttempt($username, $ip_address, false, $user_agent);
                } else {
                    // Login exitoso
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["username"] = $user["username"];
                    $_SESSION["role"] = $user["role"];
                    
                    // Actualizar último login
                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user["id"]]);
                    
                    // Registrar login exitoso
                    $db->logLoginAttempt($username, $ip_address, true, $user_agent);
                    $db->logAction($user["id"], 'login', "Usuario inició sesión", $ip_address, $user_agent);
                    
                    header("Location: dashboard.php");
                    exit();
                }
            } else {
                $error = "Usuario o contraseña incorrectos";
                $db->logLoginAttempt($username, $ip_address, false, $user_agent);
            }
        } catch (PDOException $e) {
            $error = "Error en el sistema. Intenta más tarde.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMUNA - Iniciar Sesión</title>
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

        .header {
            margin-bottom: 30px;
            text-align: center;
        }

        .header img {
            max-width: 200px;
            height: auto;
        }

        .modal {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        h2 {
            color: #ff69b4;
            margin-bottom: 30px;
            font-size: 28px;
            text-align: center;
            font-weight: 600;
            text-transform: uppercase;
        }

        .error-message {
            color: #dc3545;
            background: rgba(255, 105, 180, 0.1);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            animation: shake 0.5s ease-in-out;
            border: 1px solid rgba(255, 105, 180, 0.2);
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

        button:active {
            transform: translateY(0);
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
        }

        .register-link a {
            color: #ff69b4;
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        @media (max-width: 480px) {
            .modal {
                padding: 30px;
                margin: 10px;
            }

            h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="header animate__animated animate__fadeIn">
        <img src="ruta-a-tu-logo.png" alt="EMUNA">
    </div>
    
    <div class="modal animate__animated animate__fadeIn">
        <form method="POST" action="">
            <h2 class="animate__animated animate__slideInDown">Iniciar sesión</h2>
            
            <?php if ($error): ?>
                <div class="error-message animate__animated animate__shakeX">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="input-group">
                <label for="username">Usuario o Email:</label>
                <input type="text" id="username" name="username" required 
                       class="animate__animated animate__fadeInUp"
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            
            <div class="input-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required 
                       class="animate__animated animate__fadeInUp">
            </div>
            
            <button type="submit" class="animate__animated animate__fadeInUp">
                Iniciar sesión
            </button>
            
            <div class="register-link">
                <p>¿No tienes cuenta? <a href="auth/register.php">Regístrate aquí</a></p>
            </div>
        </form>
    </div>
</body>
</html>
