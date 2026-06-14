<?php
// core/class/VeoliaNouveauFetcher.php
// Uses VeoliaNouveauAuth to obtain tokens and fetch the CSV export.

require_once __DIR__ . '/VeoliaNouveauAuth.php';

class VeoliaNouveauFetcher
{
    private string $userAgent = 'Mozilla/5.0 (compatible; Jeedom plugin)';
    private string $baseHost;

    public function __construct(string $baseHost = 'https://prd-ael-sirius-backend.istefr.fr')
    {
        $this->baseHost = rtrim($baseHost, '/');
    }

    public function buildExportUrl(string $contractId, int $annee, string $numeroPds, string $dateDebut): string
    {
        return sprintf(
            '%s/consommations/%s/mensuelles/export?annee=%d&numero-pds=%s&date-debut-abonnement=%s',
            $this->baseHost,
            rawurlencode($contractId),
            intval($annee),
            rawurlencode($numeroPds),
            rawurlencode($dateDebut)
        );
    }

    /**
     * Ensure we have a valid access token for the given eq configuration. This method
     * expects $eq to be a Jeedom EqLogic object or an array with saved configuration.
     * - If an access token exists and not expired => return it
     * - If expired and refresh token exists => try refresh
     * - If no token or refresh fails and login/password provided => try full login
     * Caller must provide 'client_id' config in $eq config.
     */
    public function ensureAccessToken(object|array $eq): string|false
    {
        // Support both EqLogic object and plain array
        $getCfg = fn($k) => (is_object($eq) && method_exists($eq, 'getConfiguration')) ? $eq->getConfiguration($k) : ($eq[$k] ?? null);

        $clientId = $getCfg('client_id');
        if (empty($clientId)) return false;

        // tokens stored in config keys: veolia_token, veolia_refresh_token, veolia_token_expiry
        $accessToken = $getCfg('veolia_token');
        $refreshToken = $getCfg('veolia_refresh_token');
        $expiry = intval($getCfg('veolia_token_expiry') ?? 0);

        // If token exists and still valid
        if (!empty($accessToken) && $expiry > time() + 30) {
            return $accessToken;
        }

        $auth = new VeoliaNouveauAuth();

        // Try refresh if available
        if (!empty($refreshToken)) {
            $res = $auth->refreshToken($refreshToken, $clientId);
            if (is_array($res) && isset($res['AccessToken'])) {
                // caller responsible for saving tokens into eq config
                return $res['AccessToken'];
            }
        }

        // Otherwise try user/password login if present in config
        $username = $getCfg('username');
        $password = $getCfg('password');
        if (!empty($username) && !empty($password)) {
            $res = $auth->initiateAuth($username, $password, $clientId);
            if (is_array($res) && isset($res['AccessToken'])) {
                return $res['AccessToken'];
            }
            // If challenge or MFA required, return false so the UI can show message
        }

        return false;
    }

    /**
     * Fetch CSV using an existing access token (Bearer) — token must be valid.
     */
    public function fetchCsvWithToken(string $url, string $accessToken, int $timeout = 30): string|false
    {
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Origin: https://www.eau.veolia.fr',
            'Accept: */*',
            'Content-Type: application/json'
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => ''
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?? '';
        curl_close($ch);
        if ($resp === false || $code !== 200) return false;
        return $resp;
    }
}
