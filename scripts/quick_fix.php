<?php
// Minimal Database Fixer (Corregido)
require_once __DIR__ . '/../vendor/autoload.php';
\Config\Env::load(__DIR__ . '/../.env');

try {
    $db = (new \Config\Database())->getConnection();
    echo "Conectado a BD.\n";
    
    // 1. Crear Tabla Rate Limits (Definición compatible y correcta)
    $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(150) NOT NULL COMMENT 'Identificador único (IP o IP+Usuario)',
        attempts INT DEFAULT 0,
        last_attempt DATETIME DEFAULT NULL,
        locked_until DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT NULL,
        updated_at DATETIME DEFAULT NULL,
        UNIQUE KEY uk_rate_limit (`key`)
    ) ENGINE=InnoDB;";
    
    $db->exec($sql);
    echo "Tabla rate_limits creada/verificada correctamente.\n";

    // 2. Insertar Permisos
    $perms = [
        ['Ver Sistema', 'sistema.ver', 'Acceso al módulo de estado del sistema'],
        ['Gestionar Papelera', 'papelera.gestionar', 'Gestión de reciclaje'],
        ['Configurar RBAC', 'rbac.configurar', 'Configuración avanzada de seguridad']
    ];
    
    $stmt = $db->prepare("INSERT IGNORE INTO permisos (nombre, slug, descripcion) VALUES (?, ?, ?)");
    foreach ($perms as $p) {
        $stmt->execute($p);
    }
    echo "Permisos insertados.\n";

    // 3. Asignar al Rol Admin (ID 1)
    $db->exec("INSERT IGNORE INTO rol_permisos (rol_id, permiso_id)
               SELECT 1, id FROM permisos WHERE slug IN ('sistema.ver', 'papelera.gestionar', 'rbac.configurar')");
    echo "Permisos asignados al Admin.\n";

    // 4. Migrar Passwords
    $stmt = $db->query("SELECT id, username, password FROM usuarios");
    $migrados = 0;
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Solo migrar si NO es un hash de BCRYPT (empieza con $2y$)
        if (!empty($r['password']) && strpos($r['password'], '$2y$') !== 0) {
            $newHash = password_hash($r['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $up = $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $up->execute([$newHash, $r['id']]);
            $migrados++;
        }
    }
    echo "Passwords migrados: $migrados\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
