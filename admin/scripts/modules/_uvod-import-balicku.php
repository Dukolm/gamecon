<?php

use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;

if (!post('importBalicku')) {

    $importTemplate = new XTemplate(__DIR__ . '/_uvod-import-balicku.xtpl');
    $importTemplate->assign('baseUrl', URL_ADMIN);

    $importTemplate->parse('import');
    $importTemplate->out('import');

    return;
}

if (!is_readable($_FILES['souborSBalicky']['tmp_name'])) {
    throw new Chyba('Soubor se nepodařilo načíst');
}

$dejPoznamkuOVelkemBalicku = static function (string $balicek, int $rok): string {
    return str_contains($balicek, 'v')
        ? "velký balíček $rok"
        : '';
};

$maNejakyNakupSql = static function (int $rok) {
    return <<<SQL
EXISTS(SELECT 1 FROM shop_nakupy WHERE shop_nakupy.id_uzivatele = uzivatele_hodnoty.id_uzivatele AND shop_nakupy.rok = $rok)
SQL;
};

$zapsanoZmen = 0;

$reader = ReaderEntityFactory::createXLSXReader();

$reader->open($_FILES['souborSBalicky']['tmp_name']);

$reader->getSheetIterator()->rewind();
/** @var \Box\Spout\Reader\SheetInterface $sheet */
$sheet = $reader->getSheetIterator()->current();

$rowIterator = $sheet->getRowIterator();
$rowIterator->rewind();
/** @var \Box\Spout\Common\Entity\Row|null $hlavicka */
$row = $rowIterator->current();
$hlavicka = array_flip($row->toArray());
if (!array_keys_exist(['id_uzivatele', 'balicek'], $hlavicka)) {
    throw new Chyba('Chybný formát souboru - musí mít sloupce id_uzivatele a balicek');
}

$indexIdUzivatele = $hlavicka['id_uzivatele'];
$indexBalicek = $hlavicka['balicek'];

$rowIterator->next();

$chyby = [];
$varovani = [];
$balickyProSql = [];
$poradiRadku = 1;
/** @var \Box\Spout\Common\Entity\Row|null $row */
while ($rowIterator->valid()) {
    $radek = $rowIterator->current()->toArray();
    $poradiRadku++;
    $rowIterator->next();

    if ($radek) {
        $idUzivatele = (int)($radek[$indexIdUzivatele] ?? null);
        if (!$idUzivatele) {
            $chyby[] = sprintf(
                'Na řádku %d chybí ID účastníka očekávaný v %d. sloupci',
                $poradiRadku,
                $indexIdUzivatele + 1,
            );
            continue;
        }

        $uzivatel = Uzivatel::zId($idUzivatele);
        if (!$uzivatel) {
            $chyby[] = sprintf(
                'Účastník s ID %d z řádku %d nexistuje',
                $idUzivatele,
                $poradiRadku,
            );
            continue;
        }
        if (!$uzivatel->gcPrihlasen()) {
            $varovani[] = sprintf(
                'Účastník %s z řádku %d není na letošním Gameconu a byl přeskočen',
                $uzivatel->jmenoNick(),
                $poradiRadku,
            );
            continue;
        }

        $balicek = trim((string)($radek[$indexBalicek] ?? ''));
        $balicekProSql = $dejPoznamkuOVelkemBalicku($balicek, ROK);
        if ($balicekProSql === ''
            && !in_array(
                strtolower(removeDiacritics($balicek)),
                ['', 'balicek'/** exportovaný název bez diakritiky, viz report-infopult-ucastnici-balicky.php */])
        ) {
            $chyby[] = sprintf(
                "U účastníka %s z řádku %d je neznámý zápis balíčku '%s' - očekáváme nic, 'balíček' nebo 'velký balíček'",
                $uzivatel->jmenoNick(),
                $poradiRadku,
                $balicek,
            );
            continue;
        }
        if ($balicekProSql && !$uzivatel->dejShop()->koupilNejakyPredmet()) {
            $varovani[] = sprintf(
                "Účastník %s z řádku %d si nic neobjednal a nemůže proto mít velký balíček",
                $uzivatel->jmenoNick(),
                $poradiRadku,
            );
            continue;
        }
        $balickyProSql[$idUzivatele] = $balicekProSql;
    }
}
$reader->close();

if ($chyby) {
    throw new Chyba('Chybička se vloudila: ' . implode("; ", $chyby));
}

if ($varovani) {
    varovani('Drobnosti: ' . implode(',', $varovani), false);
}

if ($balickyProSql) {
    $temporaryTable = uniqid('import_balicku_tmp_', true);
    dbQuery(<<<SQL
CREATE TEMPORARY TABLE `$temporaryTable`
(id_uzivatele INT UNSIGNED NOT NULL PRIMARY KEY, infopult_poznamka VARCHAR(128) DEFAULT NULL)
SQL
    );

    $queryParams = [];
    $sqlValuesArray = [];
    $paramIndex = 0;
    foreach ($balickyProSql as $idUzivatele => $balicekProSql) {
        $queryParams[] = $idUzivatele;
        $queryParams[] = $balicekProSql;
        $sqlValuesArray[] = '($' . $paramIndex++ . ',$' . $paramIndex++ . ')';
    }

    $sqlValues = implode(",\n", $sqlValuesArray);

    dbQuery(<<<SQL
INSERT INTO `$temporaryTable` (id_uzivatele, infopult_poznamka)
    VALUES
$sqlValues
SQL,
        $queryParams
    );

    $mysqliResult = dbQuery(<<<SQL
UPDATE uzivatele_hodnoty
JOIN `$temporaryTable` ON uzivatele_hodnoty.id_uzivatele = `$temporaryTable`.id_uzivatele
-- pouze pokud má účastník letos nějaký nákup, tak může mít velký balíček
SET uzivatele_hodnoty.infopult_poznamka = IF ({$maNejakyNakupSql(ROK)}, `$temporaryTable`.infopult_poznamka, '')
SQL
    );
    $zapsanoZmen += dbNumRows($mysqliResult);

    dbQuery(<<<SQL
DROP TEMPORARY TABLE `$temporaryTable`
SQL
    );
}

oznameni("Import dokončen. " . ($zapsanoZmen > 0 ? "Změněno $zapsanoZmen záznamů." : 'Beze změny.'));