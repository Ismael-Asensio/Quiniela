<?php

// ═══════════════════════════════════════════════════════════════
// SyncController — Controlador de Sincronización (APIs Externas)
// ═══════════════════════════════════════════════════════════════

class SyncController
{
    private $syncService;
    private $cuotasService;

    public function __construct()
    {
        $this->syncService = new SyncService();
        $this->cuotasService = new CuotasService();
    }

    public function syncPartidos(Request $request): void
    {
        AuthMiddleware::requireAdmin();

        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        // Mismo candado que usa la tarea programada (bin/sync_once.php).
        // Dos sincronizaciones simultáneas pueden insertar el mismo partido
        // dos veces, porque SyncService hace findByApiId() → create() sin
        // bloqueo de fila en medio.
        $lock = ProcessLock::sync();
        if (!$lock->adquirir()) {
            JsonResponse::error('Ya hay una sincronización en curso. Intenta de nuevo en unos segundos.', 409);
        }

        $result = $this->syncService->sincronizarPartidos();

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 500);
        }

        $stats = $result['stats'] ?? [];
        $partidosSinc = $stats['procesados'] ?? (($stats['nuevos'] ?? 0) + ($stats['actualizados'] ?? 0));
        $puntosRecalculados = ($stats['evaluados'] ?? 0) > 0 ? 1 : 0; // The JS just checks if it's truthy, or shows the number. We can pass evaluados or something similar.

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'stats' => [
                'partidosSincronizados' => $partidosSinc,
                'equiposInsertados' => $stats['nuevos'] ?? 0,
                'puntosRecalculados' => $stats['evaluados'] ?? 0
            ]
        ]);
        exit;
    }

    public function syncOdds(Request $request): void
    {
        AuthMiddleware::requireAdmin();

        if (!$request->isMethod('POST')) {
            JsonResponse::error('Método no permitido', 405);
        }

        $result = $this->cuotasService->sincronizarCuotas();

        if (!$result['success']) {
            JsonResponse::error($result['error'], $result['code'] ?? 500);
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'stats' => $result['stats'] ?? ['nuevos' => 0, 'actualizados' => 0]
        ]);
        exit;
    }
}
