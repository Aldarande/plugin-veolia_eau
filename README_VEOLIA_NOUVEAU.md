# Veolia nouveau - integration notes

This branch adds initial support for the "Veolia nouveau" site flow (Cognito authentication + export endpoint).

Files added:
- core/class/VeoliaNouveauAuth.php  -> handles Cognito InitiateAuth / Refresh
- core/class/VeoliaNouveauFetcher.php -> builds export URL and fetches CSV with Bearer token
- core/class/VeoliaNouveauParser.php -> parses the CSV produced by Veolia
- core/class/test_veolia_nouveau.php -> CLI test harness (use env vars, see below)

Quick test (local):

1) Set environment variables (do NOT commit credentials):

export VEOLIA_USERNAME="your_username"
export VEOLIA_PASSWORD="your_password"
export VEOLIA_CLIENT_ID="your_cognito_app_client_id"
export VEOLIA_CONTRACT_ID="3026534"
export VEOLIA_NUMERO_PDS="002OBM8"
export VEOLIA_DATE_DEBUT="2015-08-01"
export VEOLIA_BASE_HOST="https://prd-ael-sirius-backend.istefr.fr"

2) Run test:
php core/class/test_veolia_nouveau.php

Notes and next steps:
- The current implementation uses plain username/password to call Cognito and retrieve tokens. In the plugin integration, tokens should be saved in EqLogic configuration keys (veolia_token, veolia_refresh_token, veolia_token_expiry) and passwords should be stored with care (Jeedom helpers / encryption if available).
- If AWS Cognito returns a challenge (MFA) the current code will return failure; handling MFA requires implementing the challenge flow or falling back to manual token entry.
- Security: the password you pasted earlier in the chat is potentially exposed; rotate the password in your Veolia account and prefer using per-eq tokens or dedicated application credentials.
