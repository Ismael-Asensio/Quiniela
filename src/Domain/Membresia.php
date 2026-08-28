<?php
// ═══ Membresia — Entidad de Dominio ═══
class Membresia
{
    private $id;
    private $idUsuario;
    private $montoPagado;
    private $fechaPago;
    private $estado;

    private function __construct() {}

    public static function fromRow(array $row): self
    {
        $m = new self();
        $m->id           = (int)($row['Id'] ?? 0);
        $m->idUsuario    = (int)($row['IdUsuario'] ?? 0);
        $m->montoPagado  = (float)($row['MontoPagado'] ?? 0);
        $m->fechaPago    = self::fmt($row['FechaPago'] ?? null);
        $m->estado       = (string)($row['Estado'] ?? 'Activa');
        return $m;
    }

    public function toArray(): array
    {
        return [
            'Id'          => $this->id,
            'IdUsuario'   => $this->idUsuario,
            'MontoPagado' => $this->montoPagado,
            'FechaPago'   => $this->fechaPago,
            'Estado'      => $this->estado,
        ];
    }

    public function getId(): int           { return $this->id; }
    public function getIdUsuario(): int    { return $this->idUsuario; }
    public function getMontoPagado(): float { return $this->montoPagado; }

    private static function fmt($v): ?string
    {
        if ($v instanceof \DateTime) return $v->format('Y-m-d H:i:s');
        return is_string($v) && !empty($v) ? $v : null;
    }
}
