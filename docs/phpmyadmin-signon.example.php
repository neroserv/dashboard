<?php

/**
 * phpMyAdmin Signon-Skript für automatischen Login aus der PraxisHosting-App.
 *
 * Dieses Skript auf den phpMyAdmin-Server kopieren (z. B. neben index.php)
 * und in config.inc.php eintragen:
 *
 *   $cfg['Servers'][$i]['auth_type'] = 'signon';
 *   $cfg['Servers'][$i]['SignonSession'] = 'SignonSession';
 *   $cfg['Servers'][$i]['SignonURL'] = 'https://IHRE-PMA-DOMAIN/dieses-script.php';
 *
 * In .env der Laravel-App:
 *   PHPMYADMIN_SIGNON_URL=https://IHRE-PMA-DOMAIN/dieses-script.php
 *   PHPMYADMIN_CREDENTIALS_BASE_URL=https://IHRE-LARAVEL-APP-URL  (muss vom phpMyAdmin-Server aus erreichbar sein)
 *
 * Ablauf: Nutzer klickt „In phpMyAdmin öffnen“ → Laravel leitet mit token und
 * credentials_url um → dieses Skript holt die Zugangsdaten von der Laravel-API
 * und setzt die Session → Weiterleitung zu phpMyAdmin → eingeloggt.
 */
$token = $_GET['token'] ?? '';
$credentialsUrl = $_GET['credentials_url'] ?? '';
if ($token === '' || $credentialsUrl === '') {
    exit('Fehler: token und credentials_url erforderlich.');
}

$fullUrl = $credentialsUrl.(str_contains($credentialsUrl, '?') ? '&' : '?').'token='.urlencode($token);
$ctx = stream_context_create([
    'http' => [
        'timeout' => 10,
        'ignore_errors' => true,
    ],
]);
$json = @file_get_contents($fullUrl, false, $ctx);
if ($json === false) {
    exit('Fehler: Zugangsdaten konnten nicht geladen werden. Prüfen Sie, ob der phpMyAdmin-Server die Laravel-App erreichen kann.');
}
$data = json_decode($json, true);
if (! is_array($data) || ! isset($data['username'], $data['password'])) {
    exit('Fehler: Ungültige Antwort von der Laravel-App.');
}

$sessionName = 'SignonSession';
session_name($sessionName);
session_start();
$_SESSION['PMA_single_signon_user'] = $data['username'];
$_SESSION['PMA_single_signon_password'] = $data['password'];
$_SESSION['PMA_single_signon_host'] = $data['host'] ?? '127.0.0.1';
$_SESSION['PMA_single_signon_port'] = (string) ($data['port'] ?? 3306);
$_SESSION['PMA_single_signon_HMAC_secret'] = hash('sha1', random_bytes(32));
session_write_close();

$base = dirname($_SERVER['SCRIPT_NAME']);
if ($base === '/' || $base === '\\') {
    $base = '';
}
$redirect = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    .'://'.$_SERVER['HTTP_HOST'].$base.'/index.php';
header('Location: '.$redirect);
exit;
