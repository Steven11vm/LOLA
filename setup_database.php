<?php
/**
 * Script para corregir y agregar columnas faltantes en la base de datos EMUNA
 * Ejecutar este archivo para solucionar el error de columnas no encontradas
 */

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'emuna_system';
$username = 'root'; // Cambia por tu usuario de MySQL
$password = '';     // Cambia por tu contraseña de MySQL

try {
    // Crear conexión PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔧 Reparando base de datos EMUNA...</h2>";
    
    // Función para verificar si una columna existe
    function columnExists($pdo, $table, $column) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() > 0;
    }
    
    // Verificar y agregar columnas faltantes en user_profiles
    echo "<h3>📋 Verificando tabla 'user_profiles'...</h3>";
    
    $columns_to_check = [
        'emergency_contact' => "ALTER TABLE user_profiles ADD COLUMN emergency_contact VARCHAR(100) AFTER bio",
        'medical_notes' => "ALTER TABLE user_profiles ADD COLUMN medical_notes TEXT AFTER emergency_contact"
    ];
    
    foreach ($columns_to_check as $column => $alter_sql) {
        if (!columnExists($pdo, 'user_profiles', $column)) {
            $pdo->exec($alter_sql);
            echo "<p>✅ Columna '$column' agregada a user_profiles</p>";
        } else {
            echo "<p>✅ Columna '$column' ya existe en user_profiles</p>";
        }
    }
    
    // Verificar y agregar columnas faltantes en client_treatments
    echo "<h3>📋 Verificando tabla 'client_treatments'...</h3>";
    
    $treatment_columns = [
        'treatment_description' => "ALTER TABLE client_treatments ADD COLUMN treatment_description TEXT NOT NULL AFTER status",
        'medical_notes' => "ALTER TABLE client_treatments ADD COLUMN medical_notes TEXT AFTER treatment_description"
    ];
    
    foreach ($treatment_columns as $column => $alter_sql) {
        if (!columnExists($pdo, 'client_treatments', $column)) {
            try {
                $pdo->exec($alter_sql);
                echo "<p>✅ Columna '$column' agregada a client_treatments</p>";
            } catch (PDOException $e) {
                // Si la columna treatment_description es NOT NULL, necesitamos un valor por defecto
                if ($column === 'treatment_description' && strpos($e->getMessage(), '1364') !== false) {
                    $alter_sql_default = "ALTER TABLE client_treatments ADD COLUMN treatment_description TEXT DEFAULT 'Tratamiento estándar' AFTER status";
                    $pdo->exec($alter_sql_default);
                    echo "<p>✅ Columna '$column' agregada a client_treatments con valor por defecto</p>";
                } else {
                    throw $e;
                }
            }
        } else {
            echo "<p>✅ Columna '$column' ya existe en client_treatments</p>";
        }
    }
    
    // Recrear la vista con manejo de errores
    echo "<h3>🔄 Recreando vista 'user_complete_info'...</h3>";
    
    try {
        $sql_view = "
        CREATE OR REPLACE VIEW user_complete_info AS
        SELECT 
            u.id,
            u.username,
            u.email,
            u.full_name,
            u.role,
            u.status,
            u.created_at,
            u.last_login,
            COALESCE(p.phone, '') as phone,
            COALESCE(p.address, '') as address,
            COALESCE(p.city, '') as city,
            COALESCE(p.country, '') as country,
            p.birth_date,
            p.gender,
            COALESCE(p.profile_image, '') as profile_image,
            COALESCE(p.bio, '') as bio,
            COALESCE(p.emergency_contact, '') as emergency_contact,
            COALESCE(p.medical_notes, '') as medical_notes
        FROM users u
        LEFT JOIN user_profiles p ON u.id = p.user_id
        ";
        
        $pdo->exec($sql_view);
        echo "<p>✅ Vista 'user_complete_info' recreada exitosamente</p>";
    } catch (PDOException $e) {
        echo "<p>⚠️ Error al recrear la vista: " . $e->getMessage() . "</p>";
    }
    
    // Verificar la estructura actual de las tablas
    echo "<h3>📊 Verificando estructura actual...</h3>";
    
    // Verificar user_profiles
    $stmt = $pdo->query("DESCRIBE user_profiles");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p><strong>Columnas en user_profiles:</strong></p>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>$column</li>";
    }
    echo "</ul>";
    
    // Verificar client_treatments
    $stmt = $pdo->query("DESCRIBE client_treatments");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p><strong>Columnas en client_treatments:</strong></p>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>$column</li>";
    }
    echo "</ul>";
    
    // Probar la vista
    echo "<h3>🧪 Probando la vista...</h3>";
    try {
        $test_query = $pdo->query("SELECT COUNT(*) FROM user_complete_info");
        $count = $test_query->fetchColumn();
        echo "<p>✅ La vista 'user_complete_info' funciona correctamente ($count registros)</p>";
    } catch (PDOException $e) {
        echo "<p>❌ Error al probar la vista: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>🎉 ¡Reparación completada!</h2>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>✅ Acciones realizadas:</h3>";
    echo "<ul>";
    echo "<li>Verificación de todas las columnas necesarias</li>";
    echo "<li>Agregado de columnas faltantes si era necesario</li>";
    echo "<li>Recreación de la vista user_complete_info</li>";
    echo "<li>Verificación del funcionamiento</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>📝 Próximos pasos:</h3>";
    echo "<ol>";
    echo "<li>La base de datos debería funcionar correctamente ahora</li>";
    echo "<li>Puedes proceder a usar tu sistema de registro EMUNA</li>";
    echo "<li>Si persisten errores, verifica las credenciales de conexión</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "<h3>❌ Error durante la reparación:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p><strong>Posibles soluciones:</strong></p>";
    echo "<ul>";
    echo "<li>Verifica que las credenciales de conexión sean correctas</li>";
    echo "<li>Asegúrate de que la base de datos 'emuna_system' existe</li>";
    echo "<li>Verifica que tengas permisos para modificar la estructura de las tablas</li>";
    echo "</ul>";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMUNA - Reparación de Base de Datos</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #047475, #aec2c0);
            min-height: 100vh;
        }
        
        .container {
            background: #ebe4c7;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        h2, h3 {
            color: #047475;
        }
        
        p {
            margin: 10px 0;
        }
        
        ul, ol {
            margin: 15px 0;
            padding-left: 30px;
        }
        
        li {
            margin: 5px 0;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- El contenido PHP se muestra aquí -->
    </div>
</body>
</html>