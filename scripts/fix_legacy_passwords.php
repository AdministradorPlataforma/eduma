<?php
/**
 * Script de Migración de Seguridad Crítica
 * 
 * Objetivo: Identificar y hashear contraseñas en texto plano.
 * No toca hashes MD5 (32 chars hex) ni SHA1 (40 chars hex) para no romper esos logins legacy.
 * Solo convierte lo que claramente es texto plano a Bcrypt.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Config\Database;
use App\Helpers\PasswordValidator;

echo "=== Migración de Contraseñas en Texto Plano ===\n";

try {
    $db = (new Database())->getConnection();
    
    // Obtener todos los usuarios
    $stmt = $db->query("SELECT id, username, password FROM usuarios");
    $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    $count = 0;
    $skipped = 0;
    
    foreach ($users as $user) {
        $pwd = $user['password'];
        $needsHash = false;
        $reason = '';
        
        // 1. Ya es Bcrypt/Argon? (Empieza con $2y$, $2a$, etc)
        if (substr($pwd, 0, 1) === '$') {
            continue; // Ya seguro
        }
        
        // 2. Es MD5? (32 hex)
        if (preg_match('/^[a-f0-9]{32}$/i', $pwd)) {
            $skipped++;
            echo "Usuario {$user['username']}: Ignorado (Posible MD5)\n";
            continue; 
        }
        
        // 3. Es SHA1? (40 hex)
        if (preg_match('/^[a-f0-9]{40}$/i', $pwd)) {
            $skipped++;
            echo "Usuario {$user['username']}: Ignorado (Posible SHA1)\n";
            continue;
        }
        
        // 4. Texto Plano detectado
        echo "Usuario {$user['username']}: Texto plano detectado. Hasheando...\n";
        
        $newHash = password_hash($pwd, PASSWORD_DEFAULT);
        
        $update = $db->prepare("UPDATE usuarios SET password = :p WHERE id = :id");
        $update->execute([':p' => $newHash, ':id' => $user['id']]);
        
        $count++;
    }
    
    echo "\n=== Resumen ===\n";
    echo "Total procesados: " . count($users) . "\n";
    echo "Hasheados (eran texto plano): $count\n";
    echo "Saltados (MD5/SHA1 legacy): $skipped\n";
    echo "Seguros (ya eran hash): " . (count($users) - $count - $skipped) . "\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
