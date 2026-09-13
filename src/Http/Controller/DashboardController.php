<?php

// ═══════════════════════════════════════════════════════════════
// DashboardController — Controlador de Dashboards
// ═══════════════════════════════════════════════════════════════

class DashboardController
{
    private $dashboardService;
    private $apuestaDashboardService;

    public function __construct()
    {
        $this->dashboardService = new DashboardService();
        $this->apuestaDashboardService = new ApuestaDashboardService();
    }

    public function getUserDashboard(Request $request): void
    {
        AuthMiddleware::requireAuth();

        if (!$request->isMethod('GET')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $idUsuario = AuthMiddleware::getUserId();
        $idTabla = $request->queryInt('idTabla', 0);
        $esAdmin = AuthMiddleware::isAdmin();

        $data = $this->dashboardService->getUserDashboard($idUsuario, $idTabla, $esAdmin);

        JsonResponse::send($data);
    }

    /**
     * Gráficos de apuestas visibles a cualquier usuario logueado
     * (no requiere rol de Miembro pese al nombre del endpoint legacy).
     */
    public function getMemberCharts(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $competicionId = self::resolverCompeticionId($request);

        $data = $this->apuestaDashboardService->getMemberCharts($competicionId);

        JsonResponse::send([
            'success' => true,
            'campeon' => $data['campeon'],
            'partidos' => $data['partidos'],
        ]);
    }

    /**
     * Dashboard financiero personal del Miembro (o Admin).
     */
    public function getMiembroDashboard(Request $request): void
    {
        AuthMiddleware::requireMember();

        $idUsuario = AuthMiddleware::getUserId();
        $competicionId = self::resolverCompeticionId($request);

        $data = $this->apuestaDashboardService->getMiembroDashboard($idUsuario, $competicionId);

        JsonResponse::send([
            'success' => true,
            'resumen' => $data['resumen'],
            'apuestasPartidos' => $data['apuestasPartidos'],
            'apuestasCampeon' => $data['apuestasCampeon'],
            'equipos' => $data['equipos'],
            'membresia' => $data['membresia'],
        ]);
    }

    /**
     * Dashboard de tesorería. Admin, Tesorero o Miembro.
     */
    public function getTesoreroDashboard(Request $request): void
    {
        AuthMiddleware::requireMonetaryAccess();

        $competicionId = self::resolverCompeticionId($request);

        $data = $this->apuestaDashboardService->getTesoreroDashboard($competicionId);

        JsonResponse::send([
            'success' => true,
            'membresias' => $data['membresias'],
            'campeonPorEquipo' => $data['campeonPorEquipo'],
            'recaudacionPorDia' => $data['recaudacionPorDia'],
            'membresiasRecientes' => $data['membresiasRecientes'],
            'apuestasResumen' => $data['apuestasResumen'],
            'membresiasPorDia' => $data['membresiasPorDia'],
        ]);
    }

    /**
     * Replica el comportamiento legacy: ?competicion= (vacío) cae al
     * default igual que si el parámetro no viniera en absoluto. Un
     * Request::queryInt() normal no distingue ausente de cadena vacía.
     */
    private static function resolverCompeticionId(Request $request): int
    {
        $raw = $request->query('competicion', '');
        return $raw !== '' ? (int)$raw : Config::competicionActiva();
    }
}
