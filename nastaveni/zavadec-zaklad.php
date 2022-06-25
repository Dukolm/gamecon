<?php

/**
 * Soubor, který zpřístupní definice pro gamecon (třídy, konstanty).
 */

// autoloader Gamecon webu (modelu)
spl_autoload_register(function ($trida) {
    $trida = strtolower(preg_replace('@[A-Z]@', '-$0', lcfirst($trida)));
    $classFile = __DIR__ . '/../model/' . $trida . '.php';
    if (file_exists($classFile)) {
        include_once $classFile;
    }
});

// autoloader Composeru
require_once __DIR__ . '/../vendor/autoload.php';

// starý model s pomocí funkcí
require_once __DIR__ . '/../model/funkce/fw-general.php';
require_once __DIR__ . '/../model/funkce/fw-database.php';
require_once __DIR__ . '/../model/funkce/funkce.php';
require_once __DIR__ . '/../model/funkce/web-funkce.php';

// načtení konfiguračních konstant

error_reporting(E_ALL & ~E_NOTICE); // skrýt notice, aby se konstanty daly "přetížit" dřív vloženými

$host = $_SERVER['SERVER_NAME'] ?? 'localhost';
if (PHP_SAPI === 'cli' || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || ($_ENV['ENV'] ?? '') === 'local') {
    if (file_exists(__DIR__ . '/nastaveni-local.php')) {
        include __DIR__ . '/nastaveni-local.php'; // nepovinné lokální nastavení
    }
    require_once __DIR__ . '/nastaveni-local-default.php'; // výchozí lokální nastavení
} elseif (substr($_SERVER['SERVER_NAME'], -15) === 'beta.gamecon.cz') {
    require_once __DIR__ . '/nastaveni-beta.php';
} elseif (str_ends_with($host, 'blackarrow.gamecon.cz')) {
    require_once __DIR__ . '/nastaveni-blackarrow.php';
} elseif (str_ends_with($host, 'jakublounek.gamecon.cz')) {
    require_once __DIR__ . '/nastaveni-jakublounek.php';
} elseif (str_ends_with($host, 'misahojna.gamecon.cz')) {
    require_once __DIR__ . '/nastaveni-misahojna.php';
} elseif (str_ends_with($host, 'sciator.gamecon.cz')) {
    require_once __DIR__ . '/nastaveni-sciator.php';
} elseif ($_SERVER['SERVER_NAME'] === 'admin.gamecon.cz' || $_SERVER['SERVER_NAME'] === 'gamecon.cz') {
    require_once __DIR__ . '/nastaveni-produkce.php';
} else {
    echo 'Nepodařilo se detekovat prostředí, nelze načíst nastavení verze';
    exit(1);
}

require_once __DIR__ . '/nastaveni.php';

// výchozí hodnoty konstant
// (nezobrazovat chyby, pokud už konstanta byla nastavena dřív)
$puvodniErrorReporting = error_reporting();
error_reporting($puvodniErrorReporting ^ E_NOTICE);
require_once __DIR__ . '/nastaveni-vychozi.php';
error_reporting($puvodniErrorReporting);

if (defined('URL_WEBU') && URL_WEBU) {
    $domain = parse_url(URL_WEBU, PHP_URL_HOST) ?: 'localhost';
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $domain !== 'localhost'
            ? ".$domain"
            : $domain, // Chrome-based browsers consider .localhost cookie domain as invalid (as localhost can not have subdomains)
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'lax',
    ]);
    // rozdilne nazvy pro ruzne instance (ostra, beta...), aby si PHP session cookies nelezly do zeli
    session_name('PS0' . preg_replace('~[^a-z0-9]~i', '0', $domain));
}
