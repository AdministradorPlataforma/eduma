<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario\UsuarioModel;
use App\Helpers\PasswordValidator;
use App\Helpers\SessionHelper;
use App\Helpers\RateLimitHelper;

class AuthService extends BaseService {
    
    private UsuarioModel $usuarioModel;
    private SessionHelper $session;

    public function __construct(UsuarioModel $usuarioModel, SessionHelper $session) {
        $this->usuarioModel = $usuarioModel;
        $this->session = $session;
    }

    /**
     * Intenta autenticar un usuario con sus credenciales.
     * 
     * @param string $username
     * @param string $password
     * @return array Datos del usuario autenticado
     * @throws \Exception Si falla la autenticación (mensaje seguro)
     */
    public function authenticate(string $username, string $password): array {
        $user = $this->usuarioModel->findByUsername($username);

        if (!$user) {
            // Mensaje genérico por seguridad
            $this->error('Credenciales incorrectas.');
        }

        $passwordNeedsRehash = false;
        $isValid = false;

        // ============================================================
        // AUTENTICACIÓN SEGURA - Solo hashes modernos (bcrypt/argon2)
        // ============================================================
        // ELIMINADO (2026-02-23): Soporte MD5/SHA1 removido por seguridad.
        // Si existen usuarios con hashes legacy, ejecutar:
        //   php scripts/migrate_legacy_passwords.php
        // Ese script fuerza el reset y notifica al admin.
        // ============================================================

        // Detectar hash legacy (MD5=32hex, SHA1=40hex) y rechazar
        $storedHash = $user['password'] ?? '';
        if (preg_match('/^[a-f0-9]{32}$/i', $storedHash) || preg_match('/^[a-f0-9]{40}$/i', $storedHash)) {
            // Hash inseguro detectado — NO validamos, logueamos y bloqueamos
            \App\Helpers\LoggerHelper::security(
                "Login bloqueado: hash legacy detectado (MD5/SHA1)", 
                ['user_id' => $user['id'], 'username' => $username]
            );
            $this->error('Su contraseña requiere actualización. Contacte al administrador.');
        }

        // Verificación segura con bcrypt/argon2 (password_verify)
        $isValid = PasswordValidator::verify($password, $storedHash);
        
        if ($isValid && PasswordValidator::needsRehash($storedHash)) {
            $passwordNeedsRehash = true;
        }

        if (!$isValid) {
            $this->error('Credenciales incorrectas.');
        }

        // Auto-rehash si el algoritmo/costo cambió (bcrypt cost upgrade)
        if ($passwordNeedsRehash) {
            try {
                $newHash = PasswordValidator::hash($password);
                $this->usuarioModel->update((int)$user['id'], ['password' => $newHash]);
                $user['password'] = $newHash;

                error_log("[AUTH] Password rehashed para usuario ID:{$user['id']} (upgrade de costo bcrypt)");
            } catch (\Exception $e) {
                // No bloqueamos el login si falla el rehash
                error_log("[AUTH_WARN] Error rehashing password usuario {$user['id']}: " . $e->getMessage());
            }
        }

        return $user;
    }

    /**
     * Inicia la sesión del usuario estableciendo variables y permisos.
     * 
     * @param array $user
     * @return void
     */
    public function loginUser(array $user): void {
        session_regenerate_id(true); // Prevención de Session Fixation
        \App\Helpers\CSRFHelper::generateToken(); // Rotar CSRF token post-login
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        RateLimitHelper::clear($ip);

        $this->session->set('user_id', $user['id']);
        $this->session->set('user_data', [
            'id' => $user['id'],
            'nombre' => $user['nombre'],
            'apellido' => $user['apellido'],
            'email' => $user['email'],
            'es_admin' => $user['es_admin'],
            'es_docente' => $user['es_docente'],
            'es_estudiante' => $user['es_estudiante']
        ]);

        // Permisos base (todos los usuarios autenticados)
        $permissions = ['ver_escritorio'];

        // ============================================================
        // RBAC DINÁMICO PURO (2026-02-23)
        // ============================================================
        // Todos los permisos se cargan desde rol_permisos (BD).
        // Ya NO hay permisos hardcodeados para es_admin.
        // El Super Admin los tiene vía RbacSetupService.
        // ============================================================

        $userId = (int)$user['id'];
        $db = \App\Core\Container::getInstance()->get('db');

        // 1. Obtener permisos dinámicos de los roles asignados
        $sql = "SELECT DISTINCT p.slug 
                FROM usuario_roles ur
                JOIN rol_permisos rp ON ur.rol_id = rp.rol_id
                JOIN permisos p ON rp.permiso_id = p.id
                WHERE ur.usuario_id = :uid";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $dynamicPerms = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if ($dynamicPerms) {
            $permissions = array_merge($permissions, $dynamicPerms);
        }

        // 2. Auto-migrate: Si es_admin=1 pero sin rol asignado, asignar rol Super Admin
        //    Esto es un fallback de migración, se logea como incidencia.
        if (($user['es_admin'] ?? 0) == 1 && empty($dynamicPerms)) {
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM usuario_roles WHERE usuario_id = :uid");
            $stmtCheck->execute([':uid' => $userId]);
            $hasRoles = (int)$stmtCheck->fetchColumn();
            
            if ($hasRoles === 0) {
                // Auto-asignar rol Super Admin (ID 1)
                try {
                    $stmtAssign = $db->prepare(
                        "INSERT IGNORE INTO usuario_roles (usuario_id, rol_id) VALUES (:uid, 1)"
                    );
                    $stmtAssign->execute([':uid' => $userId]);
                    
                    // Recargar permisos del rol recién asignado
                    $stmt->execute([':uid' => $userId]);
                    $newPerms = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                    if ($newPerms) {
                        $permissions = array_merge($permissions, $newPerms);
                    }
                    
                    error_log("[RBAC_MIGRATE] Admin ID:{$userId} sin roles — auto-asignado rol Super Admin (ID:1)");
                } catch (\Exception $e) {
                    error_log("[RBAC_WARN] No se pudo auto-asignar rol Super Admin al usuario {$userId}: " . $e->getMessage());
                }
            }
        }

        // 3. Permisos complementarios por perfil funcional
        if (($user['es_docente'] ?? 0) == 1) {
            $permissions[] = 'ver_cursos';
        }
        
        // Eliminar duplicados
        $permissions = array_unique($permissions);
        $permissions = array_values($permissions); // Reindexar
        
        $this->session->set('user_permissions', $permissions);
    }

    /**
     * Cierra la sesión actual
     */
    public function logout(): void {
        $this->session->destroy();
    }
}
