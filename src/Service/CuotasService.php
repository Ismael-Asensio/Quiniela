<?php

// ═══════════════════════════════════════════════════════════════
// CuotasService — Sincronización de cuotas con The Odds API
// ═══════════════════════════════════════════════════════════════

class CuotasService
{
    /**
     * Clave de deporte de The Odds API por competición. Si la
     * competición activa no aparece aquí (o su valor es null),
     * sincronizarCuotas() no llama a la API externa: antes, la Copa
     * Centroamericana pedía siempre "soccer_fifa_world_cup" y nunca
     * había coincidencias, gastando cuota de la API en llamadas
     * inútiles (Fase 6.2 del plan de migración).
     */
    private const SPORT_KEYS = [
        1 => 'soccer_fifa_world_cup', // Mundial 2026
        2 => null,                    // Copa Centroamericana -- sin cobertura en The Odds API
    ];

    private $partidoRepo;
    private $equipoRepo;

    public function __construct()
    {
        $this->partidoRepo = new PartidoRepository();
        $this->equipoRepo = new EquipoRepository();
    }

    public function sincronizarCuotas(): array
    {
        $competicionId = Config::competicionActiva();
        $sportKey = self::SPORT_KEYS[$competicionId] ?? null;

        if ($sportKey === null) {
            return [
                'success' => false,
                'error' => 'The Odds API no tiene cobertura para la competición activa. Sincronización de cuotas desactivada.',
                'code' => 409,
            ];
        }

        $apiUrl = Config::oddsApiBase() . "/sports/{$sportKey}/odds/?apiKey=" . Config::oddsApiKey() . "&regions=eu&markets=h2h";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return ['success' => false, 'error' => "Error de Odds API: Code $httpCode", 'code' => 502];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['success' => false, 'error' => "Respuesta JSON inválida de Odds API", 'code' => 502];
        }

        $actualizados = 0;
        $noEncontrados = 0;

        foreach ($data as $event) {
            // Extraer nombres de la API externa (en inglés)
            $homeTeamApi = $event['home_team'];
            $awayTeamApi = $event['away_team'];

            // Traducir nombres al español para buscar en nuestra BD
            $homeTeamLocal = SyncService::traducirNombrePublico($homeTeamApi);
            $awayTeamLocal = SyncService::traducirNombrePublico($awayTeamApi);

            // Buscar en nuestra BD por nombre en español
            $sql = "SELECT p.Id 
                    FROM Partidos p
                    INNER JOIN Equipos eL ON p.IdLocal = eL.Id
                    INNER JOIN Equipos eV ON p.IdVisitante = eV.Id
                    WHERE eL.Nombre = ? AND eV.Nombre = ? AND p.Estado IN ('NS', '1H', 'HT')";
            
            $partidoRow = Database::fetchOne($sql, [$homeTeamLocal, $awayTeamLocal]);

            if (!$partidoRow) {
                $noEncontrados++;
                continue;
            }

            $idPartidoLocal = (int)$partidoRow['Id'];

            // Extraer cuotas del primer bookmaker disponible (ej. Pinnacle o similar)
            if (empty($event['bookmakers'])) continue;
            
            $bookmaker = $event['bookmakers'][0];
            $markets = $bookmaker['markets'] ?? [];
            
            $cuotaLocal = 0.0;
            $cuotaVisitante = 0.0;
            $cuotaEmpate = 0.0;

            foreach ($markets as $market) {
                if ($market['key'] === 'h2h') {
                    foreach ($market['outcomes'] as $outcome) {
                        if ($outcome['name'] === $homeTeamApi) {
                            $cuotaLocal = (float)$outcome['price'];
                        } elseif ($outcome['name'] === $awayTeamApi) {
                            $cuotaVisitante = (float)$outcome['price'];
                        } elseif (strtolower($outcome['name']) === 'draw') {
                            $cuotaEmpate = (float)$outcome['price'];
                        }
                    }
                    break;
                }
            }

            if ($cuotaLocal > 0 && $cuotaVisitante > 0 && $cuotaEmpate > 0) {
                $this->partidoRepo->updateOdds($idPartidoLocal, $cuotaLocal, $cuotaEmpate, $cuotaVisitante);
                $actualizados++;
            }
        }

        return [
            'success' => true, 
            'message' => 'Sincronización de cuotas completa', 
            'stats' => ['actualizados' => $actualizados, 'no_encontrados' => $noEncontrados]
        ];
    }
}
