<?php

// ═══════════════════════════════════════════════════════════════
// TesoreriaService — Lógica de Aprobación de Apuestas
// ═══════════════════════════════════════════════════════════════

class TesoreriaService
{
    private $apuestaRepo;

    public function __construct()
    {
        $this->apuestaRepo = new ApuestaRepository();
    }

    public function aprobarApuesta(int $idApuesta, string $tipo): array
    {
        if ($tipo === 'Partido') {
            $filas = $this->apuestaRepo->approveMatchBet($idApuesta);
        } elseif ($tipo === 'Campeon') {
            $filas = $this->apuestaRepo->approveChampionBet($idApuesta);
        } else {
            return ['success' => false, 'error' => 'Tipo de apuesta inválido', 'code' => 400];
        }

        // 0 filas: no existía o ya estaba aprobada (doble clic, dos
        // tesoreros a la vez). No es un error del cliente, pero tampoco
        // hay nada nuevo que aprobar.
        if ($filas === 0) {
            return ['success' => false, 'error' => 'La apuesta ya fue procesada o no existe', 'code' => 409];
        }

        return ['success' => true, 'message' => "Apuesta de " . ($tipo === 'Partido' ? 'partido' : 'campeón') . ' aprobada'];
    }

    public function denegarApuesta(int $idApuesta, string $tipo): array
    {
        if ($tipo === 'Partido') {
            $filas = $this->apuestaRepo->updateMatchBetStatus($idApuesta, Apuesta::ESTADO_DENEGADA);
        } elseif ($tipo === 'Campeon') {
            $filas = $this->apuestaRepo->updateChampionBetStatus($idApuesta, Apuesta::ESTADO_DENEGADA);
        } else {
            return ['success' => false, 'error' => 'Tipo de apuesta inválido', 'code' => 400];
        }

        if ($filas === 0) {
            return ['success' => false, 'error' => 'La apuesta ya fue procesada o no existe', 'code' => 409];
        }

        return ['success' => true, 'message' => 'Apuesta denegada'];
    }
}
