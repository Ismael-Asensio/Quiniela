<?php

// ═══════════════════════════════════════════════════════════════
// CompeticionController — Controlador de Competiciones
// ═══════════════════════════════════════════════════════════════

class CompeticionController
{
    private $competicionService;

    public function __construct()
    {
        $this->competicionService = new CompeticionService();
    }

    public function listar(Request $request): void
    {
        AuthMiddleware::requireAuth();

        $competiciones = $this->competicionService->listar();

        JsonResponse::send(['success' => true, 'competiciones' => $competiciones]);
    }
}
