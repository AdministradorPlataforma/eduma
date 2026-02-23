<?php
/**
 * Script de Migración Masiva de Contraseñas Legacy
 * 
 * OBJETIVO: Convertir TODOS los hashes MD5 y SHA1 restantes a bcrypt.
 * Como no se puede revertir MD5/SHA1 a texto plano, se genera una 
 * contraseña temporal segura y se marca al usuario para forzar el cambio.
 * 
 * USO: php scripts/migrate_legacy_passwords.php
 * 
 * @version 2.0 — Reemplaza fix_legacy_passwords.php
 * @date 2026-02-23
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';

\Config\Env::load();
require_once __DIR__ . '/../config/Constants.php';

use Config\Database;
use App\Helpers\PasswordValidator;

echo "================================================================\n";
echo "  EDUMA — Migración de Contraseñas Legacy (MD5/SHA1 → bcrypt)\n";
echo "================================================================\n\n";

try {
    $db = (new Database())->getConnection();
    
    // Obtener todos los usuarios con sus passwords
    $stmt = $db->query("SELECT id, username, email, password FROM usuarios WHERE password IS NOT NULL AND password != ''");
    $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $migrated = 0;
    $alreadySecure = 0;
    $noPassword = 0;
    $errors = 0;
    $migratedUsers = [];

    $db->beginTransaction();

    foreach ($users as $user) {
        $pwd = $user['password'];
        
        // 1. Ya es bcrypt/argon2? (Empieza con $2y$, $2a$, $argon2id$, etc.)
        if (substr($pwd, 0, 1) === '$') {
            $alreadySecure++;
            continue;
        }
        
        // 2. Es MD5? (32 hex chars)
        $isMD5 = (bool)preg_match('/^[a-f0-9]{32}$/i', $pwd);
        
        // 3. Es SHA1? (40 hex chars)
        $isSHA1 = (bool)preg_match('/^[a-f0-9]{40}$/i', $pwd);
        
        if (!$isMD5 && !$isSHA1) {
            // Texto plano u otro formato desconocido — hashear directamente
            $hashType = 'plaintext';
        } else {
            $hashType = $isMD5 ? 'MD5' : 'SHA1';
        }
        
        // Generar contraseña temporal segura
        $tempPassword = bin2hex(random_bytes(8)); // 16 chars hex
        $newHash = PasswordValidator::hash($tempPassword);
        
        try {
            // Actualizar password y marcar que necesita cambio
            $update = $db->prepare(
                "UPDATE usuarios 
                 SET password = :pwd, 
                     force_password_change = 1,
                     updated_at = NOW() 
                 WHERE id = :id"
            );
            $update->execute([':pwd' => $newHash, ':id' => $user['id']]);
            
            $migrated++;
            $migratedUsers[] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'old_type' => $hashType,
                'temp_password' => $tempPassword
            ];
            
            echo "[OK] Usuario: {$user['username']} (ID:{$user['id']}) — {$hashType} → bcrypt\n";
            
        } catch (\Exception $e) {
            $errors++;
            echo "[ERROR] Usuario: {$user['username']} — " . $e->getMessage() . "\n";
        }
    }

    // Verificar si la columna force_password_change existe
    try {
        $db->query("SELECT force_password_change FROM usuarios LIMIT 1");
    } catch (\PDOException $e) {
        // Si no existe, crearla
        echo "\n[INFO] Creando columna force_password_change...\n";
        $db->exec("ALTER TABLE usuarios ADD COLUMN force_password_change TINYINT(1) DEFAULT 0 AFTER suspended");
        
        // Re-ejecutar los updates que fallaron por la columna faltante
        echo "[INFO] Re-aplicando migraciones de password...\n";
        foreach ($migratedUsers as $mu) {
            $tempHash = PasswordValidator::hash($mu['temp_password']);
            $update = $db->prepare("UPDATE usuarios SET password = :pwd, force_password_change = 1 WHERE id = :id");
            $update->execute([':pwd' => $tempHash, ':id' => $mu['id']]);
        }
    }

    $db->commit();

    // Resumen
    echo "\n================================================================\n";
    echo "  RESUMEN DE MIGRACIÓN\n";
    echo "================================================================\n";
    echo "Total usuarios analizados: " . count($users) . "\n";
    echo "Ya seguros (bcrypt/argon2): {$alreadySecure}\n";
    echo "Migrados a bcrypt:          {$migrated}\n";
    echo "Errores:                    {$errors}\n";
    echo "================================================================\n\n";

    if (!empty($migratedUsers)) {
        echo "⚠️  IMPORTANTE: Los siguientes usuarios tienen contraseñas temporales.\n";
        echo "    Distribúyalas de forma segura para que cada usuario pueda cambiarla.\n\n";
        
        echo str_pad("ID", 8) . str_pad("Username", 25) . str_pad("Email", 35) . str_pad("Tipo Ant.", 12) . "Pass Temporal\n";
        echo str_repeat("-", 100) . "\n";
        
        foreach ($migratedUsers as $mu) {
            echo str_pad((string)$mu['id'], 8) 
                . str_pad($mu['username'], 25) 
                . str_pad($mu['email'], 35) 
                . str_pad($mu['old_type'], 12) 
                . $mu['temp_password'] . "\n";
        }
        
        echo "\n";
    }

} catch (\Exception $e) {
    echo "\n[FATAL] " . $e->getMessage() . "\n";
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    exit(1);
}
