<?php
require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Role\Zidle;

$ucast = Zidle::TYP_UCAST;

$report = Report::zSql(<<<SQL
SELECT
  uzivatele_hodnoty.id_uzivatele,
  uzivatele_hodnoty.jmeno_uzivatele,
  uzivatele_hodnoty.prijmeni_uzivatele,
  uzivatele_hodnoty.mesto_uzivatele,
  uzivatele_hodnoty.ulice_a_cp_uzivatele,
  uzivatele_hodnoty.psc_uzivatele,
  uzivatele_hodnoty.email1_uzivatele,
  uzivatele_hodnoty.telefon_uzivatele,
  uzivatele_hodnoty.zustatek,
  ucast.roky AS účast,
  kladny_pohyb.datum AS "poslední kladný pohyb na účtu",
  zaporny_pohyb.datum AS "poslední záporný pohyb na účtu"
FROM uzivatele_hodnoty
LEFT JOIN (
  SELECT
    id_uzivatele,
    GROUP_CONCAT(rok ORDER BY rok ASC) AS roky,
    COUNT(r_uzivatele_zidle.id_zidle) AS pocet
    FROM r_zidle_soupis
    JOIN r_uzivatele_zidle ON r_zidle_soupis.id_zidle = r_uzivatele_zidle.id_zidle
  WHERE r_zidle_soupis.typ = '$ucast'
  GROUP BY id_uzivatele
) AS ucast ON ucast.id_uzivatele = uzivatele_hodnoty.id_uzivatele
LEFT JOIN ( -- poslední kladný pohyb na účtu
  SELECT
    id_uzivatele,
    MAX(provedeno) AS datum
  FROM platby
  WHERE castka > 0
  GROUP BY id_uzivatele
) AS kladny_pohyb ON kladny_pohyb.id_uzivatele = uzivatele_hodnoty.id_uzivatele
LEFT JOIN ( -- poslední záporný pohyb na účtu
  SELECT
    id_uzivatele,
    MAX(provedeno) AS datum
  FROM platby
  WHERE castka < 0
  GROUP BY id_uzivatele
) AS zaporny_pohyb ON zaporny_pohyb.id_uzivatele = uzivatele_hodnoty.id_uzivatele
SQL
);
$report->tFormat(get('format'));
