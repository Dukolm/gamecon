<?php
require __DIR__ . '/sdilene-hlavicky.php';

$typTricko = Shop::TRICKO;
$typPredmet = Shop::PREDMET;
$typJidlo = Shop::JIDLO;
$rok = ROK;
$idZidliSOrganizatorySql = implode(',', \Gamecon\Zidle::dejIdZidliSOrganizatory());

$poddotazKoupenehoPredmetu = static function (string $klicoveSlovo, int $idTypuPredmetu, int $rok, bool $prilepitRokKNazvu) {
    $rokKNazvu = $prilepitRokKNazvu
        ? " $rok"
        : '';
    return <<<SQL
(SELECT GROUP_CONCAT(pocet_a_nazev SEPARATOR ', ')
    FROM (SELECT CONCAT_WS('× ', COUNT(*), CONCAT(shop_predmety.nazev, '$rokKNazvu')) AS pocet_a_nazev, shop_nakupy.id_uzivatele
        FROM shop_nakupy
            JOIN shop_predmety ON shop_nakupy.id_predmetu = shop_predmety.id_predmetu
            WHERE shop_predmety.id_predmetu = shop_nakupy.id_predmetu
                AND shop_predmety.typ = {$idTypuPredmetu}
                AND shop_nakupy.rok = {$rok}
                AND IF ('$klicoveSlovo' = '', TRUE, shop_predmety.nazev LIKE '%{$klicoveSlovo}%')
                AND shop_nakupy.rok = {$rok}
            GROUP BY shop_nakupy.id_uzivatele, shop_predmety.nazev) AS pocet_a_druh
    WHERE pocet_a_druh.id_uzivatele = uzivatele_hodnoty.id_uzivatele
)
SQL;
};

$maNejakyNakupSql = static function (int $rok, int $typ) {
    return <<<SQL
EXISTS(SELECT 1 FROM shop_nakupy
                JOIN shop_predmety on shop_nakupy.id_predmetu = shop_predmety.id_predmetu
                WHERE shop_nakupy.id_uzivatele = uzivatele_hodnoty.id_uzivatele
                    AND shop_nakupy.rok = $rok
                    AND shop_predmety.typ = $typ
                )
SQL;
};

$prihlasenNaLetosniGc = (int)\Gamecon\Zidle::PRIHLASEN_NA_LETOSNI_GC;

$report = Report::zSql(<<<SQL
SELECT  uzivatele_hodnoty.id_uzivatele,
        uzivatele_hodnoty.login_uzivatele,
        uzivatele_hodnoty.jmeno_uzivatele,
        uzivatele_hodnoty.prijmeni_uzivatele,
        IF (COUNT(zidle_organizatoru.id_zidle) > 0, 'org', '') AS role,
        {$poddotazKoupenehoPredmetu('', $typTricko, $rok, false)} AS tricka,
        {$poddotazKoupenehoPredmetu('kostka', $typPredmet, $rok, true)} AS kostky,
        {$poddotazKoupenehoPredmetu('placka', $typPredmet, $rok, false)} AS placky,
        {$poddotazKoupenehoPredmetu('nicknack', $typPredmet, $rok, false)} AS nicknacky,
        {$poddotazKoupenehoPredmetu('blok', $typPredmet, $rok, false)} AS bloky,
        {$poddotazKoupenehoPredmetu('ponožky', $typPredmet, $rok, false)} AS ponozky,
        IF ({$poddotazKoupenehoPredmetu('', $typJidlo, $rok, false)} IS NULL, '', 'stravenky') AS stravenky,
        IF (
            {$maNejakyNakupSql($rok, $typPredmet)},
            IF (uzivatele_hodnoty.infopult_poznamka = 'velký balíček $rok', 'velký balíček', 'balíček'),
            ''
        ) AS balicek
FROM uzivatele_hodnoty
JOIN r_uzivatele_zidle
    ON uzivatele_hodnoty.id_uzivatele = r_uzivatele_zidle.id_uzivatele
LEFT JOIN r_uzivatele_zidle AS zidle_organizatoru
    ON uzivatele_hodnoty.id_uzivatele = zidle_organizatoru.id_uzivatele AND zidle_organizatoru.id_zidle IN ($idZidliSOrganizatorySql)
WHERE r_uzivatele_zidle.id_zidle = {$prihlasenNaLetosniGc}
GROUP BY uzivatele_hodnoty.id_uzivatele
SQL
);

$report->tFormat(get('format'));