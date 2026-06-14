<?php
// Save endpoint to persist equipment configuration via AJAX (called from UI save action or separate button)

try { require_once dirname(__FILE__) . '/../class/VeoliaNouveauAuth.php'; } catch (Exception $e) {}

function send_json($a){ header('Content-Type: application/json'); echo json_encode($a); exit; }

$action = $_POST['action'] ?? null;
if ($action !== 'save') send_json(['state'=>'error','message'=>'Action invalide']);

$eqId = intval($_POST['eq_id'] ?? 0);
$cfgRaw = $_POST['config'] ?? null;
if ($eqId<=0 || $cfgRaw===null) send_json(['state'=>'error','message'=>'Paramètres manquants']);
$cfg = json_decode($cfgRaw, true);
if (!is_array($cfg)) send_json(['state'=>'error','message'=>'Config invalide']);

if (!class_exists('eqLogic') || !method_exists('eqLogic','byId')) send_json(['state'=>'error','message'=>'Integration Jeedom indisponible']);
$eq = eqLogic::byId($eqId);
if (!is_object($eq)) send_json(['state'=>'error','message'=>'Equipement introuvable']);

// Save useful keys
$eq->setConfiguration('type_service', $cfg['type_service'] ?? 'veolia');
$eq->setConfiguration('username', $cfg['username'] ?? '');
$eq->setConfiguration('password', $cfg['password'] ?? '');
$eq->setConfiguration('client_id', $cfg['client_id'] ?? '');
$eq->setConfiguration('contract_id', $cfg['contract_id'] ?? '');
$eq->setConfiguration('numero_pds', $cfg['numero_pds'] ?? '');
$eq->setConfiguration('date_debut', $cfg['date_debut'] ?? '');
$eq->setConfiguration('base_host', $cfg['base_host'] ?? 'https://prd-ael-sirius-backend.istefr.fr');
$eq->save();

send_json(['state'=>'ok','message'=>'Configuration enregistrée']);
