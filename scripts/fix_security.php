<?php
/**
 * Security Fix Script
 * 1. Aplica migraciones críticas (Tabla rate_limits, permisos).
 * 2. Migra contraseñas legacy de texto plano a BCRYPT.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Cargar Entorno
\Config\Env::load(__DIR__ . '/../.env');
$db = (new \Config\Database())->getConnection();

echo "=== INICIANDO PARCHE DE SEGURIDAD ===\n";

// 1. Ejecutar SQL de Tabla Rate Limits y Permisos
$sqlMigracion = file_get_contents(__DIR__ . '/../bd/migrations/2026_02_17_security_patch.sql');
try {
    $db->exec($sqlMigracion);
    echo "[OK] Tabla rate_limits y permisos creados/actualizados.\n";
} catch (PDOException $e) {
    echo "[ERROR] Fallo al crear tablas: " . $e->getMessage() . "\n";
    // Continuamos igual por si ya existían
}

// 2. Migrar Contraseñas Legacy
echo "=== MIGRANDO CONTRASEÑAS ===\n";
$stmt = $db->query("SELECT id, username, password FROM usuarios");
$count = 0;
$migrated = 0;

while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $pass = $user['password'];
    
    // Si la contraseña NO parece un hash de BCRYPT ($2y$...)
    // Y no está vacía
    if (!empty($pass) && strpos($pass, '$2y$') !== 0) {
        $newHash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $update = $db->prepare("UPDATE usuarios SET password = :p WHERE id = :id");
        $update->execute([':p' => $newHash, ':id' => $user['id']]);
        
        echo " -> Migrado usuario ID {$user['id']} ({$user['username']})\n";
        $migrated++;
    }
    $count++;
}

echo "=== RESULTADO ===\n";
echo "Total usuarios revisados: $count\n";
echo "Total contraseñas migradas: $migrated\n";
echo "Listo.\n";
