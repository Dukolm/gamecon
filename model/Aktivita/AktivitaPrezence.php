<?php

namespace Gamecon\Aktivita;

use Gamecon\Cas\DateTimeCz;

/**
 * Prezenční listina aktivity.
 */
class AktivitaPrezence
{

    /** @var Aktivita */
    private $aktivita;
    /** @var void|\Uzivatel[] */
    private $seznamSledujicich;

    public function __construct(Aktivita $aktivita) {
        $this->aktivita = $aktivita;
    }

    /**
     * Uloží prezenci do databáze.
     * @param \Uzivatel[] $dorazili uživatelé, kteří se nakonec aktivity zúčastnili
     */
    public function uloz(array $dorazili) {
        $doraziliIds = []; // id všech co dorazili (kvůli kontrole přítomnosti)

        // TODO kontrola, jestli prezence smí být uložena (např. jestli už nebyla uložena dřív)

        foreach ($dorazili as $dorazil) {
            $this->ulozDorazivsiho($dorazil);
            $doraziliIds[$dorazil->id()] = true;
        }
        foreach ($this->aktivita->prihlaseni() as $uzivatel) {
            if (!isset($doraziliIds[$uzivatel->id()])) {
                $this->ulozNedorazivsiho($uzivatel);
            }
        }
    }

    public function ulozDorazivsiho(\Uzivatel $dorazil) {
        // TODO kontrola, jestli prezence smí být uložena (např. jestli už nebyla uložena dřív)

        if ($this->aktivita->prihlasen($dorazil)) {
            dbInsertUpdate('akce_prihlaseni', [
                'id_uzivatele' => $dorazil->id(),
                'id_akce' => $this->aktivita->id(),
                'id_stavu_prihlaseni' => Aktivita::PRIHLASEN_A_DORAZIL,
            ]);
            $this->zalogujZeDorazil($dorazil);
        } else {
            $this->aktivita->odhlasZeSledováníAktivitVeStejnemCase($dorazil);
            dbInsert('akce_prihlaseni', [
                'id_uzivatele' => $dorazil->id(),
                'id_akce' => $this->aktivita->id(),
                'id_stavu_prihlaseni' => Aktivita::DORAZIL_JAKO_NAHRADNIK,
            ]);
            $this->zalogujZeDorazilJakoNahradnik($dorazil);
        }
    }

    public function zrusZeDorazil(\Uzivatel $dorazil): bool {
        // TODO kontrola, jestli prezence smí být uložena (např. jestli už nebyla uložena dřív)

        if ($this->aktivita->dorazilJakoNahradnik($dorazil)) {
            /* Návštěvník přidaný k aktivitě přes online prezenci se přidá jako náhradník a obratem potvrdí jeho přítomnost - přestože to aktivita sama vlastně nedovoluje. Když ho z aktivity zas ruší, tak ho ale nemůžeme zařadit do fronty jako náhradníka, protože to aktivita vlastně nedovoluje (a my to popravdě ani nechceme, když ho odškrtli při samotné online prezenci).
            PS: vlastně nechceme účastníka, kterého přidal vypravěč, "vracet" do stavu sledujícího, ale zatím to nechceme řešit. */
            if ($this->aktivita->prihlasovatelnaProSledujici()) {
                $this->aktivita->prihlasSledujiciho($dorazil);
            }
            dbDelete('akce_prihlaseni', [
                'id_uzivatele' => $dorazil->id(),
                'id_akce' => $this->aktivita->id(),
            ]);
            $this->zalogujZeZrusilPrihlaseniJakoNahradik($dorazil);
            return true;
        }
        if ($this->aktivita->dorazilJakoPredemPrihlaseny($dorazil)) {
            dbUpdate('akce_prihlaseni',
                ['id_stavu_prihlaseni' => Aktivita::PRIHLASEN], // vratime ho zpet jako "jen prihlaseneho"
                ['id_uzivatele' => $dorazil->id(), 'id_akce' => $this->aktivita->id()]
            );
            return true;
        }
        // else není co měnit, už je všude zrušený
        return false;
    }

    public function zalogujZeSePrihlasil(\Uzivatel $prihlaseny) {
        $this->log($prihlaseny, AktivitaPrezenceTyp::PRIHLASENI);
    }

    private function log(\Uzivatel $u, $zprava) {
        dbInsert('akce_prihlaseni_log', [
            'id_uzivatele' => $u->id(),
            'id_akce' => $this->aktivita->id(),
            'typ' => $zprava,
        ]);
    }

    public function zalogujZeSeOdhlasil(\Uzivatel $odhlaseny) {
        $this->log($odhlaseny, AktivitaPrezenceTyp::ODHLASENI);
    }

    private function zalogujZeZeNedostavil(\Uzivatel $nedorazil) {
        $this->log($nedorazil, AktivitaPrezenceTyp::NEDOSTAVENI_SE);
    }

    public function zalogujZeBylHromadneOdhlasen(\Uzivatel $hromadneOdhlasen) {
        $this->log($hromadneOdhlasen, AktivitaPrezenceTyp::ODHLASENI_HROMADNE);
    }

    public function zalogujZeDorazil(\Uzivatel $dorazil) {
        $this->log($dorazil, AktivitaPrezenceTyp::DORAZIL);
    }

    private function zalogujZeDorazilJakoNahradnik(\Uzivatel $dorazilNahradnik) {
        $this->log($dorazilNahradnik, AktivitaPrezenceTyp::DORAZIL_JAKO_NAHRADNIK);
    }

    public function zalogujZeSePrihlasilJakoSledujici(\Uzivatel $prihlasenySledujici) {
        $this->log($prihlasenySledujici, AktivitaPrezenceTyp::PRIHLASENI_SLEDUJICI);
    }

    public function zalogujZeZrusilPrihlaseniJakoNahradik(\Uzivatel $prihlasenySledujici) {
        $this->log($prihlasenySledujici, AktivitaPrezenceTyp::ZRUSENI_PRIHLASENI_NAHRADNIK);
    }

    public function zalogujZeSeOdhlasilJakoSledujici(\Uzivatel $odhlasenySledujici) {
        dbQuery(
            "INSERT INTO akce_prihlaseni_log SET id_uzivatele=$1, id_akce=$2, typ=$3",
            [$odhlasenySledujici->id(), $this->aktivita->id(), AktivitaPrezenceTyp::ODHLASENI_SLEDUJICI]
        );
    }

    public function ulozNedorazivsiho(\Uzivatel $nedorazil) {
        // TODO kontrola, jestli prezence smí být uložena (např. jestli už nebyla uložena dřív)

        dbDelete('akce_prihlaseni', [
            'id_uzivatele' => $nedorazil->id(),
            'id_akce' => $this->aktivita->id(),
        ]);
        dbInsert('akce_prihlaseni_spec', [
            'id_uzivatele' => $nedorazil->id(),
            'id_akce' => $this->aktivita->id(),
            'id_stavu_prihlaseni' => Aktivita::PRIHLASEN_ALE_NEDORAZIL,
        ]);
        $this->zalogujZeZeNedostavil($nedorazil);
        $this->posliMailNedorazivsimu($nedorazil);
    }

    /**
     * Pošle uživateli výchovný mail, že se nedostavil na aktivitu, a že by se
     * měl radši odhlašovat předem.
     */
    private function posliMailNedorazivsimu(\Uzivatel $u) {
        if (!GC_BEZI || !$this->aktivita->typ()->posilatMailyNedorazivsim()) {
            return;
        }

        (new \GcMail)
            ->adresat($u->mail())
            ->predmet('Nedostavení se na aktivitu')
            ->text(hlaskaMail('nedostaveniSeNaAktivituMail', $u))
            ->odeslat();
    }

    public function prihlasenOd(\Uzivatel $uzivatel): ?\DateTimeImmutable {
        $posledniAkce = $this->posledniTypPrihlaseniACasZmeny($uzivatel);
        if ($posledniAkce['typ'] !== AktivitaPrezenceTyp::PRIHLASENI) {
            return null;
        }
        return new \DateTimeImmutable($posledniAkce['cas']);
    }

    /**
     * @param \Uzivatel $uzivatel
     * @return string[]|null[]
     * @throws \Exception
     */
    public function posledniTypPrihlaseniACasZmeny(\Uzivatel $uzivatel): array {
        $kdyATyp = dbOneLine(<<<SQL
SELECT nejnovejsi.kdy, akce_prihlaseni_log.typ
FROM (
    SELECT MAX(cas) AS kdy, id_akce, id_uzivatele
    FROM akce_prihlaseni_log
    WHERE id_akce = $1 AND id_uzivatele = $2
    GROUP BY id_akce, id_uzivatele
) AS nejnovejsi
INNER JOIN akce_prihlaseni_log
    ON nejnovejsi.id_uzivatele = akce_prihlaseni_log.id_uzivatele
    AND nejnovejsi.id_akce = akce_prihlaseni_log.id_akce
    AND nejnovejsi.kdy = akce_prihlaseni_log.cas
GROUP BY akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele
SQL,
            [$this->aktivita->id(), $uzivatel->id()]
        );
        if (!$kdyATyp) {
            return [
                'typ' => null,
                'cas' => null,
            ];
        }
        return [
            'typ' => $kdyATyp['typ'],
            'cas' => (new \DateTimeImmutable($kdyATyp['kdy']))->format(DATE_ATOM),
        ];
    }

    /**
     * Vrátí pole uživatelů, kteří jsou sledujícími na aktivitě
     * @return \Uzivatel[]
     */
    public function seznamSledujicich(): array {
        if (!isset($this->seznamSledujicich)) {
            $this->seznamSledujicich = \Uzivatel::zIds(
                dbOneCol('
                    SELECT GROUP_CONCAT(akce_prihlaseni_spec.id_uzivatele)
                    FROM akce_seznam a
                    LEFT JOIN akce_prihlaseni_spec ON akce_prihlaseni_spec.id_akce = a.id_akce
                    WHERE akce_prihlaseni_spec.id_akce = ' . $this->aktivita->id() . '
                    AND akce_prihlaseni_spec.id_stavu_prihlaseni = ' . $this->aktivita::SLEDUJICI
                )
            );
        }
        return $this->seznamSledujicich;
    }

    /**
     * Je alespoń jeden účastník označen jako že dorazil, dorazil jako náhradník, nebo byl přihlášen ale nedorazil?
     * @return bool
     */
    public function jePrezenceUzavrena(): bool {
        return $this->aktivita->uzavrena();
    }

    /**
     * @param PosledniZmenyStavuPrihlaseni $posledniZnameZmenyStavuPrihlaseni
     * @return PosledniZmenyStavuPrihlaseni
     */
    public static function dejPosledniPlatneZmeny(PosledniZmenyStavuPrihlaseni $posledniZnameZmenyStavuPrihlaseni): PosledniZmenyStavuPrihlaseni {
        $index = 0;
        $where = 'akce_prihlaseni_log.id_akce = $' . $index;
        $sqlQueryParametry = [$index => $posledniZnameZmenyStavuPrihlaseni->getIdAktivity()];

        $novejsiNezZnameZmenyStavuSql = [];
        foreach ($posledniZnameZmenyStavuPrihlaseni->zmenyStavuPrihlaseni() as $zmenaStavuPrihlaseni) {
            $identifikatoryAktivitySql = [];

            $casZmenyStavu = $zmenaStavuPrihlaseni->casZmeny();
            if ($casZmenyStavu) {
                $index++;
                $novejsiNeboJinySql = 'akce_prihlaseni_log.cas > $' . $index; // novejsi
                $sqlQueryParametry[$index] = $casZmenyStavu->format(DateTimeCz::FORMAT_DB);

                $index++;
                $jinyVeStejnyCasSql = 'akce_prihlaseni_log.cas = $' . $index; // nebo ve stejny cas...
                $sqlQueryParametry[$index] = $casZmenyStavu->format(DateTimeCz::FORMAT_DB);

                $jinyTypNeboUcastnikSql = [];
                $index++;
                // ...ale odlisny stav (abychom nereagovali na tu samou zmenu vicekrat)...
                $jinyTypNeboUcastnikSql[] = 'akce_prihlaseni_log.typ != $' . $index;
                $sqlQueryParametry[$index] = $zmenaStavuPrihlaseni->stavPrihlaseni();
                $index++;
                // ...nebo je to jiny ucastnik
                $jinyTypNeboUcastnikSql[] = 'akce_prihlaseni_log.id_uzivatele != $' . $index;
                $sqlQueryParametry[$index] = $zmenaStavuPrihlaseni->idUzivatele();

                $jinyVeStejnyCasSql .= ' AND (' . implode(' OR ', $jinyTypNeboUcastnikSql) . ')';

                $novejsiNeboJinySql .= ' OR (' . $jinyVeStejnyCasSql . ')';

                $identifikatoryAktivitySql[] = $novejsiNeboJinySql;
            }

            $novejsiNezZnameZmenyStavuSql[] = '(' . implode(' AND ', $identifikatoryAktivitySql) . ')';
        }
        if ($novejsiNezZnameZmenyStavuSql) {
            $where .= ' AND (' . implode(' OR ', $novejsiNezZnameZmenyStavuSql) . ')';
        }
        /* For example:
        SELECT akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele, akce_prihlaseni_log.typ, akce_prihlaseni_log.cas
        FROM (SELECT akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele, MAX(akce_prihlaseni_log.cas) AS kdy
            FROM akce_prihlaseni_log
            INNER JOIN akce_prihlaseni -- abychom dostali jen změny, které stále platí
                ON akce_prihlaseni_log.id_akce = akce_prihlaseni.id_akce
            WHERE akce_prihlaseni_log.id_akce = 4057
            AND (
                (akce_prihlaseni_log.cas > '2022-04-26 11:48:54'
                OR (akce_prihlaseni_log.cas = '2022-04-26 11:48:54'
                    AND (akce_prihlaseni_log.typ != 'prihlaseni_nahradnik' OR akce_prihlaseni_log.id_uzivatele != 517))
            ))
        GROUP BY id_akce, id_uzivatele) AS nejnovejsi
        INNER JOIN akce_prihlaseni_log
            ON nejnovejsi.id_akce = akce_prihlaseni_log.id_akce
            AND nejnovejsi.id_uzivatele = akce_prihlaseni_log.id_uzivatele
            AND nejnovejsi.kdy = akce_prihlaseni_log.cas
        GROUP BY akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele;
         */

        $zmeny = dbFetchAll(<<<SQL
SELECT akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele, akce_prihlaseni_log.typ, akce_prihlaseni_log.cas
FROM (
    SELECT akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele, MAX(akce_prihlaseni_log.cas) AS kdy
    FROM akce_prihlaseni_log
    INNER JOIN akce_prihlaseni -- abychom dostali jen změny, které stále platí
        ON akce_prihlaseni_log.id_akce = akce_prihlaseni.id_akce
    WHERE $where
    GROUP BY id_akce, id_uzivatele
) AS nejnovejsi
INNER JOIN akce_prihlaseni_log
    ON nejnovejsi.id_akce = akce_prihlaseni_log.id_akce
    AND nejnovejsi.id_uzivatele = akce_prihlaseni_log.id_uzivatele
    AND nejnovejsi.kdy = akce_prihlaseni_log.cas
GROUP BY akce_prihlaseni_log.id_akce, akce_prihlaseni_log.id_uzivatele
SQL
            , $sqlQueryParametry
        );

        $nejnovejsiZmenyStavuPrihlaseni = new PosledniZmenyStavuPrihlaseni($posledniZnameZmenyStavuPrihlaseni->getIdAktivity());
        foreach ($zmeny as $zmena) {
            $zmenaStavuPrihlaseni = new ZmenaStavuPrihlaseni(
                (int)$zmena['id_uzivatele'],
                new \DateTimeImmutable($zmena['cas']),
                $zmena['typ']
            );
            $nejnovejsiZmenyStavuPrihlaseni->addPosledniZmenaStavuPrihlaseni($zmenaStavuPrihlaseni);
        }
        return $nejnovejsiZmenyStavuPrihlaseni;
    }
}