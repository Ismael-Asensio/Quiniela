<?php

// ═══════════════════════════════════════════════════════════════
// PrediccionService — Lógica de Negocio de Predicciones
// ═══════════════════════════════════════════════════════════════

class PrediccionService
{
    private $prediccionRepo;
    private $partidoRepo;
    private $usuarioRepo;

    public function __construct()
    {
        $this->prediccionRepo = new PrediccionRepository();
        $this->partidoRepo = new PartidoRepository();
        $this->usuarioRepo = new UsuarioRepository();
    }

    /**
     * Usuarios y partidos disponibles para el formulario de "crear
     * predicción de administrador". No filtra por competición: el
     * admin puede crear una predicción sobre cualquier partido.
     */
    public function obtenerOpciones(): array
    {
        $usuarios = $this->usuarioRepo->findAll();

        $partidos = array_map(function (array $row) {
            return [
                'Id' => $row['Id'],
                'Fase' => $row['Fase'],
                'FechaHoraCST' => $row['FechaHoraCST'] instanceof \DateTime ? $row['FechaHoraCST']->format('Y-m-d H:i') : $row['FechaHoraCST'],
                'Estado' => $row['Estado'],
                'NombreLocal' => $row['NombreLocal'] ?? 'Por definir',
                'NombreVisitante' => $row['NombreVisitante'] ?? 'Por definir',
            ];
        }, $this->partidoRepo->findAllForAdminOptions());

        return ['usuarios' => $usuarios, 'partidos' => $partidos];
    }

    /**
     * Guarda una predicción validando reglas de negocio (bloqueo por tiempo).
     */
    public function guardar(int $idUsuario, int $idPartido, int $golesLocal, int $golesVisitante, bool $ignorarBloqueoAdmin = false): array
    {
        if ($golesLocal < 0 || $golesVisitante < 0) {
            return ['success' => false, 'error' => 'Los goles no pueden ser negativos', 'code' => 400];
        }

        $partidoRow = $this->partidoRepo->findById($idPartido);
        if (!$partidoRow) {
            return ['success' => false, 'error' => 'Partido no encontrado', 'code' => 404];
        }

        $partido = Partido::fromRow($partidoRow);

        // Validar bloqueo
        if (!$ignorarBloqueoAdmin) {
            $ahora = Config::now();
            $fechaDT = new \DateTime($partido->getFechaHoraCST(), new \DateTimeZone(Config::TIMEZONE));
            
            if ($ahora >= $fechaDT || $partido->isBloqueado()) {
                return ['success' => false, 'error' => 'El partido ya ha comenzado o finalizado. No se permiten más predicciones.', 'code' => 403];
            }
        }

        $this->prediccionRepo->save($idUsuario, $idPartido, $golesLocal, $golesVisitante);

        return [
            'success' => true, 
            'message' => 'Predicción guardada correctamente',
            'data' => [
                'prediccion' => [
                    'Fase' => $partido->getFase(),
                    'MultiplicadorFase' => Config::mapFase($partido->getFase())['multiplicador'],
                    'FechaPrediccion' => Config::now()->format('Y-m-d H:i:s')
                ]
            ]
        ];
    }

    /**
     * Obtiene las predicciones de un partido específico con los datos de los usuarios.
     * Útil para la vista de Admin o cuando el partido ya empezó.
     */
    public function obtenerPorPartido(int $idPartido): array
    {
        return $this->prediccionRepo->findByMatch($idPartido);
    }

    /**
     * Obtiene las predicciones registradas en el sistema, paginadas y filtradas.
     * Útil para la vista general de Administración.
     */
    public function obtenerPaginadas(int $page = 1, int $limit = 50, string $searchUser = '', string $searchMatch = '', int $idCompeticion = 0): array
    {
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 50;
        
        $offset = ($page - 1) * $limit;
        
        return $this->prediccionRepo->findAllPaginated($limit, $offset, $searchUser, $searchMatch, $idCompeticion);
    }
}
