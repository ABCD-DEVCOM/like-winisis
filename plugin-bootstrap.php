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

use ABCD\Common\LanguageManager;
$bridge = PluginBridge::getInstance();

if (!function_exists('like_winisis_charset')) {
    function like_winisis_charset(string $text): string
    {
        global $msgstr, $langManager, $charset;

        if (isset($langManager)) {
            $userLang = $_SESSION["lang"] ?? $_REQUEST["lang"] ?? 'en';
            $plugin_lang = $langManager->loadPluginTranslations(__DIR__, 'like-winisis', 'like-winisis.tab', $userLang);

            if (is_array($msgstr) && is_array($plugin_lang)) {
                $msgstr = array_merge($msgstr, $plugin_lang);
            }

            $targetEncoding = in_array($charset, ['ISO-8859-1', 'ANSI', 'WINDOWS-1252'], true)
                ? 'ISO-8859-1'
                : 'UTF-8';

            // Check if the source string from the .tab file is UTF-8 or ISO-8859-1
            $isStringUtf8 = mb_check_encoding($text, 'UTF-8');

            if ($targetEncoding === 'UTF-8' && !$isStringUtf8) {
                // File is ISO-8859-1, but current database is UTF-8
                $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
            } elseif ($targetEncoding === 'ISO-8859-1' && $isStringUtf8) {
                // File is UTF-8, but current database is ISO-8859-1
                $text = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
            }
            return $msgstr[$text] ?? $text;
        }
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

    $title = like_winisis_charset($msgstr['like_winisis_title'] ?? 'Legacy Search');
    $placeholder = like_winisis_charset($msgstr['like_winisis_placeholder'] ?? '?v10:"Maria" AND v20:"Brasil"');
    $searchBtn = like_winisis_charset($msgstr['like_winisis_search_btn'] ?? 'Search');
    $saveBtn = like_winisis_charset($msgstr['like_winisis_save_btn'] ?? 'Save expression');
    $resetBtn = like_winisis_charset($msgstr['like_winisis_reset_btn'] ?? 'Clear results');
    $emptyMsg = like_winisis_charset($msgstr['like_winisis_empty'] ?? 'No records found.');
    $errorMsg = like_winisis_charset($msgstr['like_winisis_error'] ?? 'Syntax error.');
    $fieldMsg = like_winisis_charset($msgstr['like_winisis_field_content'] ?? 'Content');
    $historyTitle = like_winisis_charset($msgstr['like_winisis_history_title'] ?? 'Session History');
    $savedTitle = like_winisis_charset($msgstr['like_winisis_saved_title'] ?? 'Saved Expressions');
    $clearTxt = like_winisis_charset($msgstr['like_winisis_clear_history'] ?? 'Clear');
    $syncedTxt = like_winisis_charset($msgstr['like_winisis_session_synced'] ?? 'Synced with Selected Records');

    $helpTitle = like_winisis_charset($msgstr['like_winisis_help_title'] ?? 'Supported syntax:');
    $helpP = like_winisis_charset($msgstr['like_winisis_help_p'] ?? '<b>?p(vXX)</b> / <b>?a(vXX)</b>');
    $helpMfn = like_winisis_charset($msgstr['like_winisis_help_mfn'] ?? '<b>?mfn>XX</b> / <b>?mfn<XX</b>');
    $helpStr = like_winisis_charset($msgstr['like_winisis_help_str'] ?? '<b>?vXX:"text"</b>');
    $helpComb = like_winisis_charset($msgstr['like_winisis_help_comb'] ?? '<b>?v10:"A" AND v20:"B"</b>');
    $helpStrRange = like_winisis_charset($msgstr['like_winisis_help_str_range'] ?? '<b>?*10,125 v0:"text"</b>');

    $sidebar = '
    <div id="like-winisis-sidebar" class="winisis-sidebar">
        <div class="winisis-header">
            <h4><i class="fas fa-terminal"></i> ' . $title . '</h4>
            <button type="button" id="like-winisis-close">&times;</button>
        </div>
        <div class="winisis-body">
            <div class="winisis-input-group">
                <input type="text" id="like-winisis-input" placeholder="' . $placeholder . '">
                <button type="button" id="like-winisis-exec-btn" class="winisis-icon-btn primary" title="' . $searchBtn . '">
                    <i class="fas fa-play"></i>
                </button>
                <button type="button" id="like-winisis-save-btn" class="winisis-icon-btn" title="' . $saveBtn . '">
                    <i class="fas fa-bookmark"></i>
                </button>
                <!-- NOVO BOTÃO DE RESET AQUI: -->
                <button type="button" id="like-winisis-reset-btn" class="winisis-icon-btn" title="' . $resetBtn . '">
                    <i class="fas fa-eraser"></i>
                </button>
            </div>

            <div id="like-winisis-session-bar" class="winisis-session-bar" style="display:none;">
                <span id="like-winisis-count-badge"></span>
                <span class="winisis-sync-tag"><i class="fas fa-link"></i> ' . $syncedTxt . '</span>
            </div>

            <div class="winisis-panels-Accordion">
                <details id="like-winisis-history-details">
                    <summary>
                        <span><i class="fas fa-history"></i> ' . $historyTitle . '</span>
                        <a href="javascript:void(0);" id="like-winisis-clear-history" class="winisis-mini-link">' . $clearTxt . '</a>
                    </summary>
                    <div id="like-winisis-history-list" class="winisis-expr-list"></div>
                </details>

                <details id="like-winisis-saved-details">
                    <summary>
                        <span><i class="fas fa-bookmark"></i> ' . $savedTitle . '</span>
                    </summary>
                    <div id="like-winisis-saved-list" class="winisis-expr-list"></div>
                </details>
            </div>

            <div id="like-winisis-results">
                <div class="winisis-help">
                    <h5>' . $helpTitle . '</h5>
                    <ul>
                        <li>' . $helpP . '</li>
                        <li>' . $helpMfn . '</li>
                        <li>' . $helpStr . '</li>
                        <li>' . $helpComb . '</li>
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
