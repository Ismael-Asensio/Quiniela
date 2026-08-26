<?php
// ═══ Equipo — Entidad de Dominio ═══
class Equipo
{
    private $id;
    private $nombre;
    private $codigoISO;
    private $banderaURL;
    private $idApiExterno;

    private function __construct() {}

    public static function fromRow(array $row): self
    {
        $e = new self();
        $e->id            = (int)($row['Id'] ?? 0);
        $e->nombre        = (string)($row['Nombre'] ?? '');
        $e->codigoISO     = $row['CodigoISO'] ?? null;
        $e->banderaURL    = $row['BanderaURL'] ?? null;
        $e->idApiExterno  = isset($row['IdApiExterno']) ? (int)$row['IdApiExterno'] : null;
        return $e;
    }

    public function toArray(): array
    {
        return [
            'Id'           => $this->id,
            'Nombre'       => $this->nombre,
            'CodigoISO'    => $this->codigoISO,
            'BanderaURL'   => $this->banderaURL,
        ];
    }

    public function getId(): int          { return $this->id; }
    public function getNombre(): string   { return $this->nombre; }
}
