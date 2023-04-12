<?php
/**
 * 2022 @link 2022-05-15_1-mistnosti-2022.php
 */

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
TRUNCATE TABLE akce_lokace;

INSERT INTO akce_lokace(id_lokace, nazev, dvere, poznamka, poradi, rok)
VALUES
('1','RPG 1 – bunkr','Budova C, suterén, bunkr C','Bunkr, dveře vzadu vpravo, repráky','1','0'),
('2','RPG 2 - repráky','Budova C, dveře č. 1','Pokoj 3L, repráky','2','0'),
('3','RPG 3 - 2L pokoj','Budova C, dveře č. 38','Pokoj 2L','3','0'),
('4','RPG 4 – repráky','Budova C, dveře č. 2','Pokoj 3L, repráky','4','0'),
('5','RPG 5 ','Budova C, dveře č. 37','Pokoj 3L','5','0'),
('6','RPG 6','Budova C, dveře č. 36','Pokoj 3L','6','0'),
('7','RPG 7','Budova C, dveře č. 35','Pokoj 3L','7','0'),
('8','RPG 8','Budova C, dveře č. 34','Pokoj 3L','8','0'),
('9','RPG 9','Budova C, dveře č. 33','Pokoj 3L','9','0'),
('10','RPG 10','Budova C, dveře č. 32','Pokoj 3L','10','0'),
('11','RPG 11','Budova C, dveře č. 22','Pokoj 3L','11','0'),
('12','RPG 12','Budova C, dveře č. 21','Pokoj 3L','12','0'),
('13','RPG 13','Budova C, dveře č. 20 ','Pokoj 3L','13','0'),
('14','RPG 14','Budova C, dveře č. 19','Pokoj 3L','14','0'),
('15','RPG 15','Budova C, dveře č. 18','Pokoj 3L','15','0'),
('16','RPG 16','Budova C, dveře č. 17','Pokoj 3L','16','0'),
('17','LKD 1 ','Budova C, dveře č. 100','Pokoj 3L','17','0'),
('18','LKD 2','Budova C, dveře č. 135','Pokoj 3L','18','0'),
('81','LKD 3','Budova C, dveře č. 134','Pokoj 3L','19','0'),
('80','LKD 4','Budova C, dveře č. 102','Pokoj 3L','20','0'),
('19','LKD 5','Budova C, dveře č. 133','Pokoj 3L','21','0'),
('20','LKD 6','Budova C, dveře č. 132','Pokoj 3L','22','0'),
('114','LKD 7','Budova C, dveře č. 131','Pokoj 3L','23','0'),
('63','LKD 8','Budova C, dveře č. 103','Klubovna','24','0'),
('21','mDrD 1','Waldorf ','Třída Waldorf','25','0'),
('22','mDrD 2','Waldorf ','Třída Waldorf','26','0'),
('23','mDrD 3','Waldorf ','Třída Waldorf','27','0'),
('24','mDrD 4','Waldorf ','Třída Waldorf','28','0'),
('25','mDrD 5','Waldorf ','Třída Waldorf','29','0'),
('26','mDrD 6','Waldorf ','Třída Waldorf','30','0'),
('27','mDrD 7','Waldorf ','Třída Waldorf','31','0'),
('28','mDrD 8','Waldorf ','Třída Waldorf','32','0'),
('29','mDrD 9','Waldorf ','Třída Waldorf','33','0'),
('30','mDrD 10','Waldorf ','Třída Waldorf','34','0'),
('31','EPIC 1 - prosklená 0p','Budova C, dveře č. 11','Prosklená klubovna','35','0'),
('32','EPIC 2 - pokoj 0p','Budova C, dveře č. 12','Pokoj 3L','36','0'),
('33','EPIC 3 - tv místnost 0p','Budova C, dveře č. 13','TV místnost na C','37','0'),
('34','EPIC 4 – pokoj 0p','Budova C, dveře č. 15','Pokoj 3L','38','0'),
('35','EPIC 5 – pokoj 0p','Budova C, dveře č. 16','Pokoj 3L','39','0'),
('84','EPIC 6 – pokoj 1p','Budova C, dveře č. 121','Pokoj 3L','40','0'),
('37','EPIC 7 – pokoj 1p','Budova C, dveře č. 120','Pokoj 3L','41','0'),
('83','EPIC 8 - prosklená 1p','Budova C, dveře č. 110','Prosklená klubovna','42','0'),
('82','EPIC 9 – tv mistnost 1p','Budova C, dveře č. 111','TV místnost na C','43','0'),
('103','EPIC 10 – prosklená 2p','Budova C, dveře č. 210','Prosklená klubovna','44','0'),
('39','Larp 1 - 1L pokoj 3p.','Budova C, dveře č. 308','Pokoj 1L','45','0'),
('40','Larp 2 - dvojpokoj 3p.','Budova C, dveře č. 310+311','Dvojmístnost','46','0'),
('42','Larp 3 - bunkr B','Budova C, suterén, bunkr B','Dveře vzadu vlevo','47','0'),
('43','Larp 4 - DDM sál','DDM, přízemí, velký sál','','48','0'),
('115','Larp 5 - DDM knihovna','','','49','0'),
('44','Larp 6 - DDM 42, malá','DDM, 1. patro, dveře č. 42','','50','0'),
('45','Larp 7 - DDM 36, třída','DDM, 1. patro, dveře č. 36','','51','0'),
('46','Larp 8 - DDM, hudebna','DDM, 2. patro, dveře č. 12','','52','0'),
('47','Larp 9 - Sborovna','Budova A, dveře č. 18','Sborovna na A','53','0'),
('48','Larp 10 - knihovna','Budova B, suterén','Po schodech dolů vpravo, dveře vpravo','54','0'),
('49','Larp 11 - W. družina','Waldorf, družina','Samostatná budova','55','0'),
('50','Larp 12 - W. zahrada','','Zahrada Waldorf družiny','56','0'),
('66','Desk 1','KD, 1. patro, předsálí','','57','0'),
('61','Desk 2','KD, 1. patro, prosklený sál','Prosklený sál na konci chodby','58','0'),
('62','Desk 3 ','KD, 1. patro, druhá vpravo','','59','0'),
('69','Desk 4','KD, 1. patro, první vpravo','','60','0'),
('51','Desk 5','KD, 1. patro vlevo','','61','0'),
('59','Desk 6','KD, 1. patro, taneční sál','','62','0'),
('60','Desk 7','KD, 1. patro, pódium v sále','','63','0'),
('36','WarG 1 - C1','Budova C, dveře č. 203','Velká klubovna na C','64','0'),
('38','WarG 2 - C2','Budova C, dveře č. 303','Velká klubovna na C','65','0'),
('52','Bonus 1 - klubovna','Budova C, dveře č. 3','Velká klubovna na C','66','0'),
('53','Bonus 2 - bunkr I','Budova C, suterén, bunkr I','Tři propojené kumbály, napravo','67','0'),
('56','Bonus 3 - zahrada C','Budova C, zahrada','Hřiště','68','0'),
('57','Bonus 4 - venku na GC','','','69','0'),
('58','Bonus 5 - mimo GC','','','70','0'),
('41','Přednáškovka - Klub','Budova C, suterén, hudební klub','','71','0'),
('65','Prog 1 - Kino','','','72','0'),
('67','Prog 2 - předsálí down','KD, přízemí, předsálí','','73','0'),
('68','Prog 3 - Bunkr D+E','Budova C, suterén','Vstup přes bunkr C','74','0'),
('70','Prog 4 - rezerva 1','Budova C, dveře č. 130','Pokoj 3L','75','0'),
('71','Prog 5 – rezerva 2','Budova C, dveře č. 129','Pokoj 3L','76','0'),
('72','Prog 6 - jídelna','Budova C mezipatro pod přízemím vzadu','','77','0'),
('73','Prog 7 - mimo GC','','','78','0'),
('74','Záz 1 - infopult','KD, přízemí u šaten','','79','0'),
('75','Záz 2 - štáb','Budova C, přízemí, dveře 28','','80','0'),
('76','Záz 3 - sklad IT','Budova C, dveře č. 30','Pokoj 3L','81','0'),
('77','Záz 4 - snídárna','Budova B, dveře č. 27','Snídárna na B','82','0'),
('78','Záz 5 - ostatní','','','83','0'),
('54','Záz 6 - Zahrada A','Budova A, zahrada','Nějaké stromy atp., 2022 - blokováno','84','0'),
('55','Záz 7 - Zahrada B','Budova B, zahrada','Volnější prostor, blíž bráně, venkovní snídaně','85','0'),
('64','Záz 8 - Zahrada KD','Atrium za KD, vchod kolem infopultu','','86','0'),
('86','F - KDD Vstup','','','88','0'),
('87','F - KDD DH-vstup','','','89','0'),
('88','F - KDD DH-bar','','','90','0'),
('89','F - KDD L1','','','91','0'),
('90','F - KDD L2','','','92','0'),
('91','F - KDD L3','','','93','0'),
('92','F - KDD L4','','','94','0'),
('93','F - KDD L5','','','95','0'),
('94','F - KDD P1','','','96','0'),
('95','F - KDD P2','','','97','0'),
('96','F - KDD P3','','','98','0'),
('97','F - KDD P4','','','99','0'),
('98','F - KDD DH-A','','','100','0'),
('99','F – KDD SM 1','','','101','0'),
('100','F – KDD SM 2','','','102','0'),
('101','F – KDD SM 3','','','103','0'),
('102','F – KDD SM 4','','','104','0'),
('85','F – KDD FH1','','','105','0'),
('117','F – KDD FH2','','','106','0'),
('79','F – KDD FH3','','','107','0'),
(NULL,'F – KDD F1','','','108','0');
SQL
);

