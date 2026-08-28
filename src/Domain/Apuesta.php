<?php
// ═══ Apuesta — Entidad de Dominio ═══
class Apuesta
{
    private $id;
    private $idUsuario;
    private $montoApostado;
    private $cuotaFijada;
    private $estadoApuesta;
    private $ganancia;
    private $fecha;
    // Partido-specific
    private $idPartido;
    private $equipoApostado;
    // Campeon-specific
    private $idEquipo;

    const ESTADO_PENDIENTE = 'Pendiente';
    const ESTADO_GANADA    = 'Ganada';
    const ESTADO_PERDIDA   = 'Perdida';
    const ESTADO_DENEGADA  = 'Denegada';

    private function __construct() {}

    public static function fromRow(array $row): self
    {
        $a = new self();
        $a->id              = (int)($row['Id'] ?? 0);
        $a->idUsuario       = (int)($row['IdUsuario'] ?? 0);
        $a->montoApostado   = (float)($row['MontoApostado'] ?? 0);
        $a->cuotaFijada     = (float)($row['CuotaFijada'] ?? 0);
        $a->estadoApuesta   = (string)($row['EstadoApuesta'] ?? self::ESTADO_PENDIENTE);
        $a->ganancia        = isset($row['Ganancia']) ? (float)$row['Ganancia'] : null;
        $a->fecha           = self::fmt($row['Fecha'] ?? null);
        $a->idPartido       = isset($row['IdPartido']) ? (int)$row['IdPartido'] : null;
        $a->equipoApostado  = $row['EquipoApostado'] ?? null;
        $a->idEquipo        = isset($row['IdEquipo']) ? (int)$row['IdEquipo'] : null;
        return $a;
    }

    public function toArray(): array
    {
        return [
            'Id'             => $this->id,
            'IdUsuario'      => $this->idUsuario,
            'MontoApostado'  => $this->montoApostado,
            'CuotaFijada'    => $this->cuotaFijada,
            'EstadoApuesta'  => $this->estadoApuesta,
            'Ganancia'       => $this->ganancia,
            'EquipoApostado' => $this->equipoApostado,
        ];
    }

    public function getId(): int                { return $this->id; }
    public function getIdUsuario(): int         { return $this->idUsuario; }
    public function getMontoApostado(): float   { return $this->montoApostado; }
    public function getCuotaFijada(): float     { return $this->cuotaFijada; }
    public function getEstadoApuesta(): string  { return $this->estadoApuesta; }
    public function getEquipoApostado(): ?string { return $this->equipoApostado; }
    public function getGananciaPotencial(): float { return round($this->montoApostado * $this->cuotaFijada, 2); }

    private static function fmt($v): ?string
    {
        if ($v instanceof \DateTime) return $v->format('Y-m-d H:i:s');
        return is_string($v) && !empty($v) ? $v : null;
    }
}
