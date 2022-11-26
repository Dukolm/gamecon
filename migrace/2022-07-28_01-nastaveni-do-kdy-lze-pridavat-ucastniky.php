<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, aktivni, nazev, popis, datovy_typ, skupina, poradi)
    VALUES
        ('UCASTNIKY_LZE_PRIDAVAT_X_DNI_PO_GC_U_NEUZAVRENE_PREZENCE', '30', 1, 'Do kolika dní po GC lze přidat účastníka', 'Kolik dní po konci GC lze ještě přidávat účastníky na Neuzavřenou aktivitu', 'integer', 'Časy',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi))
SQL
);
