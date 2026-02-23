<?php
declare(strict_types=1);

namespace Config;

use PDO;
use PDOException;

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn = null;

    public function __construct() {
        // Cargar variables de entorno
        Env::load();
        
        // Obtener credenciales desde variables de entorno
        $this->host = Env::get('DB_HOST', 'localhost');
        $this->db_name = Env::get('DB_NAME', 'eduma');
        $this->username = Env::get('DB_USER', 'root');
        $this->password = Env::get('DB_PASSWORD', 'UMA2025');
    }

    public function getConnection(): PDO {
        try {
            if ($this->conn === null) {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    ]
                );
            }
            return $this->conn;
        } catch (PDOException $exception) {
            // Loguear el error interno
            error_log("[EDUMA][DB_ERROR] " . $exception->getMessage());
            
            $mensaje = "Error crítico: No se pudo establecer la conexión con la base de datos.";
            
            // En modo debug, proporcionar detalles específicos del error
            if (Env::get('APP_DEBUG', true)) {
                $mensaje .= " Detalles técnicos: " . $exception->getMessage() . 
                           " [Servidor: {$this->host}, BD: {$this->db_name}, Usuario: {$this->username}]";
            }
            
            throw new \RuntimeException($mensaje, (int)$exception->getCode(), $exception);
        }
    }

    public function closeConnection() {
        $this->conn = null;
    }
}