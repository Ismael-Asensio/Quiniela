<?php

// ═══════════════════════════════════════════════════════════════
// PartidoService — Lógica de Negocio de Partidos
// ═══════════════════════════════════════════════════════════════

class PartidoService
{
    private $partidoRepo;
    private $prediccionRepo;
    private $cuotaCampeonRepo;

    public function __construct()
    {
        $this->partidoRepo = new PartidoRepository();
        $this->prediccionRepo = new PrediccionRepository();
        $this->cuotaCampeonRepo = new CuotaCampeonRepository();
    }

    /**
     * IDs de equipos que perdieron un partido de fase eliminatoria (no
     * incluye exclusiones manuales/automáticas de CuotasCampeonFijas).
     * Fuente única usada por getEquiposEliminados() y por
     * autoExcluirEliminados().
     */
    private function getPerdedoresFaseEliminatoria(int $competicionId): array
    {
        $perdedores = [];
        foreach ($this->partidoRepo->findFinalizadosEliminatoriosConGoles($competicionId) as $row) {
            $gLocal = (int)$row['GolesLocal'];
            $gVisit = (int)$row['GolesVisitante'];
            if ($gLocal > $gVisit) {
                $perdedores[] = (int)$row['IdVisitante'];
            } elseif ($gVisit > $gLocal) {
                $perdedores[] = (int)$row['IdLocal'];
            }
        }
        return array_values(array_unique($perdedores));
    }

    /**
     * IDs de equipos ya eliminados del torneo: perdieron un partido de
     * fase eliminatoria, o fueron excluidos manualmente en
     * CuotasCampeonFijas. Usado para no ofrecer apuestas al campeón
     * sobre un equipo que ya no puede ganar.
     */
    public function getEquiposEliminados(?int $competicionId = null): array
    {
        $compId = $competicionId ?? Config::competicionActiva();
        $eliminados = $this->getPerdedoresFaseEliminatoria($compId);

        foreach ($this->cuotaCampeonRepo->getExcluidoIds() as $idExcluido) {
            $eliminados[] = $idExcluido;
        }

        return array_values(array_unique($eliminados));
    }

    /**
     * Excluye automáticamente en CuotasCampeonFijas a los equipos que acaban
     * de perder un partido de fase eliminatoria, para que el admin no tenga
     * que marcar "Excluido" a mano tras cada sincronización. Idempotente:
     * cada llamada vuelve a derivar la lista completa de perdedores desde
     * los partidos y sólo toca los que aún no están marcados. Conserva la
     * CuotaFija existente si el equipo ya tenía una; si no, usa el piso 1.01
     * (mismo placeholder que CuotaCampeonController::save() al excluir sin
     * cuota). Es de una sola dirección: no des-excluye equipos si el admin
     * revierte la marca a mano; eso ya era así para las exclusiones manuales.
     *
     * @return int[] IDs recién excluidos en esta llamada (para logging)
     */
    public function autoExcluirEliminados(?int $competicionId = null): array
    {
        $compId = $competicionId ?? Config::competicionActiva();
        $perdedores = $this->getPerdedoresFaseEliminatoria($compId);
        $yaExcluidos = $this->cuotaCampeonRepo->getExcluidoIds();

        $nuevosExcluidos = [];
        foreach ($perdedores as $idEquipo) {
            if (in_array($idEquipo, $yaExcluidos, true)) {
                continue;
            }
            $fija = $this->cuotaCampeonRepo->findByEquipo($idEquipo);
            $cuotaFija = $fija ? (float)$fija['CuotaFija'] : 1.01;
            $this->cuotaCampeonRepo->upsert($idEquipo, $cuotaFija, true, null);
            $nuevosExcluidos[] = $idEquipo;
        }

        return $nuevosExcluidos;
    }

    /**
     * Lista los partidos con predicciones del usuario actual, 
     * aplicando filtros opcionales.
     */
    public function listarConPredicciones(int $idUsuario, ?string $fecha = null, ?string $fase = null, ?string $estado = null): array
    {
        $partidosRaw = $this->partidoRepo->findAllWithTeams($fecha, $fase, $estado);
        $prediccionesUsuario = $this->prediccionRepo->findByUser($idUsuario);

        // Indexar predicciones por IdPartido para búsqueda rápida O(1)
        $predsIndexadas = [];
        foreach ($prediccionesUsuario as $pr) {
            $predsIndexadas[$pr['IdPartido']] = $pr;
        }

        $ahora = Config::now();
        $result = [];

        foreach ($partidosRaw as $row) {
            $p = Partido::fromRow($row);
            
            // Determinar si está bloqueado por tiempo o estado
            $fechaDT = new \DateTime($p->getFechaHoraCST(), new \DateTimeZone(Config::TIMEZONE));
            $bloqueado = ($ahora >= $fechaDT) || $p->isBloqueado();

            // Adjuntar predicción si existe
            $prediccionFormat = null;
            if (isset($predsIndexadas[$p->getId()])) {
                $prRaw = $predsIndexadas[$p->getId()];
                $pr = Prediccion::fromRow($prRaw);
                $prediccionFormat = [
                    'GolesLocal'      => $pr->getGolesLocal(),
                    'GolesVisitante'  => $pr->getGolesVisitante(),
                    'PuntosObtenidos' => $pr->getPuntosObtenidos(),
                    'FechaPrediccion' => $prRaw['FechaPrediccion'] instanceof \DateTime 
                        ? $prRaw['FechaPrediccion']->format('Y-m-d H:i:s') 
                        : $prRaw['FechaPrediccion']
                ];
            }

            $result[] = [
                'Id'                => $p->getId(),
                'FechaHoraCST'      => $p->getFechaHoraCST(),
                'GolesLocal'        => $p->getGolesLocal(),
                'GolesVisitante'    => $p->getGolesVisitante(),
                'Estado'            => $p->getEstado(),
                'Fase'              => $p->getFase(),
                'MultiplicadorFase' => $p->getMultiplicadorFase(),
                'Jornada'           => $row['Jornada'] ?? null,
                'Sede'              => $row['Sede'] ?? null,
                'Bloqueado'         => $bloqueado,
                'CuotaLocal'        => $p->getCuota('Local'),
                'CuotaEmpate'       => $p->getCuota('Empate'),
                'CuotaVisitante'    => $p->getCuota('Visitante'),
                'EquipoLocal' => [
                    'Id'         => $row['IdEquipoLocal'],
                    'Nombre'     => $row['NombreLocal'],
                    'CodigoISO'  => $row['ISOLocal'],
                    'BanderaURL' => $row['BanderaLocal']
                ],
                'EquipoVisitante' => [
                    'Id'         => $row['IdEquipoVisitante'],
                    'Nombre'     => $row['NombreVisitante'],
                    'CodigoISO'  => $row['ISOVisitante'],
                    'BanderaURL' => $row['BanderaVisitante']
                ],
                'Prediccion' => $prediccionFormat
            ];
        }

        return $result;
    }

    public function obtenerFasesUnicas(): array
    {
        $fases = $this->partidoRepo->getFasesUnicas();
        return array_column($fases, 'Fase');
    }

    public function obtenerFechasUnicas(): array
    {
        $fechas = $this->partidoRepo->getFechasUnicas();
        return array_column($fechas, 'Fecha');
    }
}
