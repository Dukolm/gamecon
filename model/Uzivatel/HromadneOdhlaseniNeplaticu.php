<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Kanaly\GcMail;
use Gamecon\Logger\LogHomadnychAkciTrait;
use Gamecon\Logger\Zaznamnik;
use Gamecon\Pravo;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Exceptions\NaHromadneOdhlasovaniJeBrzy;
use Gamecon\Uzivatel\Exceptions\NaHromadneOdhlasovaniJePozde;
use Chyba;

class HromadneOdhlaseniNeplaticu
{
    private const SKUPINA = 'uzivatele';

    use LogHomadnychAkciTrait;

    private int $odhlasenoCelkem = 0;

    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni) {
    }

    /**
     * @throws NaHromadneOdhlasovaniJeBrzy
     * @throws NaHromadneOdhlasovaniJePozde
     */
    public function hromadneOdhlasit(
        string             $zdrojOdhlaseniZaklad,
        ?Zaznamnik         $zaznamnik,
        \DateTimeInterface $platnostZpetneKDatu = null
    ): int {
        $nejblizsiHromadneOdhlasovaniKdy = $this->systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy($platnostZpetneKDatu);
        $poradiHromadnehoOdhlasovani     = DateTimeGamecon::poradiHromadnehoOdhlasovani(
            $nejblizsiHromadneOdhlasovaniKdy,
            $this->systemoveNastaveni
        );
        $zdrojOdhlaseni                  = "$zdrojOdhlaseniZaklad-$poradiHromadnehoOdhlasovani";
        $uzivatelSystem                  = \Uzivatel::zId(\Uzivatel::SYSTEM);
        foreach ($this->neplaticiAKategorie($nejblizsiHromadneOdhlasovaniKdy)
                 as ['uzivatel' => $uzivatel, 'kategorie_neplatice' => $kategorieNeplatice]) {
            /**
             * @var \Uzivatel $uzivatel
             * @var KategorieNeplatice $kategorieNeplatice
             */
            if ($kategorieNeplatice->melByBytOdhlasen()) {
                if ($kategorieNeplatice->maSmyslOdhlasitMuJenNeco()) {
                    $necoOdhlaseno = null;
                    do {
                        $necoOdhlaseno = $this->odhlasMuJenNeco($uzivatel, $zdrojOdhlaseni, $uzivatelSystem, $necoOdhlaseno);
                        if ($necoOdhlaseno) {
                            $kategorieNeplatice->otoc(false /* platby se nezměniliy */);
                        }
                    } while ($necoOdhlaseno !== false && $kategorieNeplatice->melByBytOdhlasen());
                    if (!$kategorieNeplatice->melByBytOdhlasen()) {
                        $this->posliEmailSOdhlasenymiPolozkami($uzivatel, $zdrojOdhlaseni);
                        continue; // povedlo se, postupným odhlašováním položek jsme se dostali až k tomu, že nemusíme odhlásit samotného účastníka
                    }
                }
                try {
                    $uzivatel->gcOdhlas(
                        $zdrojOdhlaseni,
                        $uzivatelSystem,
                        $this->systemoveNastaveni,
                        $zaznamnik,
                    );
                    $zaznamnik?->pridejEntitu($uzivatel);
                    $this->odhlasenoCelkem++;
                    set_time_limit(30); // jenom pro jistotu, mělo by to trvat maximálně sekundu
                } catch (Chyba $chyba) {
                    $potiz = sprintf(
                        "Nelze ohlásit účastníka %s s ID %d: '%s'",
                        $uzivatel->jmenoNick(),
                        $uzivatel->id(),
                        $chyba->getMessage()
                    );
                    $zaznamnik?->pridejZpravu("Potíže: $potiz");
                }
            }
        }

        $this->zalogujHromadneOdhlaseni(
            $this->odhlasenoCelkem,
            $nejblizsiHromadneOdhlasovaniKdy,
            \Uzivatel::zId(\Uzivatel::SYSTEM, true)
        );

        return $this->odhlasenoCelkem;
    }

    private function odhlasMuJenNeco(
        \Uzivatel $uzivatel,
        string    $zdrojOdhlaseni,
        \Uzivatel $odhlasujici,
        ?string   $naposledyOdhlaseno
    ): string|false {
        if ($naposledyOdhlaseno === null
            && $uzivatel->shop($this->systemoveNastaveni)->zrusLetosniUbytovani($zdrojOdhlaseni) > 0
        ) {
            return 'ubytovani';
        }
        if ($naposledyOdhlaseno === 'ubytovani'
            && $uzivatel->shop($this->systemoveNastaveni)->zrusPrihlaseniNaLetosniLarpy($odhlasujici, $zdrojOdhlaseni) > 0
        ) {
            return 'larpy';
        }
        if ($naposledyOdhlaseno === 'larpy'
            && $uzivatel->shop($this->systemoveNastaveni)->zrusPrihlaseniNaLetosniRpg($odhlasujici, $zdrojOdhlaseni) > 0
        ) {
            return 'rpg';
        }
        // respektive na zbývající
        if ($naposledyOdhlaseno === 'rpg'
            && $uzivatel->shop($this->systemoveNastaveni)->zrusPrihlaseniNaVsechnyAktivity($odhlasujici, $zdrojOdhlaseni) > 0
        ) {
            return 'ostatni-aktivity';
        }
        return false;
    }

    private function zalogujHromadneOdhlaseni(
        int                $odhlaseno,
        \DateTimeInterface $hromadneOdhlasovaniKdy,
        \Uzivatel          $odhlasujici
    ) {
        $this->zalogujHromadnouAkci(
            self::SKUPINA,
            $this->sestavNazevAkceHromadnehoOdhlaseni($hromadneOdhlasovaniKdy),
            $odhlaseno,
            $odhlasujici
        );
    }

    private function sestavNazevAkceHromadnehoOdhlaseni(\DateTimeInterface $hromadneOdhlasovaniKdy): string {
        return 'odhlaseni-' . $hromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_CAS_SOUBOR);
    }

    private function uzivateleKeKontrole(): \Generator {
        $prihlasenNaLetosniGc = Role::PRIHLASEN_NA_LETOSNI_GC;
        $neodhlasovat         = Role::LETOSNI_NEODHLASOVAT;

        $result = dbQuery(<<<SQL
SELECT uzivatele_hodnoty.id_uzivatele
FROM uzivatele_hodnoty
WHERE EXISTS(SELECT * FROM platne_role_uzivatelu AS role
      WHERE uzivatele_hodnoty.id_uzivatele = role.id_uzivatele
        AND role.id_role = {$prihlasenNaLetosniGc}
    )
    AND NOT EXISTS(SELECT * FROM platne_role_uzivatelu AS role
        WHERE uzivatele_hodnoty.id_uzivatele = role.id_uzivatele
        AND role.id_role = {$neodhlasovat}
    )
SQL,
            dbConnectTemporary() // abychom nevyblokovali globální sdílené connection při postupném zpracovávání tohoto generátoru
        );
        while ($idUzivatele = mysqli_fetch_column($result)) {
            yield \Uzivatel::zId($idUzivatele, true);
        }
    }

    public function odhlasenoCelkem(): int {
        return $this->odhlasenoCelkem;
    }

    public function odhlaseniProvedenoKdy(\DateTimeInterface $hromadneOdhlasovaniKdy = null): ?\DateTimeInterface {
        $hromadneOdhlasovaniKdy ??= $this->systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy();
        $nazevAkce              = $this->sestavNazevAkceHromadnehoOdhlaseni($hromadneOdhlasovaniKdy);

        return $this->posledniHromadnaAkceKdy(self::SKUPINA, $nazevAkce);
    }

    /**
     * @return \Generator{neplatic: \Uzivatel, kategorie_neplatice: KategorieNeplatice}
     * @throws NaHromadneOdhlasovaniJeBrzy
     * @throws NaHromadneOdhlasovaniJePozde
     */
    public function neplaticiAKategorie(
        \DateTimeInterface $nejblizsiHromadneOdhlasovaniKdy = null,
        \DateTimeInterface $kDatu = null,
        \DateTimeInterface $platnostZpetneKDatu = null,

    ): \Generator {
        $nejblizsiHromadneOdhlasovaniKdy ??= $this->systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy();
        $kDatu                           ??= $this->systemoveNastaveni->ted();

        if ($nejblizsiHromadneOdhlasovaniKdy > $kDatu) {
            throw new NaHromadneOdhlasovaniJeBrzy(
                sprintf(
                    "Hromadné odhlášení může být spuštěno nejdříve v '%s'",
                    $nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)
                )
            );
        }

        $platnostZpetneKDatu ??= $kDatu->modify('-1 day');
        if ($nejblizsiHromadneOdhlasovaniKdy < $platnostZpetneKDatu) {
            throw new NaHromadneOdhlasovaniJePozde(
                sprintf(
                    "Hromadné odhlášení může být spuštěno nanejvýš den po platnosti.
Platnost hromadného odhlášení byla '%s', teď je '%s' a nejpozději šlo hromadně odhlásit v '%s'",
                    $nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB),
                    $kDatu->format(DateTimeCz::FORMAT_DB),
                    $platnostZpetneKDatu->format(DateTimeCz::FORMAT_DB),
                )
            );
        }

        $nejblizsiVlnaKdy = $this->systemoveNastaveni->nejblizsiVlnaKdy($platnostZpetneKDatu);
        if ($nejblizsiHromadneOdhlasovaniKdy >= $nejblizsiVlnaKdy) {
            throw new NaHromadneOdhlasovaniJePozde(
                sprintf(
                    "Nejbližší vlna aktivit už začala v '%s', nemůžeme začít hromadně odhlašovat k okamžiku '%s'",
                    $nejblizsiVlnaKdy->format(DateTimeCz::FORMAT_DB),
                    $nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)
                )
            );
        }

        foreach ($this->uzivateleKeKontrole() as $uzivatel) {
            $kategorieNeplatice = KategorieNeplatice::vytvorZHromadnehoOdhlasovani(
                $uzivatel,
                $nejblizsiHromadneOdhlasovaniKdy,
                $this->systemoveNastaveni
            );
            if ($kategorieNeplatice->melByBytOdhlasen()) {
                yield [
                    'neplatic'            => $uzivatel,
                    'kategorie_neplatice' => $kategorieNeplatice,
                ];
            }
        }
    }

    public function cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy(
        \DateTimeInterface $hromadneOdhlasovaniKdy,
        int                $poradiOznameni
    ): ?DateTimeImmutableStrict {
        return $this->posledniHromadnaAkceKdy(
            self::SKUPINA,
            $this->sestavNazevAkceEmailuCfoInfo($poradiOznameni, $hromadneOdhlasovaniKdy),
        );
    }

    public function neplaticiNotifikovaniOBrzkemHromadnemOdhlaseniKdy(
        \DateTimeInterface $hromadneOdhlasovaniKdy,
        int                $poradiOznameni
    ): ?DateTimeImmutableStrict {
        return $this->posledniHromadnaAkceKdy(
            self::SKUPINA,
            $this->sestavNazevAkceEmailuSVarovanim($poradiOznameni, $hromadneOdhlasovaniKdy),
        );
    }

    private function sestavNazevAkceEmailuSVarovanim(
        int                $poradiOznameni,
        \DateTimeInterface $hromadneOdhlasovaniKdy
    ): string {
        return "email-varobvani-neplaticum-brzke-odhlaseni-$poradiOznameni-" . $hromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_CAS_SOUBOR);
    }

    public function zalogujNotifikovaniCfoOBrzkemHromadnemOdhlaseni(
        int                $budeOdhlaseno,
        \DateTimeInterface $hromadneOdhlasovaniKdy,
        int                $poradiOznameni,
        \Uzivatel          $odeslal,
        \DateTimeInterface $stalaSeKdy = null
    ) {
        $this->zalogujHromadnouAkci(
            self::SKUPINA,
            $this->sestavNazevAkceEmailuCfoInfo($poradiOznameni, $hromadneOdhlasovaniKdy),
            $budeOdhlaseno,
            $odeslal,
            $stalaSeKdy
        );
    }

    private function sestavNazevAkceEmailuCfoInfo(
        int                $poradiOznameni,
        \DateTimeInterface $hromadneOdhlasovaniKdy
    ): string {
        return "email-cfo-brzke-odhlaseni-$poradiOznameni-" . $hromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_CAS_SOUBOR);
    }

    public function zalogujNotifikovaniNeplaticuOBrzkemHromadnemOdhlaseni(
        int                $pocetPotencialnichNeplaticu,
        \DateTimeInterface $hromadneOdhlasovaniKdy,
        int                $poradiOznameni,
        \Uzivatel          $odeslal,
        \DateTimeInterface $staloSeKdy = null
    ) {

        $this->zalogujHromadnouAkci(
            self::SKUPINA,
            $this->sestavNazevAkceEmailuSVarovanim($poradiOznameni, $hromadneOdhlasovaniKdy),
            $pocetPotencialnichNeplaticu,
            $odeslal,
            $staloSeKdy
        );
    }

    private function posliEmailSOdhlasenymiPolozkami(\Uzivatel $uzivatel, string $zdrojOdhlaseni) {
        $nazvyZrusenychAktivit = Aktivita::dejNazvyZrusenychAktivitUzivatele(
            $uzivatel,
            $zdrojOdhlaseni,
            $this->systemoveNastaveni->rocnik()
        );

        $nazvyZrusenychNakupu = $uzivatel->shop($this->systemoveNastaveni)->dejNazvyZrusenychNakupu($zdrojOdhlaseni);
        if (!$nazvyZrusenychAktivit && !$nazvyZrusenychNakupu) {
            return;
        }

        $castiPredmetu = [];

        if ($nazvyZrusenychNakupu) {
            $castiPredmetu[] = 'objednávky';
            $castiTextu[]    = 'zrušit tvé objednávky ' . implode(', ', $nazvyZrusenychNakupu);
        }

        if ($nazvyZrusenychAktivit) {
            $castiPredmetu[] = 'aktivity';
            $y               = count($nazvyZrusenychAktivit) > 1
                ? 'y'
                : '';
            $te              = count($castiTextu) > 0
                ? ''
                : 'tě ';
            $castiTextu[]    = "{$te}odlásit z aktivit$y " . implode(', ', $nazvyZrusenychAktivit);
        }

        $text = implode(' a ', $castiTextu);

        (new GcMail())
            ->adresat($uzivatel->mail())
            ->predmet('Odhlášené ' . implode(' a ', $castiPredmetu))
            ->text(<<<TEXT
                Jelikož tvé finance nedorazili na účet Gameconu včas, museli jsme $text

                Aktivity si můžeš znovu přihlásit v další vlně, předměty si můžeš znovu objednat kdykoliv. Jen prosíme ohlídej své platby.

                Tým Gameconu
                TEXT
            )
            ->odeslat();
    }
}