<?php
// core/class/test_veolia_nouveau.php
// Simple CLI test harness for the new Veolia auth + fetch + parse flow.

require_once __DIR__ . '/VeoliaNouveauAuth.php';
require_once __DIR__ . '/VeoliaNouveauFetcher.php';
require_once __DIR__ . '/VeoliaNouveauParser.php';

// Read configuration from environment variables for safety (do NOT hardcode credentials)
$username = getenv('VEOLIA_USERNAME') ?: null;
$password = getenv('VEOLIA_PASSWORD') ?: null;
$clientId = getenv('VEOLIA_CLIENT_ID') ?: null;
$contractId = getenv('VEOLIA_CONTRACT_ID') ?: null;
$numeroPds = getenv('VEOLIA_NUMERO_PDS') ?: null;
$dateDebut = getenv('VEOLIA_DATE_DEBUT') ?: null;
$baseHost = getenv('VEOLIA_BASE_HOST') ?: 'https://prd-ael-sirius-backend.istefr.fr';
$annee = intval(getenv('VEOLIA_ANNEE') ?: date('Y'));

if (empty($username) || empty($password) || empty($clientId) || empty($contractId) || empty($numeroPds) || empty($dateDebut)) {
    echo "Missing env vars. Please set VEOLIA_USERNAME, VEOLIA_PASSWORD, VEOLIA_CLIENT_ID, VEOLIA_CONTRACT_ID, VEOLIA_NUMERO_PDS, VEOLIA_DATE_DEBUT\n";
    exit(1);
}

$auth = new VeoliaNouveauAuth();
$authRes = $auth->initiateAuth($username, $password, $clientId);
if ($authRes === false) {
    echo "Authentication failed or a challenge is required.\n";
    exit(1);
}

if (!isset($authRes['AccessToken'])) {
    echo "Unexpected authentication response:\n";
    var_export($authRes);
    exit(1);
}

$accessToken = $authRes['AccessToken'];
$refreshToken = $authRes['RefreshToken'] ?? null;
$expiresIn = intval($authRes['ExpiresIn'] ?? 0);
$expiry = time() + $expiresIn;

echo "Auth OK. Access token expires in $expiresIn seconds.\n";

$fetcher = new VeoliaNouveauFetcher($baseHost);
$url = $fetcher->buildExportUrl($contractId, $annee, $numeroPds, $dateDebut);
$csv = $fetcher->fetchCsvWithToken($url, $accessToken);
if ($csv === false) {
    echo "Failed to download CSV.\n";
    exit(1);
}

$rows = VeoliaNouveauParser::parseCsv($csv);
foreach ($rows as $r) {
    echo sprintf("%s => %s m3 (%.0f L)\n", $r['date'] ?? $r['date_raw'], $r['m3'], $r['litres']);
}

// Note: In plugin integration, save tokens and expiry into EqLogic configuration for reuse.
