<?php

/**
 * Úvodní stránka sloužící pro infopult a další účely. Zajišťuje registraci na
 * DrD, Trojboj, Gamecon, Placení aj.
 *
 * nazev: Infopult
 * pravo: 100
 */

use \Gamecon\Cas\DateTimeCz;
use \Gamecon\Cas\DateTimeGamecon;

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 */

require_once __DIR__ . '/../funkce.php';
require_once __DIR__ . '/../konstanty.php';

$nastaveni = ['ubytovaniBezZamku' => true, 'jidloBezZamku' => true];
$shop = $uPracovni ? new Shop($uPracovni, $nastaveni) : null;

include __DIR__ . '/_infopult_ovladac.php';

$x = new XTemplate('infopult.xtpl');
xtemplateAssignZakladniPromenne($x, $uPracovni);
$x->assign('prihlasBtnAttr', "disabled");
$x->assign('datMaterialyBtnAttr', "disabled");
$x->assign('gcOdjedBtnAttr', "disabled");
$x->assign('odhlasBtnAttr', "disabled");

// ubytovani
$pokojVypis = Pokoj::zCisla(get('pokoj'));
$ubytovaniVypis = $pokojVypis ? $pokojVypis->ubytovani() : [];

/**
 * @param \Uzivatel[] $spolubydlici
 */
function spolubydliciTisk($spolubydlici)
{
    return array_uprint($spolubydlici, static function (Uzivatel $e) {
        return "<li> {$e->jmenoNick()} ({$e->id()}) {$e->telefon()} </li>";
    });
}

if ($uPracovni) {
    if (!$uPracovni->gcPrihlasen()) {
        if (REG_GC) {
            $x->assign('prihlasBtnAttr', "");
        } else {
            $x->parse('uvod.neprihlasen.nelze');
        }
        $x->parse('uvod.neprihlasen');
    }
    /** @var \Uzivatel $up */
    $up = $uPracovni;
    $a = $up->koncovkaDlePohlavi();
    $pokoj = Pokoj::zUzivatele($up);
    $spolubydlici = $pokoj
        ? $pokoj->ubytovani()
        : [];
    $x->assign([
        'stav' => ($up->finance()->stav() < 0 ? $err : $ok) . ' ' . $up->finance()->stavHr(),
        'stavStyle' => ($up->finance()->stav() < 0 ? '"color: #f22; font-weight: bolder;"' : ""),
        'prehled' => $up->finance()->prehledHtml(),
        'slevyAktivity' => ($akt = $up->finance()->slevyAktivity()) ?
            '<li>' . implode('<li>', $akt) :
            '(žádné)',
        'slevyVse' => ($vse = $up->finance()->slevyVse()) ?
            '<li>' . implode('<li>', $vse) :
            '(žádné)',
        'id' => $up->id(),
        'pokoj' => $pokoj ? $pokoj->cislo() : '(nepřidělen)',
        'spolubydlici' => spolubydliciTisk($spolubydlici),
        'aOrganizator' => $u->koncovkaDlePohlavi(),
        'org' => $u->jmenoNick(),
        'shop' => $up->dejShop(),
        'poznamka' => $up->poznamka(),
        'up' => $up,

        'pokojVypis' => $pokoj ? $pokoj->cislo() : "",
        'ubytovani' => spolubydliciTisk($spolubydlici),
    ]);

    if (get('pokoj')) {
        $x->assign('pokojVypis', get('pokoj'));
        if ($pokojVypis) {
            $x->assign('ubytovani', array_uprint($ubytovaniVypis, function ($e) {
                $ne = $e->gcPritomen() ? '' : 'ne';
                $color = $ne ? '#f00' : '#0a0';
                $a = $e->koncA();
                return $e->jmenoNick() . " (<span style=\"color:$color\">{$ne}dorazil$a</span>)";
            }, '<br>'));
        } else
            throw new Chyba('pokoj ' . get('pokoj') . ' neexistuje nebo je prázdný');
    }


    if ($up->finance()->stav() < 0 && !$up->gcPritomen()) {
        $x->parse('uvod.upoMaterialy');
    }
    if (!$up->gcPrihlasen()) {
    } elseif (!$up->gcPritomen()) {
        $x->assign('datMaterialyBtnAttr', "");
    } elseif (!$up->gcOdjel()) {
        $x->assign('gcOdjedBtnAttr', "");
    } else {
    }
    if ($up->gcPrihlasen() && !$up->gcPritomen()) {
        // $x->parse('uvod.gcOdhlas');
    }
    $r = dbOneLine('SELECT datum_narozeni, potvrzeni_zakonneho_zastupce FROM uzivatele_hodnoty WHERE id_uzivatele = ' . $uPracovni->id());
    $datumNarozeni = new DateTimeImmutable($r['datum_narozeni']);
    $potvrzeniOd = $r['potvrzeni_zakonneho_zastupce']
        ? new DateTimeImmutable($r['potvrzeni_zakonneho_zastupce'])
        : null;
    $potrebujePotvrzeniKvuliVeku = potrebujePotvrzeni($datumNarozeni);
    $mameLetosniPotvrzeniKvuliVeku = $potvrzeniOd && $potvrzeniOd->format('y') === date('y');

    if (!$potrebujePotvrzeniKvuliVeku) {
        $x->assign("potvrzeniAttr", "checked disabled");
        $x->assign("potvrzeniText", $ok . " nepotřebuje potvrzení od rodičů");
    } else if ($mameLetosniPotvrzeniKvuliVeku) {
        $x->assign("potvrzeniAttr", "checked value=\"\"");
        $x->assign("potvrzeniText", $ok . " má potvrzení od rodičů");
    } else {
        $x->assign("potvrzeniText", $err . " chybí potvrzení od rodičů!");
    }

    if (VYZADOVANO_COVID_POTVRZENI) {
        $mameNahranyLetosniDokladProtiCovidu = $up->maNahranyDokladProtiCoviduProRok((int)date('Y'));
        $mameOverenePotvrzeniProtiCoviduProRok = $up->maOverenePotvrzeniProtiCoviduProRok((int)date('Y'));
        if (!$mameNahranyLetosniDokladProtiCovidu && !$mameOverenePotvrzeniProtiCoviduProRok) {
            /* muze byt overeno rucne bez nahraneho dokladu */
            $x->assign("covidPotvrzeniText", $err . " požádej o doplnění");
        } elseif (!$mameNahranyLetosniDokladProtiCovidu) {
            /* potvrzeno rucne na infopultu, bez nahraneho dokladu */
            $x->assign("covidPotvrzeniAttr", "checked value=\"\"");
            $x->assign("covidPotvrzeniText", $ok . " ověřeno bez dokladu");
        } else {
            $datumNahraniPotvrzeniProtiCovid = (new DateTimeCz($up->potvrzeniProtiCoviduPridanoKdy()->format(DATE_ATOM)))->relativni();
            $x->assign('covidPotvrzeniOdkazAttr', "href=\n" . $up->urlNaPotvrzeniProtiCoviduProAdmin() . "\"");
            if ($mameOverenePotvrzeniProtiCoviduProRok) {
                $x->assign("covidPotvrzeniAttr", "checked value=\"\"");
                $x->assign("covidPotvrzeniText", $ok . " ověřeno dokladem $datumNahraniPotvrzeniProtiCovid");
            } else {
                $x->assign("covidPotvrzeniText", $warn . " neověřený doklad $datumNahraniPotvrzeniProtiCovid");
            }
        }
        $x->parse('uvod.uzivatel.covidSekce');
    }

    $x->assign("telefon", formatujTelCislo($up->telefon()));

    if ($up->gcPrihlasen()) {
        $x->parse('uvod.uzivatel.ubytovani');
    }

    if (GC_BEZI) {
        $zpravyProPotvrzeniZruseniPrace = [];
        if (!$up->gcPritomen()) {
            $zpravyProPotvrzeniZruseniPrace[] = 'nedostal materiály';
        }
        if ($up->finance()->stav() < 0) {
            $zpravyProPotvrzeniZruseniPrace[] = 'má záporný zůstatek';
        }
        if ($potrebujePotvrzeniKvuliVeku && !$mameLetosniPotvrzeniKvuliVeku) {
            $zpravyProPotvrzeniZruseniPrace[] = 'nemá potvrzení od rodičů';
        }
        foreach ($zpravyProPotvrzeniZruseniPrace as $zpravaProPotvrzeniZruseniPrace) {
            $x->assign([
                'zpravaProPotvrzeniZruseniPrace' => "Uživatel {$zpravaProPotvrzeniZruseniPrace}. Přesto ukončit práci s uživatelem?",
            ]);
            $x->parse('uvod.potvrditZruseniPrace');
        }
    }

    if ($u && $u->isSuperAdmin()) {
        $x->parse('uvod.uzivatel.idFioPohybu');
    }

    $x->parse('uvod.uzivatel');
} else {
    $x->parse('uvod.neUzivatel');
    $x->parse('uvod.prodejAnonymni');
}

// načtení předmětů a form s rychloprodejem předmětů, fixme
$o = dbQuery('
  SELECT
    CONCAT(nazev," ",model_rok) as nazev,
    kusu_vyrobeno-count(n.id_predmetu) as zbyva,
    p.id_predmetu,
    ROUND(p.cena_aktualni) as cena
  FROM shop_predmety p
  LEFT JOIN shop_nakupy n ON(n.id_predmetu=p.id_predmetu)
  WHERE p.stav > 0
  GROUP BY p.id_predmetu
  ORDER BY model_rok DESC, nazev');
$moznosti = '<option value="">(vyber)</option>';
while ($r = mysqli_fetch_assoc($o)) {
    $zbyva = $r['zbyva'] === null ? '&infin;' : $r['zbyva'];
    $moznosti .= '<option value="' . $r['id_predmetu'] . '"' . ($r['zbyva'] > 0 || $r['zbyva'] === null ? '' : ' disabled') . '>' . $r['nazev'] . ' (' . $zbyva . ') ' . $r['cena'] . '&thinsp;Kč</option>';
}
$x->assign('predmety', $moznosti);


// rychloregistrace
if (!$uPracovni) { // nechceme zobrazovat rychloregistraci (zakladani uctu), kdyz mame vybraneho uzivatele pro praci
    $x->parse('uvod.rychloregistrace');
    if (REG_GC) {
        $x->parse('uvod.rychloregistrace.prihlasitNaGc');
    }
}

$x->parse('uvod');
$x->out('uvod');