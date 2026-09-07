<?php

// ═══════════════════════════════════════════════════════════════
// CompeticionService — Lógica de Competiciones
// ═══════════════════════════════════════════════════════════════

class CompeticionService
{
    private $competicionRepo;

    public function __construct()
    {
        $this->competicionRepo = new CompeticionRepository();
    }

    public function listar(): array
    {
        $rows = $this->competicionRepo->findAll();

        return array_map(function (array $row) {
            return Competicion::fromRow($row)->toArray();
        }, $rows);
    }
}
