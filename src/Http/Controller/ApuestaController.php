<?php

// ═══════════════════════════════════════════════════════════════
// ApuestaController — Controlador de Apuestas
// ═══════════════════════════════════════════════════════════════

class ApuestaController
{
    private $apuestaService;

    public function __construct()
    {
        $this->apuestaService = new ApuestaService();
    }

    public function placeBetMatch(Request $request): void
    {
        AuthMiddleware::requireMonetaryAccess();

        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $idUsuario = AuthMiddleware::getUserId();
        
        $idPartido = $request->inputInt('idPartido');
        $monto = $request->inputFloat('monto');
        $equipoApostado = $request->input('equipoApostado'); // Local, Empate, Visitante

        if ($idPartido <= 0 || $monto <= 0 || empty($equipoApostado)) {
            JsonResponse::error('Datos inválidos o incompletos', 400);
        }

        $result = $this->apuestaService->apostarPartido($idUsuario, $idPartido, $monto, $equipoApostado);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::success(['id' => $result['id']], $result['message']);
    }

    public function placeBetChampion(Request $request): void
    {
        AuthMiddleware::requireMonetaryAccess();

        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $idUsuario = AuthMiddleware::getUserId();
        
        $idEquipo = $request->inputInt('idEquipo');
        $monto = $request->inputFloat('monto');
        $cuotaManual = $request->inputFloat('cuota', 0);

        if ($idEquipo <= 0 || $monto <= 0) {
            JsonResponse::error('Datos inválidos o incompletos', 400);
        }

        $result = $this->apuestaService->apostarCampeon($idUsuario, $idEquipo, $monto, $cuotaManual);

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 400);
        }

        JsonResponse::success(['id' => $result['id'], 'cuota' => $result['cuota'], 'ganancia' => $result['ganancia']], $result['message']);
    }
}
