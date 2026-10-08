<?php

// ═══════════════════════════════════════════════════════════════
// capture_contracts.php — Golden Files de Contratos JSON (Fase 0.3)
// ═══════════════════════════════════════════════════════════════
// Llama a los endpoints de SOLO LECTURA autenticado como admin y
// como usuario normal, y guarda la respuesta JSON cruda en
// tests/golden/<nombre>.json. Sirve de referencia para comparar
// (diff) tras cada migración de la Fase 4 del plan.
//
// IMPORTANTE — alcance deliberadamente limitado:
// Este script NO ejecuta ningún endpoint que escriba en la base de
// datos, cobre cuota de una API externa, o mueva dinero real
// (apuestas, aprobaciones, cambios de rol, sincronización). Ver la
// lista MUTATING_ENDPOINTS_EXCLUIDOS más abajo para el motivo de
// cada exclusión y cómo capturarlos manualmente si hace falta.
//
// Uso:
//   php bin/capture_contracts.php <baseUrl> <adminUser> <adminPass> [memberUser] [memberPass]
//
// Ejemplo:
//   php bin/capture_contracts.php http://localhost/Quiniela iasensio ******** carlos ********
//
// Requiere PHP con curl. No requiere sqlsrv (habla HTTP, no BD directa).
// ═══════════════════════════════════════════════════════════════

if ($argc < 4) {
    fwrite(STDERR, "Uso: php bin/capture_contracts.php <baseUrl> <adminUser> <adminPass> [memberUser] [memberPass]\n");
    fwrite(STDERR, "Ejemplo: php bin/capture_contracts.php http://localhost/Quiniela iasensio secret carlos secret\n");
    exit(1);
}

$baseUrl    = rtrim($argv[1], '/');
$adminUser  = $argv[2];
$adminPass  = $argv[3];
$memberUser = $argv[4] ?? null;
$memberPass = $argv[5] ?? null;

$goldenDir = __DIR__ . '/../tests/golden';
if (!is_dir($goldenDir)) {
    mkdir($goldenDir, 0777, true);
}

// ─── Endpoints de solo lectura, seguros de capturar en cualquier momento ───
// [nombre_archivo, ruta, rol_requerido]  rol: 'admin' | 'member' | 'any'
$SAFE_ENDPOINTS = [
    ['auth_login',                 '/api/auth/login.php',                       'auth'],   // caso especial, ver capturarLogin()
    ['admin_getAdminPredicciones', '/api/admin/getAdminPredicciones.php?page=1&limit=10', 'admin'],
    ['admin_getCompeticiones',     '/api/admin/getCompeticiones.php',            'any'],
    ['admin_getCuotasCampeon',     '/api/admin/getCuotasCampeon.php',            'admin'],
    ['admin_getOptionsAdminPredicciones', '/api/admin/getOptionsAdminPredicciones.php', 'admin'],
    ['admin_getUsersPermissions',  '/api/admin/getUsersPermissions.php',         'admin'],
    ['admin_managePenalizaciones_list', '/api/admin/managePenalizaciones.php',   'admin'],
    ['admin_manageTables_list',    '/api/admin/manageTables.php',                'admin'],
    ['dashboards_getAllBets',      '/api/dashboards/getAllBets.php',             'admin'],
    ['dashboards_getDashboards',   '/api/dashboards/getDashboards.php',          'any'],
    ['dashboards_getMemberCharts', '/api/dashboards/getMemberCharts.php',        'any'],
    ['dashboards_getMiembroDashboard', '/api/dashboards/getMiembroDashboard.php', 'any'],
    ['dashboards_getRanking',      '/api/dashboards/getRanking.php',             'any'],
    ['dashboards_getTesoreroDashboard', '/api/dashboards/getTesoreroDashboard.php', 'admin'],
    ['partidos_getPartidos',       '/api/partidos/getPartidos.php',              'any'],
    ['tesoreria_getPendingBets',   '/api/tesoreria/getPendingBets.php',          'admin'],
];

// ─── Excluidos deliberadamente — NO tocar sin decisión explícita del usuario ───
// Motivo documentado junto a cada uno. Para capturar su contrato, la vía
// segura es leer el código del Controller/Service (no ejecutarlos contra
// producción) o pedir al usuario que los ejecute él mismo con datos de
// prueba que sepa que no afectan standings/dinero reales.
const MUTATING_ENDPOINTS_EXCLUIDOS = [
    'admin/convertToMember.php'        => 'Cambia IdRol y crea/borra Membresias reales (dinero)',
    'admin/createAdminPrediccion.php'  => 'Escribe una Prediccion real y recalcula puntos de un usuario',
    'admin/deleteUser.php'             => 'DESTRUCTIVO: borra un usuario real',
    'admin/registerWorldCupApi.php'    => 'Llama a API externa desactivada; será eliminado (Fase 4.3)',
    'admin/removeCuotaCampeon.php'     => 'Borra una cuota fija real de CuotasCampeonFijas',
    'admin/saveCuotaCampeon.php'       => 'Escribe una cuota fija real (afecta apuestas nuevas)',
    'admin/updateUserAndMembership.php'=> 'Cambia rol y registra membresia real (dinero)',
    'admin/updateUserPermission.php'   => 'Escribe permisos reales en TablaMiembros',
    'apuestas/placeBetChampion.php'    => 'Crea una apuesta real con dinero',
    'apuestas/placeBetMatch.php'       => 'Crea una apuesta real con dinero',
    'auth/register.php'                => 'Crea una cuenta de usuario real',
    'partidos/getPartidosDirecto.php'  => 'ROTO (llama a worldcupApiRequest() inexistente); será eliminado (Fase 4.3)',
    'partidos/savePrediccion.php'      => 'Sobrescribe la prediccion real del usuario autenticado',
    'partidos/syncOdds.php'            => 'Consume cuota de The Odds API (limitada) y escribe cuotas reales',
    'partidos/syncPartidos.php'        => 'Llama a ESPN y recalcula puntos de TODOS los usuarios',
    'tesoreria/approveBet.php'         => 'Aprueba una apuesta real pendiente (dinero, semi-irreversible)',
    'tesoreria/denyBet.php'            => 'Deniega una apuesta real pendiente (dinero)',
    'admin/checkFases.php'             => 'Script de diagnóstico sin auth; será eliminado (Fase 4.3), no se migra',
];

function httpRequest(string $url, string $cookieJar, array $post = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['body' => $body, 'code' => $code, 'error' => $err];
}

function login(string $baseUrl, string $cookieJar, string $usuario, string $contrasena): array
{
    return httpRequest($baseUrl . '/api/auth/login.php', $cookieJar, [
        'usuario' => $usuario,
        'contrasena' => $contrasena,
    ]);
}

function capturarSesion(string $baseUrl, string $goldenDir, string $rolLabel, string $usuario, string $contrasena, array $endpoints, ?string $captureLoginName): array
{
    $cookieJar = tempnam(sys_get_temp_dir(), 'quiniela_golden_');
    $resultados = [];

    $loginRes = login($baseUrl, $cookieJar, $usuario, $contrasena);
    if ($captureLoginName) {
        file_put_contents("$goldenDir/{$captureLoginName}.json", formatearJson($loginRes['body']));
    }

    $loginData = json_decode(stripBom($loginRes['body']), true);
    if (!is_array($loginData) || empty($loginData['success'])) {
        fwrite(STDERR, "[$rolLabel] LOGIN FALLÓ para usuario '$usuario': " . ($loginRes['body'] ?: $loginRes['error']) . "\n");
        @unlink($cookieJar);
        return $resultados;
    }
    echo "[$rolLabel] Login OK como '$usuario'.\n";

    foreach ($endpoints as [$nombre, $ruta, $rolRequerido]) {
        if ($rolRequerido === 'auth') continue; // login ya capturado aparte
        if ($rolRequerido === 'admin' && $rolLabel !== 'admin') continue;

        $res = httpRequest($baseUrl . $ruta, $cookieJar, null);
        $sufijo = $rolLabel === 'admin' ? '' : "_{$rolLabel}";
        $archivo = "$goldenDir/{$nombre}{$sufijo}.json";
        file_put_contents($archivo, formatearJson($res['body']));

        $status = $res['code'] === 200 ? 'OK' : "HTTP {$res['code']}";
        echo "  [$rolLabel] $nombre -> $status (" . strlen((string)$res['body']) . " bytes)\n";
        $resultados[] = $nombre;
    }

    httpRequest($baseUrl . '/api/auth/logout.php', $cookieJar, null);
    @unlink($cookieJar);

    return $resultados;
}

/**
 * Quita un BOM UTF-8 inicial si lo hay. Un endpoint cuyo archivo (o algo
 * en su cadena de require) esté guardado con BOM manda esos 3 bytes antes
 * del JSON; el navegador lo tolera pero json_decode() de PHP no.
 */
function stripBom(?string $raw): string
{
    if ($raw === null) return '';
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        return substr($raw, 3);
    }
    return $raw;
}

function formatearJson(?string $raw): string
{
    if ($raw === null || $raw === '') return $raw ?? '';
    $limpio = stripBom($raw);
    $decoded = json_decode($limpio, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // No es JSON (p.ej. error fatal de PHP) — guardar tal cual para diagnóstico
        return $raw;
    }
    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

echo "═══════════════════════════════════════════════════════════\n";
echo " Captura de contratos (golden files) — Fase 0.3\n";
echo " Base URL: $baseUrl\n";
echo " Endpoints de solo lectura incluidos: " . count($SAFE_ENDPOINTS) . "\n";
echo " Endpoints mutantes EXCLUIDOS a propósito: " . count(MUTATING_ENDPOINTS_EXCLUIDOS) . "\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$capturados = capturarSesion($baseUrl, $goldenDir, 'admin', $adminUser, $adminPass, $SAFE_ENDPOINTS, 'auth_login_admin');

if ($memberUser && $memberPass) {
    echo "\n";
    capturarSesion($baseUrl, $goldenDir, 'member', $memberUser, $memberPass, $SAFE_ENDPOINTS, 'auth_login_member');
} else {
    echo "\n[member] Omitido: no se pasaron credenciales de un usuario no-admin.\n";
    echo "  Recomendado: vuelve a ejecutar con un usuario Miembro para capturar\n";
    echo "  getMiembroDashboard/getMemberCharts/getDashboards desde esa perspectiva.\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo " Endpoints NO capturados (requieren decisión manual, ver comentario\n";
echo " en el código de este script o PLAN_MIGRACION.md §4):\n";
foreach (MUTATING_ENDPOINTS_EXCLUIDOS as $ep => $motivo) {
    echo "   - api/$ep\n     -> $motivo\n";
}
echo "═══════════════════════════════════════════════════════════\n";
echo "Listo. Golden files en: $goldenDir\n";
