<?php

// ═══════════════════════════════════════════════════════════════
// RankingController — Controlador del Ranking Global / Tablas
// ═══════════════════════════════════════════════════════════════

class RankingController
{
    private $rankingService;

    public function __construct()
    {
        $this->rankingService = new RankingService();
    }

    public function getRanking(Request $request): void
    {
        AuthMiddleware::requireAuth();

        if (!$request->isMethod('GET')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $idTabla = $request->queryInt('idTabla', 0);
        $esAdmin = AuthMiddleware::isAdmin();
        $idUsuario = AuthMiddleware::getUserId();

        $result = $this->rankingService->getRanking($idTabla, $idUsuario, $esAdmin);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::send([
            'success' => true,
            'nombreTabla' => $result['nombreTabla'],
            'descripcionTabla' => $result['descripcionTabla'],
            'faseInicio' => $result['faseInicio'] ?? 'Grupos',
            'ranking' => $result['ranking'],
            'tablasDisponibles' => $result['tablasDisponibles'],
            'tablaActual' => $result['tablaActual']
        ]);
    }
}
