<?php
// ═══ Prediccion — Entidad de Dominio ═══
class Prediccion
{
    private $idUsuario;
    private $idPartido;
    private $golesLocal;
    private $golesVisitante;
    private $puntosObtenidos;
    private $fechaPrediccion;

    private function __construct() {}

    public static function fromRow(array $row): self
    {
        $p = new self();
        $p->idUsuario        = (int)($row['IdUsuario'] ?? 0);
        $p->idPartido        = (int)($row['IdPartido'] ?? 0);
        $p->golesLocal       = (int)($row['GolesLocal'] ?? 0);
        $p->golesVisitante   = (int)($row['GolesVisitante'] ?? 0);
        $p->puntosObtenidos  = (int)($row['PuntosObtenidos'] ?? 0);
        $p->fechaPrediccion  = self::fmt($row['FechaPrediccion'] ?? null);
        return $p;
    }

    public function toArray(): array
    {
        return [
            'IdUsuario'       => $this->idUsuario,
            'IdPartido'       => $this->idPartido,
            'GolesLocal'      => $this->golesLocal,
            'GolesVisitante'  => $this->golesVisitante,
            'PuntosObtenidos' => $this->puntosObtenidos,
            'FechaPrediccion' => $this->fechaPrediccion,
        ];
    }

    public function getIdUsuario(): int       { return $this->idUsuario; }
    public function getIdPartido(): int       { return $this->idPartido; }
    public function getGolesLocal(): int      { return $this->golesLocal; }
    public function getGolesVisitante(): int  { return $this->golesVisitante; }
    public function getPuntosObtenidos(): int { return $this->puntosObtenidos; }

    private static function fmt($v): ?string
    {
        if ($v instanceof \DateTime) return $v->format('Y-m-d H:i:s');
        return is_string($v) && !empty($v) ? $v : null;
    }
}
