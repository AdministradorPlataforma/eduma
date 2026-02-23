<?php
declare(strict_types=1);

namespace App\Helpers;

class SessionHelper {
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    public static function has($key) {
        return isset($_SESSION[$key]);
    }

    public static function remove($key) {
        unset($_SESSION[$key]);
    }

    public static function destroy() {
        session_destroy();
    }

    // --- Métodos requeridos por el BaseController ---

    public function isLoggedIn(): bool {
        return self::has('user_id');
    }

    public function getUserId(): ?int {
        return self::get('user_id');
    }

    public function getUserRole(): ?int {
        return self::get('role_id');
    }

    public function getUserData(): ?array {
        return self::get('user_data');
    }

    public function isAdmin(): bool {
        $userData = $this->getUserData();
        return ($userData['es_admin'] ?? 0) == 1;
    }

    public function hasPermission(string $permission): bool {
        $permissions = self::get('user_permissions', []);
        return in_array($permission, $permissions);
    }

    public function hasRole(array $allowedRoles): bool {
        $userRole = $this->getUserRole();
        return in_array($userRole, $allowedRoles);
    }

    public function setFlashMessage(string $type, string $message): void {
        $_SESSION['flash'][$type] = $message;
    }

    public function getFlashMessage(?string $type = null) {
        if ($type) {
            $msg = $_SESSION['flash'][$type] ?? null;
            unset($_SESSION['flash'][$type]);
            return $msg;
        }
        $flashes = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flashes;
    }

    public function hasFlashMessage(?string $type = null): bool {
        if ($type) {
            return isset($_SESSION['flash'][$type]);
        }
        return !empty($_SESSION['flash']);
    }
}
