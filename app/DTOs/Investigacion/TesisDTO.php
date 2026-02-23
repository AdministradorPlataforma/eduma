<?php
declare(strict_types=1);

namespace App\DTOs\Investigacion;

class TesisDTO {
    public string $titulo;
    public string $descripcion;
    public string $estado;
    public array $estudiantesIds;
    public array $docentesIds;
    public ?array $archivo = null;
    public ?array $archivoTesis = null;

    public function __construct(
        string $titulo,
        string $descripcion,
        string $estado,
        array $estudiantesIds,
        array $docentesIds,
        ?array $archivo = null,
        ?array $archivoTesis = null
    ) {
        $this->titulo = $titulo;
        $this->descripcion = $descripcion;
        $this->estado = $estado;
        $this->estudiantesIds = $estudiantesIds;
        $this->docentesIds = $docentesIds;
        $this->archivo = $archivo;
        $this->archivoTesis = $archivoTesis;
    }

    public static function fromRequest(array $postData, array $fileData = []): self {
        return new self(
            $postData['titulo'] ?? '',
            $postData['descripcion'] ?? '',
            $postData['estado'] ?? 'Pendiente',
            $postData['estudiantes'] ?? [],
            $postData['docentes'] ?? [],
            $fileData['archivo'] ?? null,
            $fileData['archivo_tesis'] ?? null
        );
    }
}
