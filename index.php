<?php
/*
 * Script: Like Winisis Search
 * Author: Roger Craveiro Guilherme
 * Description: Enables legacy Winisis search syntax (?p(v10), ?mfn>3) with contextual side-panel results.
 */

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config_path = realpath(__DIR__ . '/../../../central/config.php');
if (file_exists($config_path)) {
    require_once $config_path;
}

if (!class_exists('PluginBridge')) {
    ob_end_clean();
    header("HTTP/1.1 403 Forbidden");
    die("Direct access forbidden.");
}

$action = $_REQUEST['action'] ?? '';

match ($action) {
    'ajax' => require_once __DIR__ . '/ajax.php',
    default => header("HTTP/1.1 400 Bad Request")
};
