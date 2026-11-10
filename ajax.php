<?php

/*
 * Script: Like Winisis Search
 * Author: Roger Craveiro Guilherme
 * Description: Enables legacy Winisis search syntax (?p(v10), ?mfn>3) with contextual side-panel results.
 *
 * Ajax middleware to translate combined legacy Winisis queries into WXIS PFT format
 * and manage session search history.
 */
if (!class_exists('PluginBridge')) {
    header("HTTP/1.1 403 Forbidden");
    die("Direct access forbidden.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("HTTP/1.1 405 Method Not Allowed");
    exit;
}

error_reporting(0);
ini_set('display_errors', '0');

global $xWxis, $contenido, $msgstr, $db_path, $Wxis, $wxisUrl, $actparfolder, $arrHttp, $server_url, $postMethod, $cisis_ver, $unicode, $langManager, $lang;

$bridge = PluginBridge::getInstance();
$dbPath = $bridge->get('db_path') ?: $db_path;
$abcdPath = $bridge->get('abcd_path', realpath(__DIR__ . '/../../../central'));

$currentLang = $_SESSION['lang'] ?? $lang ?? 'en';
if (isset($langManager)) {
    $pluginMsgs = $langManager->loadPluginTranslations(__DIR__, 'like-winisis', 'like-winisis.tab', $currentLang);
    $msgstr = array_merge($msgstr ?? [], $pluginMsgs);
}

$errorText = mb_convert_encoding($msgstr['like_winisis_error'] ?? 'Syntax error or execution failure.', 'UTF-8', 'UTF-8, ISO-8859-1');

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

/**
 * Ensures $_SESSION['history'] only contains valid delimited strings ("base$$|$$expression$$|$$total")
 * so that ABCD core scripts (fmt.php / search_history.php) never throw a TypeError on explode().
 */
function sanitizeNativeAbcdHistory(): void
{
    if (!isset($_SESSION['history']) || !is_array($_SESSION['history'])) {
        $_SESSION['history'] = [];
        return;
    }

    $cleanHistory = [];
    foreach ($_SESSION['history'] as $item) {
        if (is_string($item) && str_contains($item, '$$|$$')) {
            $cleanHistory[] = $item;
        } elseif (is_array($item) && isset($item['base'], $item['expresion'])) {
            $tot = $item['total'] ?? 0;
            $cleanHistory[] = $item['base'] . '$$|$$' . $item['expresion'] . '$$|$$' . $tot;
        }
    }

    $_SESSION['history'] = $cleanHistory;
}

// Clean any corrupted array entries in $_SESSION['history'] right away
sanitizeNativeAbcdHistory();

$subAction = trim($_POST['sub_action'] ?? 'search');

// Initialize session storage for legacy search history
if (!isset($_SESSION['like_winisis_history']) || !is_array($_SESSION['like_winisis_history'])) {
    $_SESSION['like_winisis_history'] = [];
}

if ($subAction === 'get_history') {
    echo json_encode([
        'status' => 'success',
        'history' => array_values($_SESSION['like_winisis_history'])
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($subAction === 'clear_history') {
    $_SESSION['like_winisis_history'] = [];
    echo json_encode([
        'status' => 'success',
        'history' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawQuery = trim($_POST['query'] ?? '');
$base = trim($_POST['base'] ?? '');

if (empty($rawQuery) || empty($base)) {
    echo json_encode([
        'status' => 'error',
        'message' => $errorText
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Tokenizes and translates a legacy Winisis boolean expression into a safe CISIS PFT condition.
 * Supports:
 *   - Optional leading '?' and optional MFN range '*from,to'
 *   - p(vXX) and a(vXX)
 *   - vXX:"string" or vXX:'string' (including v0:"string" across the whole record)
 *   - mfn > XX, mfn < XX, mfn >= XX, mfn <= XX, mfn = XX
 *   - Boolean operators: AND, OR, NOT (or *, +, ^) and parentheses ( )
 */
function buildCombinedWinisisPft(string $input): ?array
{
    $expr = trim($input);
    if (str_starts_with($expr, '?')) {
        $expr = ltrim(substr($expr, 1));
    }

    $from = 1;
    $to = 999999;

    // Extract optional MFN range prefix: *10,125
    if (preg_match('/^\*(\d+)\s*,\s*(\d+)\s+(.*)$/is', $expr, $rangeMatches)) {
        $from = max(1, (int)$rangeMatches[1]);
        $to = max($from, (int)$rangeMatches[2]);
        $expr = trim($rangeMatches[3]);
    }

    if ($expr === '') {
        return null;
    }

    $fieldsUsed = [];
    $usesV0 = false;
    $pftConditionParts = [];

    $length = strlen($expr);
    $offset = 0;

    while ($offset < $length) {
        // 1. Skip whitespace
        if (preg_match('/\G\s+/A', $expr, $m, 0, $offset)) {
            $offset += strlen($m[0]);
            continue;
        }

        // 2. Parentheses
        if ($expr[$offset] === '(' || $expr[$offset] === ')') {
            $pftConditionParts[] = $expr[$offset];
            $offset++;
            continue;
        }

        // 3. Boolean operators: AND, OR, NOT or Winisis symbols *, +, ^
        if (preg_match('/\G(?:(AND|OR|NOT)\b|(\*|\+|\^))/Ai', $expr, $m, 0, $offset)) {
            $rawOp = strtoupper($m[1] ?? $m[2]);
            $mappedOp = match ($rawOp) {
                'AND', '*' => 'and',
                'OR', '+'  => 'or',
                'NOT', '^' => 'not',
                default    => 'and'
            };
            $pftConditionParts[] = $mappedOp;
            $offset += strlen($m[0]);
            continue;
        }

        // 4. Field presence or absence: p(v10) or a(v10)
        if (preg_match('/\G([pa])\s*\(\s*v(\d+)\s*\)/Ai', $expr, $m, 0, $offset)) {
            $func = strtolower($m[1]);
            $tag = $m[2];
            $fieldsUsed[$tag] = true;
            $pftConditionParts[] = $func . "(v" . $tag . ")";
            $offset += strlen($m[0]);
            continue;
        }

        // 5. Relational MFN condition: mfn > 10, mfn <= 50, mfn = 5
        if (preg_match('/\Gmfn\s*(>=|<=|<>|!=|>|<|=)\s*(\d+)/Ai', $expr, $m, 0, $offset)) {
            $op = $m[1] === '!=' ? '<>' : $m[1];
            $val = (int)$m[2];
            $pftConditionParts[] = "mfn " . $op . " " . $val;
            $offset += strlen($m[0]);
            continue;
        }

        // 6. Field string / substring search: v10:"Maria" or v0:'Petrarca'
        if (preg_match('/\Gv(\d+)\s*:\s*(?:"([^"]*)"|\'([^\']*)\')/Ai', $expr, $m, 0, $offset)) {
            $tag = $m[1];
            $term = $m[2] !== '' ? $m[2] : ($m[3] ?? '');
            if ($term === '') {
                return null;
            }
            $safeTerm = str_replace("'", "\"", $term);
            if ($tag === '0') {
                $usesV0 = true;
            } else {
                $fieldsUsed[$tag] = true;
            }
            $pftConditionParts[] = "v" . $tag . " : '" . $safeTerm . "'";
            $offset += strlen($m[0]);
            continue;
        }

        // Unrecognized token -> fail safely
        return null;
    }

    if (empty($pftConditionParts)) {
        return null;
    }

    $conditionStr = implode(' ', $pftConditionParts);
    $uniqueFields = array_keys($fieldsUsed);

    // Build output format for the matched records
    if (count($uniqueFields) === 1) {
        $singleTag = $uniqueFields[0];
        $displayExpr = "v" . $singleTag . "+|; |";
        $fieldLabel = "v" . $singleTag;
    } elseif (count($uniqueFields) > 1) {
        $segments = [];
        foreach ($uniqueFields as $tag) {
            $segments[] = "if p(v" . $tag . ") then '[v" . $tag . "] ',v" . $tag . "+|; |,'  ' fi";
        }
        $displayExpr = implode(',', $segments);
        $fieldLabel = 'v' . implode(', v', $uniqueFields);
    } elseif ($usesV0) {
        $displayExpr = "v0";
        $fieldLabel = "ALL";
    } else {
        $displayExpr = "'MFN ',f(mfn,1,0)";
        $fieldLabel = "MFN";
    }

    $pft = "if (" . $conditionStr . ") then f(mfn,1,0),'|'," . $displayExpr . "/ fi";

    return [
        'from' => $from,
        'to' => $to,
        'pft' => $pft,
        'field_label' => $fieldLabel
    ];
}

$parsed = buildCombinedWinisisPft($rawQuery);

if ($parsed === null) {
    echo json_encode([
        'status' => 'error',
        'message' => $errorText
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$arrHttp['base'] = $base;
$from = $parsed['from'];
$to = $parsed['to'];
$pft = $parsed['pft'];
$fieldExtracted = $parsed['field_label'];

$parFolder = !empty($actparfolder) ? $actparfolder : "par/";
$ciparFile = ($parFolder === "/")
    ? ($dbPath . $base . "/par/" . $base . ".par")
    : ($dbPath . $parFolder . $base . ".par");

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

$totalResults = count($results);

// 1. Save in the plugin's isolated session history (Winisis style #N (BASE) T=XX : ?expr)
$normalizedExpr = str_starts_with($rawQuery, '?') ? $rawQuery : ('?' . $rawQuery);

foreach ($_SESSION['like_winisis_history'] as $k => $entry) {
    if ($entry['base'] === strtoupper($base) && strcasecmp($entry['expression'], $normalizedExpr) === 0) {
        unset($_SESSION['like_winisis_history'][$k]);
    }
}

$_SESSION['like_winisis_history'] = array_values($_SESSION['like_winisis_history']);
$searchNumber = count($_SESSION['like_winisis_history']) + 1;

$_SESSION['like_winisis_history'][] = [
    'id' => $searchNumber,
    'base' => strtoupper($base),
    'total' => $totalResults,
    'expression' => $normalizedExpr,
    'timestamp' => date('H:i')
];

// 2. Save in native ABCD $_SESSION['history'] strictly as a "$$|$$" delimited string for fmt.php & search_history.php
$nativeHistoryString = $base . '$$|$$' . $normalizedExpr . '$$|$$' . $totalResults;
if (!in_array($nativeHistoryString, $_SESSION['history'], true)) {
    $_SESSION['history'][] = $nativeHistoryString;
}

echo json_encode([
    'status' => 'success',
    'field' => $fieldExtracted,
    'total' => $totalResults,
    'data' => $results,
    'history' => array_values(array_reverse($_SESSION['like_winisis_history'])),
    'debug' => empty($results) ? strip_tags($debugHtml) : ''
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
exit;
