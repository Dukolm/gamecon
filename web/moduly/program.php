<?php

use \Gamecon\Cas\DateTimeCz;

/** @var Modul $this */
/** @var \Gamecon\XTemplate\XTemplate $t */
/** @var Uzivatel $u */
/** @var url $url */

$this->blackarrowStyl(true);

$dny = [];
for ($den = new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
    $dny[slugify($den->format('l'))] = clone $den;
}

$nastaveni       = [];
$alternativniUrl = null;
$title           = 'Program';
// TODO: přesunout logiku práce s URL za program/ do preactu
if ($url->cast(1) === 'muj') {
    $nastaveni[Program::OSOBNI] = true;
    $title                      = 'Můj program';
} else if (isset($dny[$url->cast(1)])) {
    $nastaveni[Program::DEN] = $dny[$url->cast(1)]->format('z');
    $title                   = 'Program ' . $dny[$url->cast(1)]->format('l');
} else if (!$url->cast(1)) {
    $nastaveni[Program::DEN] = reset($dny)->format('z');
    $alternativniUrl         = 'program/' . slugify(reset($dny)->format('l'));
    $title                   = 'Program ' . reset($dny)->format('l');
} else {
    throw new Nenalezeno();
}

$this->info()->nazev($title);

$program = new Program($u, $nastaveni);
$program->zpracujPost($u);

foreach ($program->cssUrls() as $cssUrl) {
    $this->pridejCssUrl($cssUrl);
}

$zacatekPrvniVlnyOd       = \Gamecon\Cas\DateTimeGamecon::zacatekPrvniVlnyOd();
$zacatekPrvniVlnyZaSekund = $zacatekPrvniVlnyOd->getTimestamp() - time();

$legendaText   = Stranka::zUrl('program-legenda-text')->html();
$jeOrganizator = isset($u) && $u && $u->maPravo(P_ORG_AKTIVIT);

?>

<style>
    /* na stránce programu nedělat sticky menu, aby bylo maximum místa pro progam */
    .menu {
        position: relative;
        /* relative, aby fungoval z-index */
    }
</style>


<?php
function zabalSoubor(string $cestaKSouboru): string
{
    return $cestaKSouboru . '?version=' . md5_file(ADMIN . '/' . $cestaKSouboru);
}
?>

<link rel="stylesheet" href="<?= zabalSoubor('/../web/soubory/ui/style.css') ?>">

<div id="preact-program">Program se načítá ...</div>
<script>
    // Konstanty předáváné do Preactu (env.ts)
    window.GAMECON_KONSTANTY = {
        BASE_PATH_API: "<?= URL_WEBU . "/api/" ?>",
        BASE_PATH_PAGE: "<?= URL_WEBU . "/program/" ?>",
        ROCNIK: <?= ROCNIK ?>,
        LEGENDA: <?= json_encode($legendaText) ?>,
        FORCE_REDUX_DEVTOOLS: <?= defined("FORCE_REDUX_DEVTOOLS") ? "true" : "false" ?>,
    }

    window.gameconPřednačtení =
        <?php
        $res = [];
        if ($u) {
            $res["prihlasen"] = true;
            $res["pohlavi"] = $u->pohlavi();
            $res["koncovkaDlePohlavi"] = $u->koncovkaDlePohlavi();

            if ($u->jeOrganizator()) {
                $res["organizator"] = true;
            }
            if ($u->jeBrigadnik()) {
                $res["brigadnik"] = true;
            }

            $res["gcStav"] = "nepřihlášen";

            if ($u->gcPrihlasen()) {
                $res["gcStav"] = "přihlášen";
            }
            if ($u->gcPritomen()) {
                $res["gcStav"] = "přítomen";
            }
            if ($u->gcOdjel()) {
                $res["gcStav"] = "odjel";
            }
        }
        // TODO: použít jednu logiku stejně jako z API
        echo json_encode(["přihlášenýUživatel" => $res], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>
</script>

<script type="module" src="<?= zabalSoubor('/../web/soubory/ui/bundle.js') ?>"></script>

<div style="height: 70px"></div>

<script type="text/javascript">
    // programNahled(
    //     document.querySelector('.programNahled_obalNahledu'),
    //     document.querySelector('.programNahled_obalProgramu'),
    //     document.querySelectorAll('.programNahled_odkaz'),
    //     document.querySelectorAll('.program form > a'),
    // )

    // zachovejScroll(
    //     document.querySelectorAll('.program form > a'),
    //     document.querySelector('.programPosuv_obal'),
    // )

    // programPosuv(document.querySelector('.programPosuv_obal2'))

    // < ?php if ($zacatekPrvniVlnyZaSekund > 0) {
    // $zacatekPrvniVlnyZaMilisekund = $zacatekPrvniVlnyZaSekund * 1000;
    // if ($zacatekPrvniVlnyZaMilisekund > 0) { ?> /*kdyby to náhodou přeteklo za 2^32 -1 */
    // if (< ?= $zacatekPrvniVlnyZaMilisekund ?> <= 2147483647) {
    //     setTimeout(function () {
    //         location.reload()
    //     }, < ?= $zacatekPrvniVlnyZaMilisekund ?>)
    // }
    // < ?php }
    // } ?>
</script>