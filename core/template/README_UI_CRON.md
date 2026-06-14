<!-- README additions: UI & cron integration instructions -->

## UI and Cron integration for "Veolia nouveau"

Files added in this branch that help integrate the UI and cron:
- core/template/veolia_nouveau_equipement.html  (HTML form snippet)
- core/js/veolia_nouveau.js                      (JS to handle Test button)
- core/ajax/veolia_nouveau.ajax.php              (AJAX endpoint used by the UI Test button)
- core/class/VeoliaNouveauManager.php            (cron runner to import for all equipments)

How to integrate UI into your plugin equipment template
1. Open your equipment edit template (commonly in `desktop/php/` or `core/template/` depending on your plugin) and include the content of `core/template/veolia_nouveau_equipement.html` where the configuration fields are shown.
2. Add the JS include for `core/js/veolia_nouveau.js` in the equipment page so the Test button works (or merge the JS into your existing plugin JS file).
3. The Test button calls `core/ajax/veolia_nouveau.ajax.php?action=test` and returns parsed CSV rows for preview.

Cron integration
1. In your plugin main class (usually `core/class/plugin.class.php`) add a call in the cron handler to:

   require_once __DIR__ . '/VeoliaNouveauManager.php';
   VeoliaNouveauManager::runCron();

   This will iterate equipments and import CSVs for those with configuration `type_service = veolia_nouveau`.

2. The manager attempts to obtain access tokens (refresh or login) and will fetch CSVs and create/update info commands for each parsed month. Adapt the command creation logic to your plugin naming conventions if needed.

Security notes
- Store passwords/tokens securely using Jeedom helpers where available.
- Do NOT log tokens or passwords. If you previously pasted credentials publicly, rotate them immediately.

