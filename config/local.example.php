<?php

// ═══════════════════════════════════════════════════════════════
// local.example.php — Plantilla de configuración local
// ═══════════════════════════════════════════════════════════════
// Copiar este archivo a config/local.php (NO se versiona, ver
// .gitignore) y completar con los valores reales del entorno.
//
//   cp config/local.example.php config/local.php
//
// Si config/local.php no existe, Config.php cae en los valores por
// defecto embebidos (los mismos que usaba el sistema antes de esta
// migración) — así que copiar y completar este archivo es opcional
// hasta que se ejecute la Fase 2.1 del plan de migración, que retira
// los valores reales del código fuente.
//
// Cualquier clave omitida aquí también cae al valor por defecto.
// ═══════════════════════════════════════════════════════════════

return [
    // ─── Base de Datos (SQL Server) ───
    'DB_SERVER'   => '172.16.8.175',
    'DB_NAME'     => 'DBQUINIELA',
    'DB_USER'     => 'sa',
    'DB_PASSWORD' => '',
    'DB_CHARSET'  => 'UTF-8',

    // ─── The Odds API — cuotas de mercado (https://the-odds-api.com/) ───
    'ODDS_API_KEY'  => '',
    'ODDS_API_BASE' => 'https://api.the-odds-api.com/v4',

    // ─── worldcup26.ir — desactivada ───
    'WORLDCUP_API_TOKEN' => '',
    'WORLDCUP_API_BASE'  => 'https://worldcup26.ir',
];
