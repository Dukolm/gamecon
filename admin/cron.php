<?php

use Gamecon\Aktivita\Aktivita;

/**
 * Skript který je hostingem automaticky spouštěn jednou za hodinu. Standardní
 * limit vykonání je 90 sekund jako jinde na webu.
 */

require __DIR__ . '/../nastaveni/zavadec.php';

// TODO nutný hack před zmergeování zavaděče mezi redesignem a masterem
// tato proměnná je nastavena zavaděčem a zde upravíme zobrazení výjimek
/** @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac */
$vyjimkovac->zobrazeni(\Gamecon\Vyjimkovac\Vyjimkovac::PLAIN);

//////////////////////////////// pomocné funkce ////////////////////////////////

/**
 * Výstup do logu
 */
function logs($s) {
    echo date('Y-m-d H:i:s ') . $s . "\n";
}

if (defined('TESTING') && TESTING && !defined('MAILY_DO_SOUBORU')) {
    define('MAILY_DO_SOUBORU', true);
}

/////////////////////////////////// příprava ///////////////////////////////////

// otestovat, že je skript volán s heslem a sprvánou url
if (HTTPS_ONLY) httpsOnly();
if (!defined('CRON_KEY') || get('key') !== CRON_KEY) {
    die('špatný klíč');
}

if (!defined('TESTING') || !TESTING) {
// otevřít log soubor pro zápis a přesměrovat do něj výstup
    $logdir = SPEC . '/logs';
    $logfile = 'cron-' . date('Y-m') . '.log';
    if (!is_dir($logdir) && !@mkdir($logdir) && !is_dir($logdir)) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', $logdir));
    }
    $logDescriptor = fopen($logdir . '/' . $logfile, 'ab');
    ob_start(static function ($string) use ($logDescriptor) {
        fwrite($logDescriptor, $string . "\n");
        fclose($logDescriptor);
    });
}

// zapnout zobrazení chyb
ini_set('display_errors', true); // zobrazovat chyby obecně
ini_set('error_reporting', E_ALL ^ E_STRICT); // vybrat typy chyb k zobrazení
ini_set('html_errors', false); // chyby zobrazovat jako plaintext

/////////////////////////////////// cron kód ///////////////////////////////////

logs('Začínám provádět cron script.');

if (defined('FIO_TOKEN') && FIO_TOKEN !== '') {
    logs('Zpracovávám nové platby přes Fio API.');
    $platby = Platby::nactiZRozmezi(new DateTimeImmutable('2019-06-20'), new DateTimeImmutable('2019-07-01'));
    foreach ($platby as $p) {
        logs('platba ' . $p->id() . ' (' . $p->castka() . 'Kč, VS: ' . $p->vs() . ($p->zprava() ? ', zpráva: ' . $p->zprava() : '') . ')');
    }
    if (!$platby) logs('Žádné zaúčtovatelné platby.');
} else {
    logs('FIO_TOKEN není definován, přeskakuji nové platby.');
}

logs('Odemykám zamčené aktivity.');
$i = Aktivita::odemciHromadne();
logs("odemčeno $i aktivit.");

logs('Zamykám už běžící, dosud nezamčené aktivity...');
$idsZamcenmych = Aktivita::zamciUzBeziciOd(new DateTimeImmutable('-45 minutes'));
$pocetZamcenych = count($idsZamcenmych);
logs("zamčeno $pocetZamcenych aktivit.");

if (date('G') == 5) { // 5 hodin ráno
    logs('Zálohuji databázi na FTP.');

    if (!defined('FTP_ZALOHA_DB')) {
        throw new Exception('Není definována konstanta s adresou serveru pro zálohování.');
    }

    $backup = new Godric\DbBackup\DbBackup([
        'sourceDb' => [
            'server' => DB_SERV,
            'user' => DB_USER,
            'password' => DB_PASS,
            'database' => DB_NAME,
        ],
        'targetFtp' => FTP_ZALOHA_DB,
    ]);
    $backup->run();

    logs('Záloha dokončena.');
}

logs('Cron dokončen.');
