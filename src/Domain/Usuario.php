<?php

// ═══════════════════════════════════════════════════════════════
// Usuario — Entidad de Dominio
// ═══════════════════════════════════════════════════════════════

class Usuario
{
    private $id;
    private $nombreUsuario;
    private $nombreCompleto;
    private $puntosTotales;
    private $idRol;
    private $fechaCreacion;

    private function __construct() {}

    public static function fromRow(array $row): self
    {
        $u = new self();
        $u->id              = (int)($row['Id'] ?? 0);
        $u->nombreUsuario   = (string)($row['NombreUsuario'] ?? '');
        $u->nombreCompleto  = $row['NombreCompleto'] ?? null;
        $u->puntosTotales   = (int)($row['PuntosTotales'] ?? 0);
        $u->idRol           = (int)($row['IdRol'] ?? Config::ROL_USUARIO);
        $u->fechaCreacion   = self::formatDT($row['FechaCreacion'] ?? null);
        return $u;
    }

    public function toArray(): array
    {
        return [
            'Id'              => $this->id,
            'NombreUsuario'   => $this->nombreUsuario,
            'NombreCompleto'  => $this->nombreCompleto,
            'PuntosTotales'   => $this->puntosTotales,
            'IdRol'           => $this->idRol,
        ];
    }

    public function getId(): int              { return $this->id; }
    public function getNombreUsuario(): string { return $this->nombreUsuario; }
    public function getNombreCompleto(): ?string { return $this->nombreCompleto; }
    public function getPuntosTotales(): int    { return $this->puntosTotales; }
    public function getIdRol(): int            { return $this->idRol; }

    private static function formatDT($v): ?string
    {
        if ($v instanceof \DateTime) return $v->format('Y-m-d H:i:s');
        return is_string($v) && !empty($v) ? $v : null;
    }
}
