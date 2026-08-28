<?php

// ═══════════════════════════════════════════════════════════════
// Partido — Entidad de Dominio
// ═══════════════════════════════════════════════════════════════

class Partido
{
    private $id;
    private $idApiExterno;
    private $fechaHoraCST;
    private $golesLocal;
    private $golesVisitante;
    private $estado;
    private $fase;
    private $multiplicadorFase;
    private $jornada;
    private $sede;
    private $cuotaLocal;
    private $cuotaEmpate;
    private $cuotaVisitante;

    private function __construct() {}

    public static function fromRow(array $row): self
    {
        $p = new self();
        $p->id                = (int)($row['Id'] ?? 0);
        $p->idApiExterno      = $row['IdApiExterno'] ?? null;
        $p->fechaHoraCST      = self::formatDT($row['FechaHoraCST'] ?? null);
        $p->golesLocal        = isset($row['GolesLocal']) ? (int)$row['GolesLocal'] : null;
        $p->golesVisitante    = isset($row['GolesVisitante']) ? (int)$row['GolesVisitante'] : null;
        $p->estado            = (string)($row['Estado'] ?? 'NS');
        $p->fase              = (string)($row['Fase'] ?? 'Grupos');
        $p->multiplicadorFase = (int)($row['MultiplicadorFase'] ?? 1);
        $p->jornada           = $row['Jornada'] ?? null;
        $p->sede              = $row['Sede'] ?? null;
        $p->cuotaLocal        = isset($row['CuotaLocal']) ? (float)$row['CuotaLocal'] : null;
        $p->cuotaEmpate       = isset($row['CuotaEmpate']) ? (float)$row['CuotaEmpate'] : null;
        $p->cuotaVisitante    = isset($row['CuotaVisitante']) ? (float)$row['CuotaVisitante'] : null;
        return $p;
    }

    public function toArray(): array
    {
        return [
            'Id'                => $this->id,
            'FechaHoraCST'      => $this->fechaHoraCST,
            'GolesLocal'        => $this->golesLocal,
            'GolesVisitante'    => $this->golesVisitante,
            'Estado'            => $this->estado,
            'Fase'              => $this->fase,
            'MultiplicadorFase' => $this->multiplicadorFase,
            'Jornada'           => $this->jornada,
            'Sede'              => $this->sede,
            'CuotaLocal'        => $this->cuotaLocal,
            'CuotaEmpate'       => $this->cuotaEmpate,
            'CuotaVisitante'    => $this->cuotaVisitante,
        ];
    }

    public function getId(): int                    { return $this->id; }
    public function getEstado(): string             { return $this->estado; }
    public function getFase(): string               { return $this->fase; }
    public function getMultiplicadorFase(): int      { return $this->multiplicadorFase; }
    public function getGolesLocal(): ?int            { return $this->golesLocal; }
    public function getGolesVisitante(): ?int        { return $this->golesVisitante; }
    public function getFechaHoraCST(): ?string       { return $this->fechaHoraCST; }

    public function isFinalizado(): bool
    {
        return in_array($this->estado, Config::ESTADOS_FINALIZADOS);
    }

    public function isBloqueado(): bool
    {
        return in_array($this->estado, Config::ESTADOS_BLOQUEADOS);
    }

    public function getCuota(string $tipo): ?float
    {
        $map = ['Local' => $this->cuotaLocal, 'Empate' => $this->cuotaEmpate, 'Visitante' => $this->cuotaVisitante];
        return $map[$tipo] ?? null;
    }

    private static function formatDT($v): ?string
    {
        if ($v instanceof \DateTime) return $v->format('Y-m-d H:i:s');
        return is_string($v) && !empty($v) ? $v : null;
    }
}
