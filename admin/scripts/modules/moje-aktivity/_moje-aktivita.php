<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceAjax;

/**
 * Online formulář na zadávání příchozích a nedorazivších na aktivity
 *
 * nazev: Moje aktivita
 * pravo: 109
 */

/**
 * @var Uzivatel $u
 * @var bool $testovani
 */

$aktivita = Aktivita::zId(get('id'));
$problem = require __DIR__ . '/_moje-aktivita-problem.php';

if ($problem) {
    return;
}

global $BEZ_DEKORACE;
$BEZ_DEKORACE = true; // pokud nedoslo k chybě, tak nechceme levé menu, ale pouze nový čistý layout pro prezenci, viz admin/index.php

$onlinePrezenceHtml = new OnlinePrezenceHtml();
$onlinePrezenceAjax = new OnlinePrezenceAjax($onlinePrezenceHtml);

if ($onlinePrezenceAjax->odbavAjax()) {
    return;
}

echo $onlinePrezenceHtml->dejHtmlOnlinePrezence([$aktivita]);
