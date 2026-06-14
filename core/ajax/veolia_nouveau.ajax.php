<?php
// Updated AJAX endpoint to support save and challenge response.

try {
    require_once dirname(__FILE__) . '/../class/VeoliaNouveauAuth.php';
    require_once dirname(__FILE__) . '/../class/VeoliaNouveauFetcher.php';
    require_once dirname(__FILE__) . '/../class/VeoliaNouveauParser.php';
} catch (Exception $e) {
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
    if (!is_array($cfg)) send_json(['state'=>'error','message'=>'Paramètres invalides']);

    $username = $cfg['username'] ?? null;
    $password = $cfg['password'] ?? null;
    $clientId = $cfg['client_id'] ?? null;
    $contractId = $cfg['contract_id'] ?? null;
    $numeroPds = $cfg['numero_pds'] ?? null;
    $dateDebut = $cfg['date_debut'] ?? null;
    $baseHost = $cfg['base_host'] ?? 'https://prd-ael-sirius-backend.istefr.fr';
    $annee = intval($cfg['annee'] ?? date('Y'));
    $save = isset($cfg['save']) && $cfg['save'];

    if (empty($username) || empty($password) || empty($clientId) || empty($contractId) || empty($numeroPds) || empty($dateDebut)) {
        send_json(['state' => 'error', 'message' => 'Tous les champs (username/password/client_id/contract_id/numero_pds/date_debut) sont requis']);
    }

    $auth = new VeoliaNouveauAuth();
    $resp = $auth->initiateAuth($username, $password, $clientId);
    if ($resp === false) send_json(['state'=>'error','message'=>'Authentification échouée']);

    // If AuthenticationResult present => we have tokens
    if (isset($resp['AuthenticationResult'])) {
        $authRes = $resp['AuthenticationResult'];
        $accessToken = $authRes['AccessToken'] ?? null;
        $refreshToken = $authRes['RefreshToken'] ?? null;
        $expiresIn = intval($authRes['ExpiresIn'] ?? 0);

        $fetcher = new VeoliaNouveauFetcher($baseHost);
        $url = $fetcher->buildExportUrl($contractId, $annee, $numeroPds, $dateDebut);
        $csv = $fetcher->fetchCsvWithToken($url, $accessToken);
        if ($csv === false) send_json(['state'=>'error','message'=>'Échec du téléchargement CSV (vérifier droits)']);

        $rows = VeoliaNouveauParser::parseCsv($csv);

        // If requested, save tokens into eq configuration (requires eqId in cfg)
        if ($save && !empty($cfg['eq_id'])) {
            $eqId = intval($cfg['eq_id']);
            if (class_exists('eqLogic') && method_exists('eqLogic','byId')) {
                $eq = eqLogic::byId($eqId);
                if (is_object($eq)) {
                    $eq->setConfiguration('veolia_token', $accessToken);
                    if (!empty($refreshToken)) $eq->setConfiguration('veolia_refresh_token', $refreshToken);
                    if ($expiresIn>0) $eq->setConfiguration('veolia_token_expiry', time()+$expiresIn);
                    $eq->save();
                }
            }
        }

        send_json(['state'=>'ok','message'=>'Authentification OK et CSV récupéré','rows'=>$rows,'tokens'=>['access'=>substr($accessToken,0,10).'...']]);
    }

    // If challenge returned
    if (isset($resp['ChallengeName'])) {
        // store Session and ChallengeName in response so UI can prompt
        send_json(['state'=>'challenge','message'=>'Challenge requis','challenge'=>['name'=>$resp['ChallengeName'],'session'=>$resp['Session'] ?? null]]);
    }

    send_json(['state'=>'error','message'=>'Authentification non standard']);
}

if ($action === 'respond_challenge') {
    $clientId = $_POST['client_id'] ?? null;
    $challengeName = $_POST['challenge_name'] ?? null;
    $session = $_POST['session'] ?? null;
    $username = $_POST['username'] ?? null;
    $response = $_POST['response'] ?? null; // code from user
    $contractId = $_POST['contract_id'] ?? null;
    $numeroPds = $_POST['numero_pds'] ?? null;
    $dateDebut = $_POST['date_debut'] ?? null;
    $baseHost = $_POST['base_host'] ?? 'https://prd-ael-sirius-backend.istefr.fr';
    $annee = intval($_POST['annee'] ?? date('Y'));
    $save = isset($_POST['save']) && $_POST['save'];
    $eqId = intval($_POST['eq_id'] ?? 0);

    if (empty($clientId) || empty($challengeName) || empty($response)) send_json(['state'=>'error','message'=>'Paramètres manquants pour challenge']);

    $auth = new VeoliaNouveauAuth();
    // Build challengeResponses key depending on challenge
    $challengeResponses = [];
    // Common keys: SMS_MFA -> SMS_MFA_CODE, SOFTWARE_TOKEN_MFA -> SOFTWARE_TOKEN_MFA_CODE
    if ($challengeName === 'SMS_MFA' || stripos($challengeName,'SMS')!==false) {
        $challengeResponses['SMS_MFA_CODE'] = $response;
    } else if ($challengeName === 'SOFTWARE_TOKEN_MFA' || stripos($challengeName,'SOFTWARE')!==false) {
        $challengeResponses['SOFTWARE_TOKEN_MFA_CODE'] = $response;
    } else if ($challengeName === 'NEW_PASSWORD_REQUIRED') {
        // response should be new password, but require additional attributes
        $challengeResponses['NEW_PASSWORD'] = $response;
    } else {
        // generic
        $challengeResponses[$challengeName] = $response;
    }
    if (!empty($username)) $challengeResponses['USERNAME'] = $username;

    $resp = $auth->respondToAuthChallenge($clientId, $challengeName, $challengeResponses, $session);
    if ($resp === false) send_json(['state'=>'error','message'=>'Echec réponse challenge']);

    if (isset($resp['AuthenticationResult'])) {
        $authRes = $resp['AuthenticationResult'];
        $accessToken = $authRes['AccessToken'] ?? null;
        $refreshToken = $authRes['RefreshToken'] ?? null;
        $expiresIn = intval($authRes['ExpiresIn'] ?? 0);

        $fetcher = new VeoliaNouveauFetcher($baseHost);
        $url = $fetcher->buildExportUrl($contractId, $annee, $numeroPds, $dateDebut);
        $csv = $fetcher->fetchCsvWithToken($url, $accessToken);
        if ($csv === false) send_json(['state'=>'error','message'=>'Échec téléchargement CSV après challenge']);

        $rows = VeoliaNouveauParser::parseCsv($csv);

        if ($save && $eqId>0 && class_exists('eqLogic') && method_exists('eqLogic','byId')) {
            $eq = eqLogic::byId($eqId);
            if (is_object($eq)) {
                $eq->setConfiguration('veolia_token', $accessToken);
                if (!empty($refreshToken)) $eq->setConfiguration('veolia_refresh_token', $refreshToken);
                if ($expiresIn>0) $eq->setConfiguration('veolia_token_expiry', time()+$expiresIn);
                $eq->save();
            }
        }

        send_json(['state'=>'ok','message'=>'Authentification OK, CSV récupéré','rows'=>$rows]);
    }

    send_json(['state'=>'error','message'=>'Réponse challenge non concluante','raw'=>$resp]);
}

send_json(['state' => 'error', 'message' => 'Action invalide']);
