<?php

// ═══════════════════════════════════════════════════════════════
// PartidoController — Controlador de Partidos
// ═══════════════════════════════════════════════════════════════

class PartidoController
{
    private $partidoService;

    public function __construct()
    {
        $this->partidoService = new PartidoService();
    }

    public function getPartidos(Request $request): void
    {
        AuthMiddleware::requireAuth();

        if (!$request->isMethod('GET')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $idUsuario = AuthMiddleware::getUserId();
        
        // Filtros opcionales
        $fecha = $request->query('fecha') ?: null;
        $fase = $request->query('fase') ?: null;
        $estado = $request->query('estado') ?: null;

        $partidos = $this->partidoService->listarConPredicciones($idUsuario, $fecha, $fase, $estado);
        $fases = $this->partidoService->obtenerFasesUnicas();
        $fechas = $this->partidoService->obtenerFechasUnicas();

        JsonResponse::send([
            'partidos' => $partidos,
            'fases' => $fases,
            'fechas' => $fechas,
            'horaServidor' => Config::now()->format('Y-m-d H:i:s')
        ]);
    }
}
