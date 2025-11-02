<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Usuario y contraseña son requeridos';
    } else {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            // Verificar intentos de login recientes
            $attempts = $database->checkLoginAttempts($_SERVER['REMOTE_ADDR']);
            if ($attempts >= 5) {
                $error = 'Demasiados intentos fallidos. Intenta en 15 minutos.';
            } else {
                $stmt = $conn->prepare("
                    SELECT * FROM users 
                    WHERE username = ? AND status = 'active'
                ");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['password'])) {
                    // Login exitoso
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    
                    // Actualizar último login
                    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Registrar login exitoso
                    $database->logLoginAttempt($username, $_SERVER['REMOTE_ADDR'], true, $_SERVER['HTTP_USER_AGENT'] ?? null);
                    $database->logAction($user['id'], 'USER_LOGIN', "Usuario $username inició sesión", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? null);
                    
                    // Redirigir según rol
                    if ($user['role'] === 'admin') {
                        header('Location: admin_dashboard.php');
                    } else {
                        header('Location: user_dashboard.php');
                    }
                    exit;
                } else {
                    $error = 'Usuario o contraseña incorrectos';
                    
                    // Registrar intento fallido
                    $database->logLoginAttempt($username, $_SERVER['REMOTE_ADDR'], false, $_SERVER['HTTP_USER_AGENT'] ?? null);
                }
            }
        } catch(PDOException $e) {
            $error = 'Error de conexión: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EMUNA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #047475 0%, #036364 50%, #aec2c0 100%);
            background-attachment: fixed;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(174, 194, 192, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(176, 134, 96, 0.2) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .login-container {
            background: rgba(235, 228, 199, 0.98);
            backdrop-filter: blur(10px);
            padding: 50px 40px;
            border-radius: 24px;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            max-width: 440px;
            width: 100%;
            position: relative;
            z-index: 1;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #b08660, #047475);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            box-shadow: 0 8px 20px rgba(4, 116, 117, 0.3);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        h1 {
            color: #047475;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            color: #6b7c7b;
            font-size: 14px;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #047475;
            font-size: 14px;
            letter-spacing: 0.2px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            font-size: 18px;
            color: #6b7c7b;
            z-index: 2;
            transition: color 0.3s ease;
        }

        input[type="text"], 
        input[type="password"] {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #d1d5db;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #ffffff;
            color: #1f2937;
            font-family: inherit;
        }

        input:focus {
            border-color: #047475;
            outline: none;
            box-shadow: 0 0 0 4px rgba(4, 116, 117, 0.1);
            transform: translateY(-1px);
        }

        input:focus + .input-icon,
        .input-wrapper:has(input:focus) .input-icon {
            color: #047475;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #6b7c7b;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #047475;
        }

        .error {
            color: #dc2626;
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #fca5a5;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .error::before {
            content: '⚠️';
            font-size: 18px;
        }

        button[type="submit"] {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #b08660, #047475);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 8px;
            box-shadow: 0 4px 12px rgba(4, 116, 117, 0.3);
            position: relative;
            overflow: hidden;
        }

        button[type="submit"]::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        button[type="submit"]:hover::before {
            left: 100%;
        }

        button[type="submit"]:hover {
            background: linear-gradient(135deg, #9a7555, #036364);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(4, 116, 117, 0.4);
        }

        button[type="submit"]:active {
            transform: translateY(0);
        }

        .links {
            text-align: center;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .links a {
            color: #b08660;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .links a:hover {
            color: #047475;
            text-decoration: underline;
            transform: translateY(-1px);
        }

        .admin-info {
            background: linear-gradient(135deg, #aec2c0, #b8d4d1);
            padding: 16px;
            border-radius: 12px;
            margin-top: 24px;
            font-size: 13px;
            text-align: center;
            color: #047475;
            border: 1px solid rgba(4, 116, 117, 0.2);
            box-shadow: 0 2px 8px rgba(4, 116, 117, 0.1);
        }

        .admin-info strong {
            font-weight: 600;
            display: block;
            margin-bottom: 4px;
        }

        .admin-info br {
            display: none;
        }

        .admin-info strong:first-child {
            margin-top: 0;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 40px 28px;
                border-radius: 20px;
            }

            h1 {
                font-size: 24px;
            }

            .logo-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-icon">🔐</div>
            <h1>EMUNA</h1>
            <p class="subtitle">Sistema de Gestión</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="username">Usuario</label>
                <div class="input-wrapper">
                    <span class="input-icon">👤</span>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           placeholder="Ingresa tu usuario"
                           autocomplete="username">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required
                           placeholder="Ingresa tu contraseña"
                           autocomplete="current-password">
                    <button type="button" class="password-toggle" onclick="togglePassword()">👁️</button>
                </div>
            </div>
            
            <button type="submit">Iniciar Sesión →</button>
        </form>
        
        <div class="links">
            <a href="register.php">¿No tienes cuenta? <strong>Regístrate aquí</strong></a>
        </div>
        
        <div class="admin-info">
            <strong>👨‍💼 Usuario admin:</strong> SANDRA MENDOZA
            <strong>🔑 Contraseña:</strong> 123
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        // Animación de entrada para los inputs
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input');
            inputs.forEach((input, index) => {
                input.style.opacity = '0';
                input.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    input.style.transition = 'all 0.4s ease';
                    input.style.opacity = '1';
                    input.style.transform = 'translateY(0)';
                }, 100 * (index + 1));
            });

            // Auto-focus en el primer campo
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>
