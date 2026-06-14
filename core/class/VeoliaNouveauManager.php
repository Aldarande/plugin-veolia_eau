<?php
// core/class/VeoliaNouveauManager.php
// Manager that provides a cron runner to import consumption for all equipment using veolia_nouveau service.

require_once __DIR__ . '/VeoliaNouveauFetcher.php';
require_once __DIR__ . '/VeoliaNouveauParser.php';
require_once __DIR__ . '/VeoliaNouveauAuth.php';

class VeoliaNouveauManager
{
    /**
     * Run import for all equipments. Intended to be called from plugin cron (e.g. in plugin.class.php cron method).
     * Example call from plugin.class.php: VeoliaNouveauManager::runCron();
     */
    public static function runCron(): void
    {
        // Try to gather eqLogics for common plugin types. Adjust the types if your plugin uses a different type.
        $eqs = [];
        if (class_exists('eqLogic')) {
            try {
                if (method_exists('eqLogic', 'byType')) {
                    $list1 = @eqLogic::byType('plugin_veolia_eau');
                    if (is_array($list1)) $eqs = array_merge($eqs, $list1);
                    $list2 = @eqLogic::byType('veolia_eau');
                    if (is_array($list2)) $eqs = array_merge($eqs, $list2);
                }
            } catch (Throwable $e) {
                // ignore — this method is called inside Jeedom normally
            }
        }

        foreach ($eqs as $eq) {
            try {
                if (!method_exists($eq, 'getConfiguration')) continue;
                if ($eq->getConfiguration('type_service') !== 'veolia_nouveau') continue;

                $clientId = $eq->getConfiguration('client_id');
                $contractId = $eq->getConfiguration('contract_id');
                $numeroPds = $eq->getConfiguration('numero_pds');
                $dateDebut = $eq->getConfiguration('date_debut');
                $baseHost = $eq->getConfiguration('base_host') ?: 'https://prd-ael-sirius-backend.istefr.fr';
                $annee = intval(date('Y'));

                $fetcher = new VeoliaNouveauFetcher($baseHost);

                // Ensure token
                $accessToken = $fetcher->ensureAccessToken($eq);
                if ($accessToken === false) {
                    // log or set eq status
                    if (method_exists('log', 'add')) {
                        log::add('plugin-veolia_eau', 'warning', "Veolia nouveau: cannot obtain token for eq " . $eq->getName());
                    }
                    continue;
                }

                // If access token was obtained via login/refresh, ensure we save tokens back into eq config
                // For safety, re-run InitiateAuth to obtain refresh token only when username/password present
                // If using ensureAccessToken as above, it may have performed auth but didn't save tokens; here we attempt to save.
                // Ideally ensureAccessToken will return tokens and we can save them, but we keep this simple for now.

                $url = $fetcher->buildExportUrl($contractId, $annee, $numeroPds, $dateDebut);
                $csv = $fetcher->fetchCsvWithToken($url, $accessToken);
                if ($csv === false) {
                    if (class_exists('log')) log::add('plugin-veolia_eau', 'warning', "Veolia nouveau: failed to download CSV for eq " . $eq->getName());
                    continue;
                }
                $rows = VeoliaNouveauParser::parseCsv($csv);
                // For each row, create/update commands. The plugin structure determines how to save.
                // Here we provide an example: create an info cmd named "consommation_YYYY_MM" and push value.
                foreach ($rows as $r) {
                    $logicalId = 'consommation_' . ($r['date'] ? str_replace('-', '_', $r['date']) : preg_replace('/[^0-9]/','_', $r['date_raw']));
                    // find command
                    $cmd = null;
                    if (method_exists('cmd', 'byEqLogicIdAndLogicalId')) {
                        $cmds = cmd::byEqLogicIdAndLogicalId($eq->getId(), $logicalId);
                        if (is_array($cmds) && count($cmds)) $cmd = $cmds[0];
                    }
                    if ($cmd === null) {
                        if (!class_exists('cmd')) continue;
                        $cmd = new cmd();
                        $cmd->setName('Consommation ' . ($r['date'] ?? $r['date_raw']));
                        $cmd->setEqLogic_id($eq->getId());
                        $cmd->setLogicalId($logicalId);
                        $cmd->setIsVisible(1);
                        $cmd->setType('info');
                        $cmd->setSubType('numeric');
                        $cmd->setDisplay('generic_type', 'ENERGY');
                        $cmd->save();
                    }
                    // push value
                    if (method_exists($cmd, 'event')) {
                        $cmd->event($r['m3']);
                    }
                }

            } catch (Throwable $e) {
                if (class_exists('log')) log::add('plugin-veolia_eau', 'error', 'Veolia nouveau cron error: ' . $e->getMessage());
                continue;
            }
        }
    }
}
