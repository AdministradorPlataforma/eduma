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

        // 1. Verificación Híbrida (Legacy vs Modern)
        // ELIMINADO: Soporte para texto plano (AuthService.php:42) por seguridad.
        // Se debe ejecutar scripts/fix_legacy_passwords.php si existen casos.

        if (md5($password) === $user['password']) {
             // Legacy MD5
             $passwordNeedsRehash = true;
             $isValid = true;
        } elseif (sha1($password) === $user['password']) {
             // Legacy SHA1
             $passwordNeedsRehash = true;
             $isValid = true;
        }
        // CHECK STANDARD para hashes modernos
        else {
             $isValid = PasswordValidator::verify($password, $user['password']);
             if ($isValid && PasswordValidator::needsRehash($user['password'])) {
                 $passwordNeedsRehash = true;
             }
        }

        if (!$isValid) {
            $this->error('Credenciales incorrectas.');
        }

        // 2. On-the-fly Migration (Auto-fix)
        if ($passwordNeedsRehash) {
            try {
                $newHash = PasswordValidator::hash($password);
                $this->usuarioModel->update((int)$user['id'], ['password' => $newHash]);
                // Actualizamos el array local para consistencia si se usa después
                $user['password'] = $newHash; 
                
                // Logueamos la migración silenciosa para auditoría interna
                // (Opcional, si existiera un logger de sistema bajo nivel)
            } catch (\Exception $e) {
                // Si falla la actualización, no bloqueamos el login, 
                // pero el usuario seguirá siendo legacy hasta el próximo intento.
                error_log("Error migrando password usuario {$user['id']}: " . $e->getMessage());
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

        // Cargar premisos básicos
        $permissions = ['ver_escritorio'];

        // 1. RBAC Dinámico: Obtener permisos de Roles asignados
        $userId = (int)$user['id'];
        // Consulta directa para optimizar (podría delegarse a repositorio)
        // Obtenemos los slugs de permisos únicos asociados a los roles del usuario
        $sql = "SELECT DISTINCT p.slug 
                FROM usuario_roles ur
                JOIN rol_permisos rp ON ur.rol_id = rp.rol_id
                JOIN permisos p ON rp.permiso_id = p.id
                WHERE ur.usuario_id = :uid";
        
        $db = \App\Core\Container::getInstance()->get('db');
        $stmt = $db->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $dynamicPerms = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if ($dynamicPerms) {
            $permissions = array_merge($permissions, $dynamicPerms);
        }

        // 2. Legacy Fallback (para admins antiguos sin rol asignado aún)
        if (($user['es_admin'] ?? 0) == 1) {
             // Si tiene flag admin pero NO tiene permisos dinámicos (migración pendiente), le damos full.
             // Opcional: darle full siempre si es SuperUser.
             // Por seguridad, mantenemos el full access al admin legacy por ahora.
             $permissions = array_merge($permissions, [
                'ver_usuario', 'crear_usuario', 'editar_usuario', 'eliminar_usuario',
                'ver_rol', 'crear_rol', 'editar_rol', 'eliminar_rol',
                'ver_permiso', 'crear_permiso', 'editar_permiso', 'eliminar_permiso',
                'ver_configuracion', 'ver_reportes', 'ver_cursos',
                'investigacion.ver', 'investigacion.crear', 'investigacion.gestionar',
                'sistema.ver', 'papelera.gestionar', 'rbac.configurar'
            ]);
        }

        if (($user['es_docente'] ?? 0) == 1) $permissions[] = 'ver_cursos';
        
        // Eliminar duplicados
        $permissions = array_unique($permissions);
        
        $this->session->set('user_permissions', $permissions);
    }

    /**
     * Cierra la sesión actual
     */
    public function logout(): void {
        $this->session->destroy();
    }
}
