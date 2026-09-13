<?php

// ═══════════════════════════════════════════════════════════════
// TesoreriaController — Controlador de Tesorería (Aprobación)
// ═══════════════════════════════════════════════════════════════

class TesoreriaController
{
    private $tesoreriaService;
    private $apuestaDashboardService;

    public function __construct()
    {
        $this->tesoreriaService = new TesoreriaService();
        $this->apuestaDashboardService = new ApuestaDashboardService();
    }

    /**
     * Todas las apuestas aprobadas de todos los usuarios, con pago
     * real contra el pozo y resumen global. Solo Tesorero o Admin
     * (igual que el resto de este controlador, no Miembro).
     */
    public function getAllBets(Request $request): void
    {
        AuthMiddleware::requireTesorero();

        $competicionId = self::resolverCompeticionId($request);

        $data = $this->apuestaDashboardService->getAllBets($competicionId);

        JsonResponse::send([
            'success' => true,
            'apuestasCampeon' => $data['apuestasCampeon'],
            'apuestasPartidos' => $data['apuestasPartidos'],
            'resumen' => $data['resumen'],
        ]);
    }

    /**
     * Replica el comportamiento legacy: ?competicion= (vacío) cae al
     * default igual que si el parámetro no viniera en absoluto.
     */
    private static function resolverCompeticionId(Request $request): int
    {
        $raw = $request->query('competicion', '');
        return $raw !== '' ? (int)$raw : Config::competicionActiva();
    }

    /**
     * Cola de apuestas pendientes con desglose y pago contra el pozo.
     * Contrato más rico que el que usaba TesoreriaService (dinero
     * proyectado, evento, número de apuesta, desglose histórico) —
     * es el que consume el frontend real.
     */
    public function getPendingBets(Request $request): void
    {
        AuthMiddleware::requireTesorero();

        if (!$request->isMethod('GET')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $competicionId = self::resolverCompeticionId($request);

        $data = $this->apuestaDashboardService->getPendingBetsDetallado($competicionId);

        JsonResponse::send(['success' => true, 'pendingBets' => $data['pendingBets']]);
    }

    public function approveBet(Request $request): void
    {
        AuthMiddleware::requireTesorero();

        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $id = $request->inputInt('id');
        $tipo = $request->input('tipo'); // Partido o Campeon

        if ($id <= 0 || empty($tipo)) {
            JsonResponse::error('Parámetros inválidos', 400);
        }

        $result = $this->tesoreriaService->aprobarApuesta($id, $tipo);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::success(null, $result['message']);
    }

    public function denyBet(Request $request): void
    {
        AuthMiddleware::requireTesorero();

        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $id = $request->inputInt('id');
        $tipo = $request->input('tipo'); // Partido o Campeon

        if ($id <= 0 || empty($tipo)) {
            JsonResponse::error('Parámetros inválidos', 400);
        }

        $result = $this->tesoreriaService->denegarApuesta($id, $tipo);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::success(null, $result['message']);
    }
}
