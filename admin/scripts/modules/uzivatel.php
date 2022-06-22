<?php

/**
 * Stránka k editaci ubytovacích informací
 *
 * nazev: Uživatel
 * pravo: 101
 */

use \Gamecon\Cas\DateTimeCz;
use \Gamecon\Cas\DateTimeGamecon;

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 */


$nastaveni = ['ubytovaniBezZamku' => true, 'jidloBezZamku' => true];
$shop = $uPracovni ? new Shop($uPracovni, $nastaveni) : null;

if (post('pokojeImport')) {
    $f = fopen($_FILES['pokojeSoubor']['tmp_name'], 'rb');
    if (!$f) throw new Exception('Soubor se nepodařilo načíst');

    $hlavicka = array_flip(fgetcsv($f, 512, ";"));
    if (!array_key_exists('id_uzivatele', $hlavicka)) throw new Exception('Nepodařilo se zpracovat soubor');
    $uid = $hlavicka['id_uzivatele'];
    $od = $hlavicka['prvni_noc'];
    $do = $hlavicka['posledni_noc'];
    $pokoj = $hlavicka['pokoj'];

    dbDelete('ubytovani', ['rok' => ROK]);

    while ($r = fgetcsv($f, 512, ";")) {
        if ($r[$pokoj]) {
            for ($den = $r[$od]; $den <= $r[$do]; $den++) {
                dbInsert('ubytovani', [
                    'id_uzivatele' => $r[$uid],
                    'den' => $den,
                    'pokoj' => $r[$pokoj],
                    'rok' => ROK,
                ]);
            }
        }
    }

    oznameni('Import dokončen');
}

if (post('pridelitPokoj')) {
    Pokoj::ubytujNaCislo(Uzivatel::zId(post('uid')), post('pokoj'));
    oznameni('Pokoj přidělen');
}

if (post('zpracujUbytovani')) {
    $shop->zpracujUbytovani();
    oznameni('Ubytování uloženo');
}

if (post('zpracujJidlo')) {
    $shop->zpracujJidlo();
    oznameni('Jídlo uloženo');
}

if (post('zmenitUdaj') && $uPracovni) {
    $udaje = post('udaj');
    if ($udaje['op'] ?? null) {
        $uPracovni->cisloOp($udaje['op']);
        unset($udaje['op']);
    }
    try {
        dbUpdate('uzivatele_hodnoty', $udaje, ['id_uzivatele' => $uPracovni->id()]);
    } catch (DbDuplicateEntryException $e) {
        if ($e->key() === 'email1_uzivatele') {
            chyba('Uživatel se stejným e-mailem již existuje.');
        } else if ($e->key() === 'login_uzivatele') {
            chyba('Uživatel se stejným e-mailem již existuje.');
        } else {
            chyba('Uživatel se stejným údajem již existuje.');
        }
    } catch (Exception $e) {
        $vyjimkovac->zaloguj($e);
        chyba('Došlo k neočekávané chybě.');
    }

    $uPracovni->otoc();
    back();
}

$x = new XTemplate('uzivatel.xtpl');

$pokoj = Pokoj::zCisla(get('pokoj'));
$ubytovani = $pokoj ? $pokoj->ubytovani() : [];
if (get('pokoj') && !$pokoj) throw new Chyba('pokoj ' . get('pokoj') . ' neexistuje nebo je prázdný');
$x->assign([
    'uid' => $uPracovni ? $uPracovni->id() : '',
    'pokoj' => get('pokoj'),
    'ubytovani' => array_uprint($ubytovani, function ($e) {
        $ne = $e->gcPritomen() ? '' : 'ne';
        $color = $ne ? '#f00' : '#0a0';
        $a = $e->koncA();
        return $e->jmenoNick() . " (<span style=\"color:$color\">{$ne}dorazil$a</span>)";
    }, '<br>'),
]);

if ($uPracovni && $uPracovni->gcPrihlasen()) {
    $x->assign('shop', $shop);
    $x->parse('uzivatel.ubytovani');
    $x->parse('uzivatel.jidlo');
}

if (!$uPracovni) {
    $x->assign('status', '<div class="warning">Uživatel nevybrán</div>');
} elseif (!$uPracovni->gcPrihlasen()) {
    $x->assign('status', '<div class="error">Uživatel není přihlášen na GC</div>');
}


// form s osobními údaji
if ($uPracovni) {
    $udaje = [
        'login_uzivatele' => 'Přezdívka',
        'jmeno_uzivatele' => 'Jméno',
        'prijmeni_uzivatele' => 'Příjmení',
        'pohlavi' => 'Pohlaví',
        'ulice_a_cp_uzivatele' => 'Ulice',
        'mesto_uzivatele' => 'Město',
        'psc_uzivatele' => 'PSČ',
        'telefon_uzivatele' => 'Telefon',
        'datum_narozeni' => 'Narozen' . $uPracovni->koncA(),
        'email1_uzivatele' => 'E-mail',
        // 'op'                    =>          'Číslo OP',
    ];
    $r = dbOneLine('SELECT ' . implode(',', array_keys($udaje)) . ' FROM uzivatele_hodnoty WHERE id_uzivatele = ' . $uPracovni->id());
    $datumNarozeni = new DateTimeImmutable($r['datum_narozeni']);

    foreach ($udaje as $sloupec => $nazev) {
        $hodnota = $r[$sloupec];
        if ($sloupec === 'op') {
            $hodnota = $uPracovni->cisloOp(); // desifruj cislo obcanskeho prukazu
        }
        $zobrazenaHodnota = $hodnota;
        $vstupniHodnota = $hodnota;
        $vyber = [];
        $popisek = '';
        if ($sloupec === 'pohlavi') {
            $vyber = ['f' => 'žena', 'm' => 'muž'];
            $zobrazenaHodnota = $vyber[$r['pohlavi']] ?? '';
        }
        if ($sloupec === 'datum_narozeni') {
            $popisek = sprintf('Věk na začátku Gameconu %d let', vekNaZacatkuLetosnihoGameconu($datumNarozeni));
        }
        $x->assign([
            'nazev' => $nazev,
            'sloupec' => $sloupec,
            'vstupniHodnota' => $vstupniHodnota,
            'zobrazenaHodnota' => $zobrazenaHodnota,
            'vyber' => $vyber,
            'popisek' => $popisek,
        ]);
        if ($popisek) {
            $x->parse('uzivatel.udaje.udaj.nazevSPopiskem');
        } else {
            $x->parse('uzivatel.udaje.udaj.nazevBezPopisku');
        }
        if ($sloupec === 'pohlavi') {
            foreach ($vyber as $optionValue => $optionText) {
                $x->assign([
                    'optionValue' => $optionValue,
                    'optionText' => $optionText,
                    'optionSelected' => $vstupniHodnota === $optionValue
                        ? 'selected'
                        : '',
                ]);
                $x->parse('uzivatel.udaje.udaj.select.option');
            }
            $x->parse('uzivatel.udaje.udaj.select');
        } else {
            $x->parse('uzivatel.udaje.udaj.input');
        }
        $x->parse('uzivatel.udaje.udaj');
    }
    $x->parse('uzivatel.udaje');
}

$x->parse('uzivatel');
$x->out('uzivatel');