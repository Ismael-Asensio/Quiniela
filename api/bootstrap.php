<?php

// ═══════════════════════════════════════════════════════════════
// bootstrap.php — Punto de arranque único para endpoints de la API
// ═══════════════════════════════════════════════════════════════
// Centraliza lo que antes cada api/<dominio>/<accion>.php repetía a
// mano: cargar la arquitectura (config/database.php), arrancar la
// sesión y aplicar CORS. Antes de este archivo había variaciones
// entre endpoints (algunos mandaban headers de CORS manualmente,
// otros no aplicaban ninguno) — aquí queda un único comportamiento.
//
// Uso en un entry point:
//   <?php
//   require_once __DIR__ . '/../bootstrap.php';
//   $controller = new PrediccionController();
//   $controller->save(Request::capture());
// ═══════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/database.php';

AuthMiddleware::configureSessionCookie();
session_start();

AuthMiddleware::handleCors();
