---
name: EDUMA_ACADEMIC_MANAGEMENT
description: Guía técnica del módulo de Gestión y Seguimiento Académico
---

# Gestión y Seguimiento Académico

Este módulo permite controlar el cumplimiento de tareas administrativas asignadas a facultades y cargos específicos.

## 1. Flujo de Trabajo

1.  **Administrador**: Crea las tareas en `gestion_control_seguimiento` (Actualmente vía SQL o futuro CRUD).
2.  **Sistema**: Calcula automáticamente el estado ("Semáforo") basándose en `dia_plazo_mes` y la fecha actual.
3.  **Usuario Responsable**: 
    - Ve sus tareas en la Dashboard o en `/gestion`.
    - Recibe alertas 48hs antes del vencimiento.
    - Sube evidencia (PDF/Imagen) para marcar la tarea como `CUMPLE`.
4.  **Validación**: La evidencia queda registrada en `gestion_evidencias` y el semáforo cambia a verde.

## 2. Lógica del Semáforo

Implementada en `GestionModel::calcularSemaforo($diaPlazo)`:

- **🟢 A Tiempo**: Faltan más de 2 días para el vencimiento.
- **🟡 Por Vencer**: Faltan 2 días o menos (o es el día del vencimiento).
- **🔴 Vencida**: La fecha actual (`date('j')`) es mayor al `dia_plazo_mes`.

## 3. Integración con Roles

El sistema detecta el cargo del usuario consultando:
1. Tabla `administrativos` (campo `cargo`).
2. Tabla `docentes` (campo `tipo_contrato` como fallback).

## 4. Estructura de Archivos
- **Controlador**: `App\Controllers\GestionController` (Inyecta el Servicio).
- **Servicio**: `App\Services\GestionService` (Orquesta la lógica, transacciones y eventos).
- **Repository**: `App\Repositories\Gestion\GestionRepository` (Encapsula consultas SQL complejas y agregaciones).
- **Modelo**: `App\Models\Gestion\GestionModel` (Hereda de `BaseModel`, CRUD puro).
- **Vista**: `App\Views\Gestion\index.php` (Usa componentes premium de Main.css).
- **Lógica JS**: `public/js/Gestion.js` (Manejo de eventos, Toastr y SweetAlert2).
- **Rutas**: Definidas en `routes/web.php` con middlewares de permisos.

## 5. Alertas

Se inyectan en el Dashboard (`Escritorio/index.php`) y en la vista del módulo.
- Usan `Toastr` para notificaciones no intrusivas.
- Se disparan si `$semaforo === 'warning'` o `'danger'` y no existe evidencia para el mes actual.
