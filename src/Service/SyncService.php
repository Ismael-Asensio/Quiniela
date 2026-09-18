<?php

// ═══════════════════════════════════════════════════════════════
// SyncService — Sincronización de Partidos con API de World Cup
// ═══════════════════════════════════════════════════════════════

class SyncService
{
    private $partidoRepo;
    private $equipoRepo;
    private $prediccionRepo;
    private $puntosService;
    private $partidoService;

    // ─── Mapeo de nombres de equipos: Inglés → Español ───
    private const NOMBRES_ES = [
        // Grupo A
        'Morocco' => 'Marruecos',
        'Peru' => 'Perú',
        'Scotland' => 'Escocia',
        'Canada' => 'Canadá',
        // Grupo B
        'Mexico' => 'México',
        'Colombia' => 'Colombia',
        'Ecuador' => 'Ecuador',
        'Senegal' => 'Senegal',
        // Grupo C
        'Spain' => 'España',
        'New Zealand' => 'Nueva Zelanda',
        'Egypt' => 'Egipto',
        'Belgium' => 'Bélgica',
        // Grupo D
        'Argentina' => 'Argentina',
        'Uzbekistan' => 'Uzbekistán',
        'Nigeria' => 'Nigeria',
        'Tunisia' => 'Túnez',
        // Grupo E
        'Australia' => 'Australia',
        'Denmark' => 'Dinamarca',
        'Indonesia' => 'Indonesia',
        'France' => 'Francia',
        // Grupo F
        'Brazil' => 'Brasil',
        'Cameroon' => 'Camerún',
        'Serbia' => 'Serbia',
        'Portugal' => 'Portugal',
        // Grupo G
        'Iran' => 'Irán',
        'Wales' => 'Gales',
        'United States' => 'Estados Unidos',
        'England' => 'Inglaterra',
        // Grupo H
        'Japan' => 'Japón',
        'Ivory Coast' => 'Costa de Marfil',
        'Paraguay' => 'Paraguay',
        'Germany' => 'Alemania',
        // Grupo I
        'Uruguay' => 'Uruguay',
        'Panama' => 'Panamá',
        'South Korea' => 'Corea del Sur',
        'Italy' => 'Italia',
        // Grupo J
        'Netherlands' => 'Países Bajos',
        'Saudi Arabia' => 'Arabia Saudita',
        'Costa Rica' => 'Costa Rica',
        'Croatia' => 'Croacia',
        // Grupo K
        'Switzerland' => 'Suiza',
        'Slovenia' => 'Eslovenia',
        'Albania' => 'Albania',
        'Turkey' => 'Turquía',
        // Grupo L
        'Bolivia' => 'Bolivia',
        'Ghana' => 'Ghana',
        'Chile' => 'Chile',
        'Honduras' => 'Honduras',
        "Cote d'Ivoire" => 'Costa de Marfil',
        'Korea Republic' => 'Corea del Sur',
        'Türkiye' => 'Turquía',

        // ─── Otros Países Comunes (Repesca / Clasificatorias) ───
        'Sweden' => 'Suecia',
        'Poland' => 'Polonia',
        'Ukraine' => 'Ucrania',
        'Austria' => 'Austria',
        'Czech Republic' => 'República Checa',
        'Czechia' => 'República Checa',
        'Hungary' => 'Hungría',
        'Romania' => 'Rumanía',
        'Greece' => 'Grecia',
        'Iceland' => 'Islandia',
        'Norway' => 'Noruega',
        'Finland' => 'Finlandia',
        'Ireland' => 'Irlanda',
        'Northern Ireland' => 'Irlanda del Norte',
        'Slovakia' => 'Eslovaquia',
        'Bosnia and Herzegovina' => 'Bosnia y Herzegovina',
        'Bosnia-Herzegovina' => 'Bosnia y Herzegovina',
        'Rusia' => 'Rusia',
        'Russia' => 'Rusia',
        'Qatar' => 'Catar',
        'United Arab Emirates' => 'Emiratos Árabes Unidos',
        'Iraq' => 'Irak',
        'Syria' => 'Siria',
        'Oman' => 'Omán',
        'China' => 'China',
        'China PR' => 'China',
        'Vietnam' => 'Vietnam',
        'Algeria' => 'Argelia',
        'Mali' => 'Malí',
        'Burkina Faso' => 'Burkina Faso',
        'DR Congo' => 'RD Congo',
        'Congo DR' => 'RD Congo',
        'Democratic Republic of the Congo' => 'RD Congo',
        'Cape Verde' => 'Cabo Verde',
        'Curaçao' => 'Curazao',
        'Jordan' => 'Jordania',
        'South Africa' => 'Sudáfrica',
        'Zambia' => 'Zambia',
        'Guinea' => 'Guinea',
        'Venezuela' => 'Venezuela',
        'Jamaica' => 'Jamaica',
        'El Salvador' => 'El Salvador',
        'Haiti' => 'Haití',
        'Trinidad and Tobago' => 'Trinidad y Tobago',
        'Guatemala' => 'Guatemala',
        'North Korea' => 'Corea del Norte',
        'DPR Korea' => 'Corea del Norte',
        'Macedonia' => 'Macedonia del Norte',
        'North Macedonia' => 'Macedonia del Norte',
        'Bulgaria' => 'Bulgaria',
        'Armenia' => 'Armenia',
        'Georgia' => 'Georgia',
    ];

    public function __construct()
    {
        $this->partidoRepo = new PartidoRepository();
        $this->equipoRepo = new EquipoRepository();
        $this->prediccionRepo = new PrediccionRepository();
        $this->puntosService = new PuntosService();
        $this->partidoService = new PartidoService();
    }

    public function sincronizarPartidos(?string $fechaManual = null): array
    {
        $fechas = $fechaManual !== null ? [$fechaManual] : $this->getFechasASincronizar();
        error_log("[SyncService] Descargando partidos desde ESPN para fechas: " . implode(', ', $fechas));

        $allEvents = [];
        $lastError = null;

        foreach ($fechas as $fecha) {
            $gamesRes = $this->fetchEspnApi($fecha);
            if (!$gamesRes['success']) {
                error_log("[SyncService] ERROR fecha $fecha: " . $gamesRes['error']);
                $lastError = $gamesRes['error'];
                continue;
            }

            if (isset($gamesRes['data']['events']) && is_array($gamesRes['data']['events'])) {
                foreach ($gamesRes['data']['events'] as $event) {
                    if (isset($event['id'])) {
                        $allEvents[$event['id']] = $event;
                    }
                }
            }
        }

        if (empty($allEvents) && $lastError !== null) {
            return ['success' => false, 'error' => "Error descargando partidos: " . $lastError, 'code' => 502];
        }

        $events = array_values($allEvents);
        error_log("[SyncService] Partidos recibidos en total: " . count($events));

        $nuevos = 0;
        $actualizados = 0;
        $evaluados = 0;

        foreach ($events as $event) {
            $apiId = (int) $event['id'];
            $comp = $event['competitions'][0] ?? null;
            if (!$comp)
                continue;

            $utcDate = new \DateTime($event['date'], new \DateTimeZone('UTC'));
            $utcDate->setTimezone(new \DateTimeZone(Config::TIMEZONE));
            $fechaCST = $utcDate->format('Ymd H:i:s');

            $slug = $event['season']['slug'] ?? 'group-stage';
            $slugParts = explode('-', $slug);
            $faseData = Config::mapFase($slugParts[0] ?? 'group');

            $stateDetail = $comp['status']['type']['detail'] ?? '';
            $stateState = $comp['status']['type']['state'] ?? 'pre';

            if ($stateState === 'pre')
                $estadoLocal = 'NS';
            elseif ($stateState === 'post') {
                $estadoLocal = in_array($stateDetail, ['AET', 'PEN']) ? $stateDetail : 'FT';
            } else {
                if (in_array($stateDetail, ['HT', 'Halftime']))
                    $estadoLocal = 'HT';
                else
                    $estadoLocal = '1H';
            }

            $homeTeam = null;
            $awayTeam = null;
            $homeScore = null;
            $awayScore = null;

            foreach ($comp['competitors'] as $competitor) {
                if ($competitor['homeAway'] === 'home') {
                    $homeTeam = $competitor['team'];
                    $homeScore = $competitor['score'] ?? null;
                } else {
                    $awayTeam = $competitor['team'];
                    $awayScore = $competitor['score'] ?? null;
                }
            }
            if (!$homeTeam || !$awayTeam)
                continue;

            $nombreEnLocal = $homeTeam['displayName'] ?? $homeTeam['name'] ?? '';
            $nombreEnVisit = $awayTeam['displayName'] ?? $awayTeam['name'] ?? '';

            if (strpos($nombreEnLocal, 'Winner') !== false || strpos($nombreEnLocal, 'Place') !== false)
                continue;
            if (strpos($nombreEnVisit, 'Winner') !== false || strpos($nombreEnVisit, 'Place') !== false)
                continue;

            $nombreLocalEsp = $this->traducirNombreEquipo($nombreEnLocal);
            $nombreVisitEsp = $this->traducirNombreEquipo($nombreEnVisit);

            $rowLocal = $this->equipoRepo->findByNombre($nombreLocalEsp);
            $rowVisit = $this->equipoRepo->findByNombre($nombreVisitEsp);

            if (!$rowLocal) {
                $logoLocal = $homeTeam['logo'] ?? null;
                $apiIdLocal = isset($homeTeam['id']) ? (int)$homeTeam['id'] : null;
                $isoLocal = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nombreLocalEsp), 0, 3));
                $this->equipoRepo->create($nombreLocalEsp, $isoLocal, $logoLocal, $apiIdLocal);
                $rowLocal = $this->equipoRepo->findByNombre($nombreLocalEsp);
            }

            if (!$rowVisit) {
                $logoVisit = $awayTeam['logo'] ?? null;
                $apiIdVisit = isset($awayTeam['id']) ? (int)$awayTeam['id'] : null;
                $isoVisit = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nombreVisitEsp), 0, 3));
                $this->equipoRepo->create($nombreVisitEsp, $isoVisit, $logoVisit, $apiIdVisit);
                $rowVisit = $this->equipoRepo->findByNombre($nombreVisitEsp);
            }

            if (!$rowLocal || !$rowVisit) {
                error_log("[SyncService] Error crítico creando equipos en DB para: $nombreLocalEsp o $nombreVisitEsp");
                continue;
            }

            $idLocal = (int) $rowLocal['Id'];
            $idVisitante = (int) $rowVisit['Id'];

            $golesLocal = ($estadoLocal !== 'NS' && $homeScore !== null) ? (int) $homeScore : null;
            $golesVisitante = ($estadoLocal !== 'NS' && $awayScore !== null) ? (int) $awayScore : null;

            $existente = $this->partidoRepo->findByApiId($apiId);
            if (!$existente) {
                $existente = $this->partidoRepo->findByLocalVisitante($idLocal, $idVisitante);
                
                // Si no se encontró por equipos, intentar por fecha (para fases eliminatorias donde no hay equipos definidos)
                if (!$existente && $faseData['fase'] !== 'Grupos') {
                    $existente = $this->partidoRepo->findByFechaHoraCST($fechaCST);
                }

                if ($existente) {
                    $this->partidoRepo->updateTeamsAndApi($existente['Id'], $idLocal, $idVisitante, $apiId);
                    
                    $existente['IdLocal'] = $idLocal;
                    $existente['IdVisitante'] = $idVisitante;
                    $existente['IdApiExterno'] = $apiId;
                }
            }

            if ($existente) {
                $eraFinalizado = in_array($existente['Estado'], Config::ESTADOS_FINALIZADOS);
                $esFinalizado = in_array($estadoLocal, Config::ESTADOS_FINALIZADOS);
                $esEnVivo = in_array($estadoLocal, Config::ESTADOS_EN_VIVO);

                // Detectar si el marcador, estado o FECHA han cambiado
                $fechaDbFormateada = $existente['FechaHoraCST'] instanceof \DateTime
                    ? $existente['FechaHoraCST']->format('Ymd H:i:s')
                    : (new \DateTime($existente['FechaHoraCST']))->format('Ymd H:i:s');

                $scoreChanged = ($existente['GolesLocal'] != $golesLocal)
                    || ($existente['GolesVisitante'] != $golesVisitante)
                    || ($existente['Estado'] != $estadoLocal)
                    || ($fechaDbFormateada != $fechaCST);

                if ($scoreChanged) {
                    $this->partidoRepo->updateMatchData($existente['Id'], $fechaCST, $golesLocal, $golesVisitante, $estadoLocal);
                    $actualizados++;
                }

                // Recalcular puntos SOLAMENTE si el partido está finalizado.
                if ($esFinalizado) {
                    if (!$eraFinalizado || $scoreChanged) {
                        $this->prediccionRepo->updatePointsByMatch($existente['Id'], [Config::class, 'calcularPuntos']);
                        $evaluados++;
                    }
                } elseif ($eraFinalizado) {
                    // Si el partido estaba finalizado pero la API lo revirtió (muy raro, corrección de VAR, etc)
                    $this->prediccionRepo->clearPointsByMatch($existente['Id']);
                    $evaluados++;
                }
            } else {
                // Solo agregar si es de la fase de grupos o si no existe aún (para eliminatorias)
                if ($faseData['fase'] === 'Grupos' || !$existente) {
                    $this->partidoRepo->create([
                        'IdApiExterno' => $apiId,
                        'IdLocal' => $idLocal,
                        'IdVisitante' => $idVisitante,
                        'FechaHoraCST' => $fechaCST,
                        'GolesLocal' => $golesLocal,
                        'GolesVisitante' => $golesVisitante,
                        'Estado' => $estadoLocal,
                        'Fase' => $faseData['fase'],
                        'MultiplicadorFase' => $faseData['multiplicador'],
                        'Jornada' => null,
                        'Sede' => null,
                    ]);
                    $nuevos++;
                }
            }
        }

        if ($evaluados > 0) {
            $this->puntosService->recalcularPuntosTodos();

            $excluidos = $this->partidoService->autoExcluirEliminados();
            if (!empty($excluidos)) {
                error_log("[SyncService] Auto-excluidos de Cuotas Campeón (eliminados): " . implode(', ', $excluidos));
            }
        }

        return [
            'success' => true,
            'message' => 'Sincronización completa vía ESPN',
            'stats' => ['procesados' => count($events), 'nuevos' => $nuevos, 'actualizados' => $actualizados, 'evaluados' => $evaluados]
        ];
    }

    private function getFechasASincronizar(): array
    {
        $now = Config::now();
        $fechas = [
            (clone $now)->modify('-1 day')->format('Ymd'),
            $now->format('Ymd'),
        ];

        try {
            $competicion = Config::competicionActiva();
            $rows = Database::fetchAll(
                "SELECT DISTINCT CONVERT(VARCHAR(8), FechaHoraCST, 112) AS Fecha
                 FROM Partidos
                 WHERE IdCompeticion = ?
                   AND Estado NOT IN ('FT', 'AET', 'PEN', 'CANC', 'ABD')
                   AND FechaHoraCST <= DATEADD(day, 1, CONVERT(DATETIME, ?, 120))
                   AND FechaHoraCST >= DATEADD(day, -7, CONVERT(DATETIME, ?, 120))",
                [$competicion, $now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]
            );
            foreach ($rows as $r) {
                if (!empty($r['Fecha']) && !in_array($r['Fecha'], $fechas, true)) {
                    $fechas[] = $r['Fecha'];
                }
            }
        } catch (\Throwable $e) {
            error_log("[SyncService] Error obteniendo fechas pendientes: " . $e->getMessage());
        }

        return array_values(array_unique($fechas));
    }

    private function fetchEspnApi(?string $date = null): array
    {
        $ch = curl_init();
        $url = Config::ESPN_API_SCOREBOARD;
        if ($date !== null) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . 'dates=' . urlencode($date);
        }
        if (strpos($url, '?') !== false) {
            $url .= '&_=' . time();
        } else {
            $url .= '?_=' . time();
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9,es;q=0.8',
            'Cache-Control: max-age=0',
            'Connection: keep-alive',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1'
        ]);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            $detalle = $curlError;
            if (!$detalle && is_string($response)) {
                $detalle = preg_replace('/\s+/', ' ', substr(trim(strip_tags($response)), 0, 200));
            }
            return ['success' => false, 'error' => "ESPN API Error: $httpCode - $detalle"];
        }

        return ['success' => true, 'data' => json_decode($response, true)];
    }

    /**
     * Traduce el nombre de un equipo de inglés a español.
     * Si no existe traducción, devuelve el nombre original.
     * Método público estático para uso desde otros servicios (ej. CuotasService).
     */
    public static function traducirNombrePublico(string $nombreEn): string
    {
        return self::NOMBRES_ES[$nombreEn] ?? $nombreEn;
    }

    private function traducirNombreEquipo(string $nombreEn): string
    {
        return self::traducirNombrePublico($nombreEn);
    }
}
