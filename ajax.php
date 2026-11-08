<?php

/**
 * Ajax middleware to translate legacy Winisis query into WXIS format.
 */
if (!class_exists('PluginBridge')) {
    header("HTTP/1.1 403 Forbidden");
    die("Direct access forbidden.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("HTTP/1.1 405 Method Not Allowed");
    exit;
}

global $xWxis, $contenido, $msgstr, $db_path, $Wxis, $wxisUrl, $actparfolder, $arrHttp, $server_url, $postMethod, $cisis_ver, $unicode, $langManager, $lang;

$bridge = PluginBridge::getInstance();
$dbPath = $bridge->get('db_path') ?: $db_path;
$abcdPath = $bridge->get('abcd_path', realpath(__DIR__ . '/../../../central'));

// Ensure plugin translations are available in $msgstr
$currentLang = $_SESSION['lang'] ?? $lang ?? 'en';
if (isset($langManager)) {
    $pluginMsgs = $langManager->loadPluginTranslations(__DIR__, 'like-winisis', 'like-winisis.tab', $currentLang);
    $msgstr = array_merge($msgstr ?? [], $pluginMsgs);
}

$errorText = mb_convert_encoding($msgstr['like_winisis_error'] ?? '', 'UTF-8', 'UTF-8, ISO-8859-1');

$rawQuery = trim($_POST['query'] ?? '');
$base = trim($_POST['base'] ?? '');

// Clean any output generated during config.php bootstrap
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

if (empty($rawQuery) || empty($base)) {
    echo json_encode([
        'status' => 'error',
        'message' => $errorText
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// wxis_llamar.php requires $arrHttp['base']
$arrHttp['base'] = $base;

$pft = '';
$fieldExtracted = '';
$from = 1;
$to = 999999;

// 1. ?p(vXX) -> Presence of field XX
if (preg_match('/^\?p\(v(\d+)\)$/i', $rawQuery, $matches)) {
    $fieldExtracted = $matches[1];
    // Concatenated safely to avoid PHP curly brace variable interpolation on '+|; |'
    $pft = "if p(v" . $fieldExtracted . ") then f(mfn,1,0),'|',v" . $fieldExtracted . "+|; |/ fi";
}
// 2. ?mfn>XX -> Relational MFN > XX
elseif (preg_match('/^\?mfn\s*>\s*(\d+)$/i', $rawQuery, $matches)) {
    $from = (int)$matches[1] + 1;
    $fieldExtracted = 'MFN';
    $pft = "f(mfn,1,0),'|','MFN ',f(mfn,1,0)/";
}
// 3. ?mfn<XX -> Relational MFN < XX
elseif (preg_match('/^\?mfn\s*<\s*(\d+)$/i', $rawQuery, $matches)) {
    $to = max(1, (int)$matches[1] - 1);
    $fieldExtracted = 'MFN';
    $pft = "f(mfn,1,0),'|','MFN ',f(mfn,1,0)/";
// 4. ? *10,125 v0:"Petrarca" OU ? v0:"Petrarca" -> Busca de string (com ou sem range de MFN)
} elseif (preg_match('/^\?\s*(?:\*(\d+)\s*,\s*(\d+)\s+)?v(\d+)\s*:\s*"([^"]+)"$/i', $rawQuery, $matches)) {
    // Se o range (*10,125) foi informado, ele estará nos índices 1 e 2
    $hasRange = !empty($matches[1]) && !empty($matches[2]);
    $from = $hasRange ? (int)$matches[1] : 1;
    $to = $hasRange ? (int)$matches[2] : 999999;
    
    $tag = $matches[3]; // Ex: 0
    $searchString = $matches[4]; // Ex: Petrarca
    $fieldExtracted = $tag === '0' ? 'ALL' : $tag;

    // Escapa aspas simples para não quebrar a sintaxe do PFT
    $safeString = str_replace("'", "''", $searchString);

    if ($tag === '0') {
        // Busca na ficha inteira (v0). O WXIS retorna a ficha concatenada
        $pft = "if v0 : '" . $safeString . "' then f(mfn,1,0),'|',v0/ fi";
    } else {
        // Busca em um campo específico
        $pft = "if v" . $tag . " : '" . $safeString . "' then f(mfn,1,0),'|',v" . $tag . "+|; |/ fi";
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => $errorText
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Resolve cipar path
$parFolder = !empty($actparfolder) ? $actparfolder : "par/";
$ciparFile = ($parFolder === "/")
    ? ($dbPath . $base . "/par/" . $base . ".par")
    : ($dbPath . $parFolder . $base . ".par");

// Select available .xis script in /central/dataentry/wxis/
if (file_exists($xWxis . "leer_mfnrange.xis")) {
    $IsisScript = $xWxis . "leer_mfnrange.xis";
    $query = "&base=" . $base
        . "&cipar=" . $ciparFile
        . "&from=" . $from
        . "&to=" . $to
        . "&Pft=" . urlencode($pft)
        . "&Formato=" . urlencode($pft);
} elseif (file_exists($xWxis . "imprime.xis")) {
    $IsisScript = $xWxis . "imprime.xis";
    $query = "&base=" . $base
        . "&cipar=" . $ciparFile
        . "&Opcion=rango"
        . "&from=" . $from
        . "&to=" . $to
        . "&Pft=" . urlencode($pft)
        . "&Formato=" . urlencode($pft);
} else {
    $IsisScript = $xWxis . "buscar.xis";
    $query = "&base=" . $base
        . "&cipar=" . $ciparFile
        . "&Opcion=buscar"
        . "&Expresion=$"
        . "&from=" . $from
        . "&to=" . $to
        . "&Pft=" . urlencode($pft)
        . "&Formato=" . urlencode($pft);
}

$_GET['IsisScript'] = $IsisScript;
$contenido = [];

ob_start();
require $abcdPath . "/common/wxis_llamar.php";
$debugHtml = ob_get_clean();

$results = [];
if (!empty($contenido) && is_array($contenido)) {
    foreach ($contenido as $line) {
        $line = trim($line);
        if ($line !== '' && str_contains($line, '|') && !str_starts_with($line, 'WXIS|')) {
            [$mfn, $fieldData] = explode('|', $line, 2);
            $mfn = (int)trim($mfn);
            if ($mfn > 0) {
                $results[] = [
                    'mfn' => $mfn,
                    'content' => mb_convert_encoding(trim($fieldData), 'UTF-8', 'UTF-8, ISO-8859-1')
                ];
            }
        }
    }
}

echo json_encode([
    'status' => 'success',
    'field' => $fieldExtracted,
    'total' => count($results),
    'data' => $results,
    'debug' => empty($results) ? strip_tags($debugHtml) : ''
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
exit;
