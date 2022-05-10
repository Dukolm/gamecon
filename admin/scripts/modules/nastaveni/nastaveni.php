<?php

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniAjax;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniHtml;

/**
 * nazev: Nastavení
 * pravo: 110 Administrace - panel Nastavení
 */

/**
 * @var Uzivatel $u
 */

$nastaveni = new SystemoveNastaveni();
$nastaveniHtml = new SystemoveNastaveniHtml($nastaveni);
$nastaveniAjax = new SystemoveNastaveniAjax($nastaveni, $nastaveniHtml, $u);

if ($nastaveniAjax->zpracujPost()) {
    exit;
}

$nastaveniHtml->zobrazHtml();