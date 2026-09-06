<?php

// ═══════════════════════════════════════════════════════════════
// PozoService — Reparto de premios contra el pozo real de apuestas
// ═══════════════════════════════════════════════════════════════
// Movido tal cual desde config/database.php (getPozosPartidos,
// getPozosCampeon, calcularPagoConPozoPartido/Campeon). La pieza con
// más riesgo financiero: si la ganancia comprometida del lado ganador
// supera el 90% del pozo aprobado, se paga a prorrata.
// ═══════════════════════════════════════════════════════════════

class PozoService
{
    private $pozoRepo;

    public function __construct()
    {
        $this->pozoRepo = new PozoRepository();
    }

    /**
     * Pozo de apuestas de partido: total por partido y desglose de
     * monto/ganancia comprometida por opción (Local/Empate/Visitante).
     * Forma: [IdPartido => ['PTotal' => float, 'Opciones' => [Equipo => ['Monto', 'Ganancia']]]]
     */
    public function getPozoPartidos(int $competicionId): array
    {
        $pozos = [];
        foreach ($this->pozoRepo->findPozoPartidos($competicionId) as $row) {
            $id = $row['IdPartido'];
            $equipo = $row['EquipoApostado'];
            $monto = floatval($row['Monto']);

            if (!isset($pozos[$id])) {
                $pozos[$id] = ['PTotal' => 0, 'Opciones' => []];
            }
            $pozos[$id]['Opciones'][$equipo] = [
                'Monto' => $monto,
                'Ganancia' => floatval($row['Ganancia']),
            ];
            $pozos[$id]['PTotal'] += $monto;
        }
        return $pozos;
    }

    /**
     * Pozo de apuestas al campeón: total global y desglose de
     * monto/ganancia comprometida por equipo.
     * Forma: ['PTotal' => float, 'Equipos' => [IdEquipo => ['Monto', 'Ganancia']]]
     */
    public function getPozoCampeon(int $competicionId): array
    {
        $pozos = ['PTotal' => 0, 'Equipos' => []];
        foreach ($this->pozoRepo->findPozoCampeon($competicionId) as $row) {
            $id = $row['IdEquipo'];
            $monto = floatval($row['Monto']);
            $pozos['Equipos'][$id] = [
                'Monto' => $monto,
                'Ganancia' => floatval($row['Ganancia']),
            ];
            $pozos['PTotal'] += $monto;
        }
        return $pozos;
    }

    /**
     * Pago real de una apuesta de partido contra el pozo (ver
     * calcularPago() para la fórmula).
     */
    public function calcularPagoPartido(array $pozos, int $idPartido, string $equipoApostado, float $montoApostadoUsuario, float $cuotaUsuario, int $isAprobado = 1): float
    {
        $pozoAprobado = $pozos[$idPartido]['PTotal'] ?? 0;
        $gananciaAprobada = $pozos[$idPartido]['Opciones'][$equipoApostado]['Ganancia'] ?? 0;
        return $this->calcularPago($pozoAprobado, $gananciaAprobada, $montoApostadoUsuario, $cuotaUsuario, $isAprobado);
    }

    /**
     * Pago real de una apuesta al campeón contra el pozo (ver
     * calcularPago() para la fórmula).
     */
    public function calcularPagoCampeon(array $pozos, int $idEquipo, float $montoApostadoUsuario, float $cuotaUsuario, int $isAprobado = 1): float
    {
        $pozoAprobado = $pozos['PTotal'] ?? 0;
        $gananciaAprobada = $pozos['Equipos'][$idEquipo]['Ganancia'] ?? 0;
        return $this->calcularPago($pozoAprobado, $gananciaAprobada, $montoApostadoUsuario, $cuotaUsuario, $isAprobado);
    }

    /**
     * Fórmula de pago compartida por partido y campeón (antes
     * duplicada byte a byte en calcularPagoConPozoPartido/Campeon):
     *
     * - Aprobada: si la ganancia comprometida del lado ganador supera
     *   el 90% del pozo aprobado, se paga a prorrata; si no, se paga
     *   la ganancia proyectada completa.
     * - Pendiente (no aprobada): proyección considerando el pozo y la
     *   ganancia comprometida COMO SI esta apuesta también se aprobara,
     *   sin superar nunca la ganancia proyectada propia.
     */
    private function calcularPago(float $pozoAprobado, float $gananciaAprobada, float $montoApostadoUsuario, float $cuotaUsuario, int $isAprobado): float
    {
        $miGananciaProyectada = $montoApostadoUsuario * $cuotaUsuario;

        if ($isAprobado == 1) {
            $fondoDisponible = $pozoAprobado * 0.90;
            if ($gananciaAprobada == 0) {
                return 0;
            }

            if ($gananciaAprobada > $fondoDisponible) {
                $pago = ($miGananciaProyectada / $gananciaAprobada) * $fondoDisponible;
            } else {
                $pago = $miGananciaProyectada;
            }
            return round($pago, 2);
        }

        $pozoNuevo = $pozoAprobado + $montoApostadoUsuario;
        $denominador = $gananciaAprobada + $montoApostadoUsuario;
        if ($denominador == 0) {
            return 0;
        }

        $pago = ($miGananciaProyectada / $denominador) * $pozoNuevo;
        if ($pago > $miGananciaProyectada) {
            $pago = $miGananciaProyectada;
        }
        return round($pago, 2);
    }
}
