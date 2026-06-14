<?php
// core/class/VeoliaNouveauAuth.php
// Handles authentication against AWS Cognito used by the Veolia new site.

class VeoliaNouveauAuth
{
    private string $cognitoEndpoint;
    private string $userAgent = 'Mozilla/5.0 (compatible; Jeedom plugin)';

    public function __construct(private string $region = 'eu-west-3')
    {
        $this->cognitoEndpoint = sprintf('https://cognito-idp.%s.amazonaws.com/', $this->region);
    }

    /**
     * Initiate USER_PASSWORD_AUTH and return token data array or false on error.
     * $clientId must be provided (Cognito App client id).
     */
    public function initiateAuth(string $username, string $password, string $clientId): array|false
    {
        $url = $this->cognitoEndpoint;
        $payload = [
            'AuthFlow' => 'USER_PASSWORD_AUTH',
            'AuthParameters' => [
                'USERNAME' => $username,
                'PASSWORD' => $password
            ],
            'ClientId' => $clientId
        ];

        $resp = $this->postJson($url, json_encode($payload), 'AWSCognitoIdentityProviderService.InitiateAuth');
        if ($resp === false) return false;
        $data = json_decode($resp, true);
        if (!is_array($data)) return false;
        // expected keys: AuthenticationResult -> AccessToken, RefreshToken, ExpiresIn, IdToken
        if (isset($data['AuthenticationResult'])) return $data['AuthenticationResult'];
        // In some flows, a challenge may be required (MFA). Return the raw response so caller can inspect.
        return $data;
    }

    /**
     * Refresh an access token using a refresh token. Returns AuthenticationResult on success.
     */
    public function refreshToken(string $refreshToken, string $clientId): array|false
    {
        $url = $this->cognitoEndpoint;
        $payload = [
            'AuthFlow' => 'REFRESH_TOKEN_AUTH',
            'AuthParameters' => [
                'REFRESH_TOKEN' => $refreshToken
            ],
            'ClientId' => $clientId
        ];
        $resp = $this->postJson($url, json_encode($payload), 'AWSCognitoIdentityProviderService.InitiateAuth');
        if ($resp === false) return false;
        $data = json_decode($resp, true);
        if (!is_array($data)) return false;
        if (isset($data['AuthenticationResult'])) return $data['AuthenticationResult'];
        return $data;
    }

    private function postJson(string $url, string $jsonBody, string $xAmzTarget): string|false
    {
        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/x-amz-json-1.1',
            'X-Amz-Target: ' . $xAmzTarget,
            'Accept: */*',
            'Origin: https://www.eau.veolia.fr'
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => ''
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($resp === false || ($code < 200 || $code >= 400)) {
            // don't expose sensitive info in logs
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return $resp;
    }
}
