<?php

// ═══════════════════════════════════════════════════════════════
// PrediccionController — Controlador de Predicciones
// ═══════════════════════════════════════════════════════════════

class PrediccionController
{
    private $prediccionService;

    public function __construct()
    {
        $this->prediccionService = new PrediccionService();
    }

    public function save(Request $request): void
    {
        AuthMiddleware::requireAuth();

        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $idUsuario = AuthMiddleware::getUserId();
        
        $idPartido = $request->inputInt('idPartido');
        $golesLocal = $request->inputInt('golesLocal', -1);
        $golesVisitante = $request->inputInt('golesVisitante', -1);

        if ($idPartido <= 0 || $golesLocal < 0 || $golesVisitante < 0) {
            JsonResponse::error('Datos inválidos. Se requiere idPartido, golesLocal y golesVisitante.', 400);
        }

        $result = $this->prediccionService->guardar($idUsuario, $idPartido, $golesLocal, $golesVisitante);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::success($result['data'] ?? [], $result['message']);
    }

    // ─── ADMIN ───

    public function adminCreate(Request $request): void
    {
        AuthMiddleware::requireAdmin();

        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $idUsuarioDestino = $request->inputInt('IdUsuario');
        $idPartido = $request->inputInt('IdPartido');
        $golesLocal = $request->inputInt('GolesLocal', -1);
        $golesVisitante = $request->inputInt('GolesVisitante', -1);

        if ($idUsuarioDestino <= 0 || $idPartido <= 0 || $golesLocal < 0 || $golesVisitante < 0) {
            JsonResponse::error('Datos inválidos. Verifica todos los campos.', 400);
        }

        // Admin puede ignorar el bloqueo
        $result = $this->prediccionService->guardar($idUsuarioDestino, $idPartido, $golesLocal, $golesVisitante, true);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        // Recalcular puntos de la prediccion y del usuario en caso de que el partido haya finalizado
        $predRepo = new PrediccionRepository();
        $predRepo->updatePointsByMatch($idPartido, function($pL, $pV, $rL, $rV, $mult) {
            return Config::calcularPuntos($pL, $pV, $rL, $rV, $mult);
        });

        $puntosService = new PuntosService();
        $puntosService->recalcularPuntosUsuario($idUsuarioDestino);

        JsonResponse::success(null, "Predicción guardada y puntos sincronizados correctamente");
    }

    public function getOptions(Request $request): void
    {
        AuthMiddleware::requireAdmin();

        $opciones = $this->prediccionService->obtenerOpciones();

        JsonResponse::send([
            'success' => true,
            'usuarios' => $opciones['usuarios'],
            'partidos' => $opciones['partidos'],
        ]);
    }

    public function getAdminPredicciones(Request $request): void
    {
        AuthMiddleware::requireAdmin();

        $idPartido = $request->queryInt('idPartido');
        
        if ($idPartido > 0) {
            $predicciones = $this->prediccionService->obtenerPorPartido($idPartido);
            JsonResponse::send(['success' => true, 'predicciones' => $predicciones]);
        } else {
            $page = $request->queryInt('page', 1);
            $limit = $request->queryInt('limit', 50);
            $searchUser = $request->query('searchUser', '');
            $searchMatch = $request->query('searchMatch', '');
            $idCompeticion = $request->queryInt('idCompeticion', 0);

            $result = $this->prediccionService->obtenerPaginadas($page, $limit, $searchUser, $searchMatch, $idCompeticion);
            
            JsonResponse::send([
                'success' => true, 
                'predicciones' => $result['records'],
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'totalRecords' => $result['total'],
                    'totalPages' => ceil($result['total'] / $limit)
                ]
            ]);
        }
    }
}
