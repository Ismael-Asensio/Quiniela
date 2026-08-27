<?php
// ═══ Competicion — Entidad de Dominio ═══
class Competicion
{
    private $id;
    private $nombre;
    private $activa;
    private $fechaInicio;

    private function __construct() {}

    public static function fromRow(array $row): self
    {
        $c = new self();
        $c->id          = (int)($row['Id'] ?? 0);
        $c->nombre      = (string)($row['Nombre'] ?? '');
        $c->activa      = (bool)($row['Activa'] ?? false);
        $c->fechaInicio = $row['FechaInicio'] ?? null;
        return $c;
    }

    public function toArray(): array
    {
        return [
            'Id'          => $this->id,
            'Nombre'      => $this->nombre,
            'Activa'      => $this->activa,
            'FechaInicio' => $this->fechaInicio,
        ];
    }

    public function getId(): int        { return $this->id; }
    public function getNombre(): string { return $this->nombre; }
    public function isActiva(): bool    { return $this->activa; }
}
