<?php

// ═══════════════════════════════════════════════════════════════
// database.php — Bootstrap de la arquitectura
// ═══════════════════════════════════════════════════════════════
// Único punto de entrada que registra el autoloader. Hasta la Fase 5
// del plan de migración también exponía funciones legacy (getConnection,
// isAdmin, calcularPuntos, getChampionOdds, etc.) como wrappers de
// compatibilidad; con los 35 endpoints ya migrados a la arquitectura
// por capas, ninguna tenía consumidores reales y se retiraron.
// ═══════════════════════════════════════════════════════════════

require_once __DIR__ . '/../src/Core/Autoloader.php';

Autoloader::register(__DIR__ . '/../src');
