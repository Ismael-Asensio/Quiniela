<?php

// ═══════════════════════════════════════════════════════════════
// PuntosService — Lógica de cálculo de Puntos Totales
// ═══════════════════════════════════════════════════════════════

class PuntosService
{
    private $usuarioRepo;

    public function __construct()
    {
        $this->usuarioRepo = new UsuarioRepository();
    }

    /**
     * Recalcula los puntos totales de un usuario específico.
     * Fórmula: SUM(Puntos Predicciones) - SUM(Penalizaciones Activas)
     *
     * @param int $idUsuario
     * @return bool True si fue exitoso
     */
    public function recalcularPuntosUsuario(int $idUsuario): bool
    {
        return $this->usuarioRepo->recalcularPuntos($idUsuario);
    }

    /**
     * Recalcula los puntos totales de todos los usuarios en el sistema.
     * Útil tras la sincronización de un partido finalizado.
     *
     * @return int Número de usuarios actualizados
     */
    public function recalcularPuntosTodos(): int
    {
        return $this->usuarioRepo->recalcularPuntosTodos();
    }

    /**
     * Calcula los puntos de una predicción individual.
     * Delega en Config::calcularPuntos que es una función pura.
     */
    public function calcularPuntosPrediccion(int $predLocal, int $predVisitante, ?int $realLocal, ?int $realVisitante, int $multiplicador = 1): int
    {
        return Config::calcularPuntos($predLocal, $predVisitante, $realLocal, $realVisitante, $multiplicador);
    }
}
