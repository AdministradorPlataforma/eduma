<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Helpers\SessionHelper;

/**
 * MenuConfigHelper
 *
 * Centraliza la configuración del menú de navegación lateral.
 * Define la estructura del menú y gestiona la visibilidad de los ítems
 * basándose en los permisos del usuario actual (RBAC).
 *
 * Versión V2.6 - Premium Masterpiece
 */
class MenuConfigHelper {

    /**
     * Obtiene la estructura completa del menú, filtrada por permisos.
     *
     * @return array Lista de ítems de menú visibles para el usuario.
     */
    public static function getMenu(): array {
        $menuItems = [
            [
                'label' => 'Escritorio',
                'url' => 'escritorio',
                'icon' => 'bi bi-grid-1x2-fill',
                'permission' => 'ver_escritorio',
            ],
            [
                'label' => 'Seguimiento Académico',
                'url' => '#',
                'icon' => 'bi bi-clipboard-data-fill',
                'permission' => 'ver_escritorio',
                'submenu' => [
                    [
                        'label' => 'Tablero de Control',
                        'url' => 'gestion',
                        'icon' => 'bi bi-kanban',
                        'permission' => 'ver_escritorio'
                    ],
                    [
                        'label' => 'Administración Tareas',
                        'url' => 'gestion/admin',
                        'icon' => 'bi bi-list-check',
                        'permission' => 'ver_escritorio'
                    ]
                ]
            ],
            [
                'label' => 'Investigación',
                'url' => '#',
                'icon' => 'bi bi-mortarboard-fill',
                'permission' => 'investigacion.ver',
                'submenu' => [
                    [
                        'label' => 'Gestión de Tesis',
                        'url' => 'investigacion',
                        'icon' => 'bi bi-folder2-open',
                        'permission' => 'investigacion.ver'
                    ],
                    [
                        'label' => 'Registrar Tesis',
                        'url' => 'investigacion/registrar',
                        'icon' => 'bi bi-plus-circle',
                        'permission' => 'investigacion.crear'
                    ]
                ]
            ],
            [
                'label' => 'Análisis',
                'url' => '#',
                'icon' => 'bi bi-graph-up-arrow',
                'permission' => 'ver_escritorio',
                'submenu' => [
                    [
                        'label' => 'Análisis Predictivo',
                        'url' => 'prediccion/docente',
                        'icon' => 'bi bi-cpu-fill',
                        'permission' => 'ver_escritorio'
                    ]
                ]
            ],
            [
                'label' => 'Gestión de Acceso',
                'url' => '#',
                'icon' => 'bi bi-shield-lock-fill',
                'permission' => 'ver_escritorio', 
                'submenu' => [
                    [
                        'label' => 'Usuarios',
                        'url' => 'usuario',
                        'icon' => 'bi bi-people-fill',
                        'permission' => 'ver_usuario',
                    ],
                    [
                        'label' => 'Roles',
                        'url' => 'rol',
                        'icon' => 'bi bi-person-badge-fill',
                        'permission' => 'ver_rol',
                    ],
                    [
                        'label' => 'Permisos',
                        'url' => 'permiso',
                        'icon' => 'bi bi-key-fill',
                        'permission' => 'ver_permiso',
                    ],
                    [
                        'label' => 'Sesiones Activas',
                        'url' => 'admin/sesiones',
                        'icon' => 'bi bi-activity',
                        'permission' => 'ver_usuario',
                    ]
                ]
            ],
            [
                'label' => 'Integración Moodle',
                'url' => '#',
                'icon' => 'bi bi-hdd-network-fill',
                'permission' => 'ver_escritorio', 
                'submenu' => [
                    [
                        'label' => 'Panel de Control',
                        'url' => 'moodle',
                        'icon' => 'bi bi-command',
                        'permission' => 'ver_escritorio'
                    ]
                ]
            ],
            [
                'label' => 'Sistema',
                'url' => '#',
                'icon' => 'bi bi-gear-fill',
                'permission' => 'ver_configuracion',
                'submenu' => [
                    [
                        'label' => 'Auditoría',
                        'url' => 'audit',
                        'icon' => 'bi bi-activity',
                        'permission' => 'ver_auditoria'
                    ],
                    [
                        'label' => 'Mantenimiento',
                        'url' => 'sistema',
                        'icon' => 'bi bi-tools',
                        'permission' => 'ver_configuracion'
                    ],
                    [
                        'label' => 'Papelera',
                        'url' => 'recycle-bin',
                        'icon' => 'bi bi-trash3-fill',
                        'permission' => 'ver_configuracion'
                    ],
                    [
                        'label' => 'Tareas Programadas',
                        'url' => 'sistema', // Temporalmente apunta al dashboard de sistema
                        'icon' => 'bi bi-clock-history',
                        'permission' => 'ver_configuracion'
                    ]
                ]
            ],
        ];

        return self::filterByPermission($menuItems);
    }

    /**
     * Filtra los ítems del menú comprobando los permisos del usuario en sesión.
     *
     * @param array $items
     * @return array
     */
    private static function filterByPermission(array $items): array {
        $filtered = [];
        $userPermissions = $_SESSION['user_permissions'] ?? [];

        foreach ($items as $item) {
            if (empty($item['permission']) || in_array($item['permission'], $userPermissions)) {
                
                if (isset($item['submenu']) && is_array($item['submenu'])) {
                    $item['submenu'] = self::filterByPermission($item['submenu']);
                    
                    // Si el menú tiene submenús pero todos fueron filtrados, no mostramos el padre
                    if (empty($item['submenu'])) {
                        continue;
                    }
                }

                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    /**
     * Verifica si una ruta está activa comparándola con la URL actual.
     * Útil para resaltar el ítem en la vista.
     *
     * @param string $route Ruta relativa (ej: 'usuario')
     * @return bool
     */
    public static function isActive(string $route): bool {
        if ($route === '#') return false;
        
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Limpiar query strings para la comparación
        $cleanUri = explode('?', $currentUri)[0];
        
        // Una comprobación simple pero efectiva
        return strpos($cleanUri, '/' . $route) !== false;
    }
}
