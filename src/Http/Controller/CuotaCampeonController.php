<?php

// ═══════════════════════════════════════════════════════════════
// CuotaCampeonController — Controlador de Cuotas Fijas Campeón
// ═══════════════════════════════════════════════════════════════

class CuotaCampeonController
{
    private $service;

    public function __construct()
    {
        $this->service = new CuotaCampeonService();
    }

    /**
     * Listar todos los equipos con sus cuotas fijas y dinámicas
     */
    public function list(): void
    {
        AuthMiddleware::requireAdmin();

        $equipos = $this->service->getAllEquiposConCuotas();

        JsonResponse::success(['equipos' => $equipos], 'Cuotas de campeón cargadas');
    }

    /**
     * Guardar/actualizar cuota fija de un equipo
     */
    public function save(Request $request): void
    {
        AuthMiddleware::requireAdmin();

        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $idEquipo  = $request->inputInt('idEquipo');
        $cuotaFija = $request->inputFloat('cuotaFija');
        $excluido  = (bool)$request->inputInt('excluido', 0);

        if ($idEquipo <= 0) {
            JsonResponse::error('ID de equipo inválido', 400);
        }

        if ($cuotaFija <= 0 && !$excluido) {
            JsonResponse::error('La cuota fija debe ser mayor a 0', 400);
        }

        // Si solo se excluye sin cuota, poner cuota mínima como placeholder
        if ($cuotaFija <= 0 && $excluido) {
            $cuotaFija = 1.01;
        }

        $adminId = AuthMiddleware::getUserId();
        $result = $this->service->setFixed($idEquipo, $cuotaFija, $excluido, $adminId);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::success([], $result['message']);
    }

    /**
     * Eliminar cuota fija de un equipo
     */
    public function remove(Request $request): void
    {
        AuthMiddleware::requireAdmin();

        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $idEquipo = $request->inputInt('idEquipo');

        if ($idEquipo <= 0) {
            JsonResponse::error('ID de equipo inválido', 400);
        }

        $result = $this->service->removeFixed($idEquipo);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::success([], $result['message']);
    }
}
