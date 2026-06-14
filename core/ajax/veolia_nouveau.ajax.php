<?php
// core/ajax/veolia_nouveau.ajax.php
// AJAX endpoint used by the equipment UI to test login and import.

try {
    require_once dirname(__FILE__) . '/../class/VeoliaNouveauAuth.php';
    require_once dirname(__FILE__) . '/../class/VeoliaNouveauFetcher.php';
    require_once dirname(__FILE__) . '/../class/VeoliaNouveauParser.php';
} catch (Exception $e) {
    // In plugin context these files should exist
}

function send_json($arr) {
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
}

$action = $_POST['action'] ?? null;
if ($action === 'test') {
    $raw = $_POST['data'] ?? '';
    $cfg = json_decode($raw, true);
    if (!is_array($cfg)) {
        send_json(['state' => 'error', 'message' => 'Paramètres invalides']);
    }
    $username = $cfg['username'] ?? null;
    $password = $cfg['password'] ?? null;
    $clientId = $cfg['client_id'] ?? null;
    $contractId = $cfg['contract_id'] ?? null;
    $numeroPds = $cfg['numero_pds'] ?? null;
    $dateDebut = $cfg['date_debut'] ?? null;
    $baseHost = $cfg['base_host'] ?? 'https://prd-ael-sirius-backend.istefr.fr';
    $annee = intval($cfg['annee'] ?? date('Y'));

    if (empty($username) || empty($password) || empty($clientId) || empty($contractId) || empty($numeroPds) || empty($dateDebut)) {
        send_json(['state' => 'error', 'message' => 'Tous les champs (username/password/client_id/contract_id/numero_pds/date_debut) sont requis']);
    }

    $auth = new VeoliaNouveauAuth();
    $res = $auth->initiateAuth($username, $password, $clientId);
    if ($res === false) {
        send_json(['state' => 'error', 'message' => 'Authentification échouée ou challenge requis']);
    }
    if (!isset($res['AccessToken'])) {
        // Could be challenge response
        send_json(['state' => 'error', 'message' => 'Authentification non standard (challenge requis)', 'raw' => $res]);
    }
    $accessToken = $res['AccessToken'];
    $fetcher = new VeoliaNouveauFetcher($baseHost);
    $url = $fetcher->buildExportUrl($contractId, $annee, $numeroPds, $dateDebut);
    $csv = $fetcher->fetchCsvWithToken($url, $accessToken);
    if ($csv === false) {
        send_json(['state' => 'error', 'message' => 'Échec du téléchargement CSV (vérifier token / droits)']);
    }
    $rows = VeoliaNouveauParser::parseCsv($csv);
    // Do not persist tokens here — UI just tests. The plugin equipment save action should store them.
    send_json(['state' => 'ok', 'message' => 'CSV récupéré', 'rows' => $rows]);
}

send_json(['state' => 'error', 'message' => 'Action invalide']);
