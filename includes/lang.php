<?php
// ============================================================
// Language loader — call loadLang() once (done in config.php),
// then use __('key') or __('key', ['placeholder' => 'value'])
// to get translated strings.
//
// Language is stored in $_SESSION['lang'] (default: 'en').
// Switching is done via ?set_lang=fa|en in any page URL.
// ============================================================

// Supported locales → [label, dir, Bootstrap RTL?]
const LANG_SUPPORTED = [
    'en' => ['label' => 'English', 'dir' => 'ltr', 'rtl' => false],
    'fa' => ['label' => 'فارسی',   'dir' => 'rtl', 'rtl' => true],
];

function loadLang(): void
{
    // Allow switching via query string
    if (isset($_GET['set_lang']) && array_key_exists($_GET['set_lang'], LANG_SUPPORTED)) {
        $_SESSION['lang'] = $_GET['set_lang'];
        // Redirect without the query string so the GET param doesn't linger
        $url = strtok($_SERVER['REQUEST_URI'], '?');
        header('Location: ' . $url);
        exit;
    }

    $lang = $_SESSION['lang'] ?? 'en';
    if (!array_key_exists($lang, LANG_SUPPORTED)) {
        $lang = 'en';
    }

    $file = dirname(__DIR__) . '/lang/' . $lang . '.php';
    if (!file_exists($file)) {
        $file = dirname(__DIR__) . '/lang/en.php';
        $lang = 'en';
    }

    $GLOBALS['_LANG']     = require $file;
    $GLOBALS['_LANG_KEY'] = $lang;
}

/**
 * Translate a key.
 * Supports simple placeholder replacement: __('key', ['name' => 'Alice'])
 * will replace {name} with Alice.
 */
function __(string $key, array $replace = []): string
{
    $str = $GLOBALS['_LANG'][$key] ?? $key;
    foreach ($replace as $k => $v) {
        $str = str_replace('{' . $k . '}', $v, $str);
    }
    return $str;
}

/** Returns the active language key, e.g. 'en' or 'fa'. */
function currentLang(): string
{
    return $GLOBALS['_LANG_KEY'] ?? 'en';
}

/** Returns true when the active language is RTL. */
function isRtl(): bool
{
    return LANG_SUPPORTED[currentLang()]['rtl'] ?? false;
}

/** Returns 'rtl' or 'ltr'. */
function langDir(): string
{
    return LANG_SUPPORTED[currentLang()]['dir'] ?? 'ltr';
}

/**
 * Render the language-switcher dropdown (Bootstrap 5).
 * Call this wherever you want the button in the template.
 */
function langSwitcherHtml(): string
{
    $current = currentLang();
    $label   = LANG_SUPPORTED[$current]['label'];
    $items   = '';
    foreach (LANG_SUPPORTED as $code => $info) {
        $active = ($code === $current) ? ' active' : '';
        $items .= '<li><a class="dropdown-item' . $active . '" href="?set_lang=' . $code . '">'
            . htmlspecialchars($info['label']) . '</a></li>';
    }
    return '<div class="dropdown">'
        . '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">'
        . '<i class="fas fa-globe me-1"></i>' . htmlspecialchars($label)
        . '</button>'
        . '<ul class="dropdown-menu dropdown-menu-end">' . $items . '</ul>'
        . '</div>';
}
