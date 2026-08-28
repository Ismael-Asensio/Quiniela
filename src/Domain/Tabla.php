<?php
// ═══ Tabla — Entidad de Dominio (Tablas de Puntuación) ═══
class Tabla
{
    private $id;
    private $nombre;
    private $descripcion;
    private $creadaPor;
    private $fechaCreacion;
    private $totalMiembros;

    private function __construct() {}

    public static function fromRow(array $row): self
    {
        $t = new self();
        $t->id             = (int)($row['Id'] ?? 0);
        $t->nombre         = (string)($row['Nombre'] ?? '');
        $t->descripcion    = $row['Descripcion'] ?? null;
        $t->creadaPor      = isset($row['CreadaPor']) ? (int)$row['CreadaPor'] : null;
        $t->fechaCreacion  = self::fmt($row['FechaCreacion'] ?? null);
        $t->totalMiembros  = (int)($row['TotalMiembros'] ?? 0);
        return $t;
    }

    public function toArray(): array
    {
        return [
            'Id'              => $this->id,
            'Nombre'          => $this->nombre,
            'Descripcion'     => $this->descripcion,
            'FechaCreacion'   => $this->fechaCreacion,
            'TotalMiembros'   => $this->totalMiembros,
        ];
    }

    public function getId(): int        { return $this->id; }
    public function getNombre(): string { return $this->nombre; }

    private static function fmt($v): ?string
    {
        if ($v instanceof \DateTime) return $v->format('Y-m-d H:i:s');
        return is_string($v) && !empty($v) ? $v : null;
    }
}
