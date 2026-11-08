<?php
/*
 * Script: Like Winisis Search
 * Author: Roger Craveiro Guilherme
 * Description: Enables legacy Winisis search syntax (?p(v10), ?mfn>3) with contextual side-panel results.
 */

if (!class_exists('PluginBridge')) {
    header("HTTP/1.1 403 Forbidden");
    die("Direct access forbidden.");
}

$bridge = PluginBridge::getInstance();

/**
 * Helper to adapt UTF-8 .tab strings to the active ABCD $charset (ISO-8859-1 or UTF-8)
 */
if (!function_exists('like_winisis_charset')) {
    function like_winisis_charset(string $text): string
    {
        global $charset;
        $targetCharset = (!empty($charset) && strtoupper($charset) === 'ISO-8859-1') ? 'ISO-8859-1' : 'UTF-8';
        if ($targetCharset === 'ISO-8859-1' && mb_check_encoding($text, 'UTF-8')) {
            return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        }
        return $text;
    }
}

abcd_add_hook('dataentry_toolbar_search', function (string $html) use ($bridge): string {
    global $msgstr, $langManager;

    $lang = $_SESSION['lang'] ?? $bridge->get('lang', 'en');
    $pluginPath = realpath(__DIR__);

    if (isset($langManager)) {
        $plugin_msgs = $langManager->loadPluginTranslations($pluginPath, 'like-winisis', 'like-winisis.tab', $lang);
        $msgstr = array_merge($msgstr ?? [], $plugin_msgs);
    }

    $title = htmlspecialchars(like_winisis_charset($msgstr['like_winisis_title'] ?? 'Legacy Search'));

    $btn = '<a href="javascript:void(0);" id="like-winisis-toggle" class="btn-toolbar-blue" title="' . $title . '" style="margin-left: 5px;">';
    $btn .= '<img src="../../content/plugins/like-winisis/assets/img/like-winisis-icon.png" alt="' . $title . '" style="width: 20px; height: 20px;">';
    $btn .= '</a>';

    return $html . $btn;
});

abcd_add_hook('dataentry_footer', function (string $html) use ($bridge): string {
    global $msgstr;

    $title = htmlspecialchars(like_winisis_charset($msgstr['like_winisis_title'] ?? 'Legacy Search'));
    $placeholder = htmlspecialchars(like_winisis_charset($msgstr['like_winisis_placeholder'] ?? '?p(v10)'));
    $emptyMsg = htmlspecialchars(like_winisis_charset($msgstr['like_winisis_empty'] ?? 'No records found.'));
    $errorMsg = htmlspecialchars(like_winisis_charset($msgstr['like_winisis_error'] ?? 'Syntax error.'));
    $fieldMsg = htmlspecialchars(like_winisis_charset($msgstr['like_winisis_field_content'] ?? 'Field'));

    $helpTitle = htmlspecialchars(like_winisis_charset($msgstr['like_winisis_help_title'] ?? 'Como usar:'));
    $helpP = like_winisis_charset($msgstr['like_winisis_help_p'] ?? '<b>?p(vXX)</b>: Presença');
    $helpMfn = like_winisis_charset($msgstr['like_winisis_help_mfn'] ?? '<b>?mfn>XX</b>: MFN');
    $helpStr = like_winisis_charset($msgstr['like_winisis_help_str'] ?? '<b>? vXX:"texto"</b>: String');
    $helpStrRange = like_winisis_charset($msgstr['like_winisis_help_str_range'] ?? '<b>? *1,20 vXX:"texto"</b>: String com limite');

    $sidebar = '
<div id="like-winisis-sidebar" class="winisis-sidebar">
    <div class="winisis-header">
        <h4><i class="fas fa-terminal"></i> ' . $title . '</h4>
        <button type="button" id="like-winisis-close">&times;</button>
    </div>
    <div class="winisis-body">
        <input type="text" id="like-winisis-input" placeholder="' . $placeholder . '">
        <div id="like-winisis-results">
            <!-- Mini Help inicial -->
            <div class="winisis-help">
                <h5>' . $helpTitle . '</h5>
                <ul>
                    <li>' . $helpP . '</li>
                    <li>' . $helpMfn . '</li>
                    <li>' . $helpStr . '</li>
                    <li>' . $helpStrRange . '</li>
                </ul>
            </div>
        </div>
    </div>
</div>';

    $scripts = '<link rel="stylesheet" href="../../content/plugins/like-winisis/assets/css/like-winisis.css">';
    $scripts .= '<script>
        const winisisLang = {
            empty: "' . $emptyMsg . '",
            error: "' . $errorMsg . '",
            field: "' . $fieldMsg . '"
        };
    </script>';
    $scripts .= '<script src="../../content/plugins/like-winisis/assets/js/like-winisis.js"></script>';

    return $html . $sidebar . $scripts;
});
