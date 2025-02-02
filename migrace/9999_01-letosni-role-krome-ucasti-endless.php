<?php
/** @var \Godric\DbMigrations\Migration $this */

use Gamecon\Role\Role;
use Granam\RemoveDiacritics\RemoveDiacritics;

require_once __DIR__ . '/pomocne/rocnik_z_promenne_mysql.php';

// ROLE
$rocnikoveRole     = Role::vsechnyRocnikoveRole(ROCNIK);
$idRocnikovychRoli = array_keys($rocnikoveRole);
$idRoliUcastiSql   = implode(',', $idRocnikovychRoli);
$resultRoli       = $this->q(<<<SQL
    SELECT id_role
    FROM role_seznam
    WHERE id_role IN ($idRoliUcastiSql)
    SQL
);

$chybejiciRocnikoveRole = $rocnikoveRole;
if ($resultRoli) {
    while ($idExistujiciRole = $resultRoli->fetch_column()) {
        unset($chybejiciRocnikoveRole[(int)$idExistujiciRole]);
    }
}

if ($chybejiciRocnikoveRole) {
    $rocnik        = rocnik_z_promenne_mysql();
    $letosniPrefix = Role::prefixRocniku($rocnik);
    foreach ($chybejiciRocnikoveRole as $idChybejiciRocnikoveRole => $nazevChybejiciRocnikoveRole) {
        $result = $this->q(<<<SQL
SELECT rocnik_role FROM role_seznam
WHERE nazev_role = '$nazevChybejiciRocnikoveRole'
SQL
        );
        if ($result) {
            $rocnikRolePredchozihoRocniku = $result->fetch_column();
            $result->close();
            if ($rocnikRolePredchozihoRocniku) {
                $prefixProRoliPredchozihoRocniku = Role::prefixRocniku($rocnikRolePredchozihoRocniku);
                $this->q(<<<SQL
UPDATE role_seznam
SET nazev_role = CONCAT('$prefixProRoliPredchozihoRocniku', ' ', nazev_role)
WHERE nazev_role = '$nazevChybejiciRocnikoveRole'
SQL
                );
            }
        }

        // 'Herman' v "letos" roce 2023 = GC2023_HERMAN
        $kodRole   = RemoveDiacritics::toConstantLikeName($letosniPrefix . ' ' . $nazevChybejiciRocnikoveRole);
        $vyznam    = Role::vyznamPodleKodu($kodRole);
        $rocnikova = Role::TYP_ROCNIKOVA;
        $kategorie = Role::kategoriePodleVyznamu($vyznam);
        $this->q(<<<SQL
INSERT INTO role_seznam
    SET id_role = $idChybejiciRocnikoveRole,
        kod_role = '$kodRole',
        nazev_role = '$nazevChybejiciRocnikoveRole',
        popis_role = '$nazevChybejiciRocnikoveRole',
        rocnik_role = $rocnik,
        typ_role = '$rocnikova',
        vyznam_role = '$vyznam',
        kategorie_role = '$kategorie'
SQL
        );
    }
}
