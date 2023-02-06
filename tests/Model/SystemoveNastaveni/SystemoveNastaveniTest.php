<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\SystemoveNastaveni;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\DbTest;

class SystemoveNastaveniTest extends DbTest
{
    protected static array $initQueries = [
        [
            <<<SQL
INSERT INTO uzivatele_hodnoty
SET id_uzivatele=$0,login_uzivatele=$1,jmeno_uzivatele=$2,prijmeni_uzivatele=$3
SQL,
            [0 => 48, 1 => 'Elden', 2 => 'Jakub', 3 => 'Jandák'],
        ],
    ];

    public function testZmenyKurzuEura() {
        $nastaveni = SystemoveNastaveni::vytvorZGlobals();

        $zaznamKurzuEuro = $nastaveni->dejZaznamyNastaveniPodleKlicu(['KURZ_EURO'])[0];
        /** viz migrace 2022-05-05_03-kurz-euro-do-systemoveho-nastaveni.php */
        self::assertSame('24', $zaznamKurzuEuro['hodnota']);
        self::assertNull($zaznamKurzuEuro['id_uzivatele']);

        $nastaveni->ulozZmenuHodnoty(123, 'KURZ_EURO', \Uzivatel::zId(48));

        $zaznamKurzuEuroPoZmene = $nastaveni->dejZaznamyNastaveniPodleKlicu(['KURZ_EURO'])[0];
        self::assertSame('123', $zaznamKurzuEuroPoZmene['hodnota']);
        self::assertSame('48', $zaznamKurzuEuroPoZmene['id_uzivatele']);
    }

    /**
     * @dataProvider provideVychoziHodnota
     */
    public function testVychoziHodnoty(int $rok, string $klic, string $ocekavanaHodnota) {
        $nastaveni = new SystemoveNastaveni(
            $rok,
            new DateTimeImmutableStrict($rok . '-12-31 23:59:59'),
            false,
            false
        );

        self::assertSame($ocekavanaHodnota, $nastaveni->dejVychoziHodnotu($klic));
    }

    public function provideVychoziHodnota(): array {
        return [
            'GC_BEZI_OD'             => [2023, 'GC_BEZI_OD', '2023-07-20 07:00:00'],
            'GC_BEZI_DO'             => [2023, 'GC_BEZI_DO', '2023-07-23 21:00:00'],
            'REG_GC_OD'              => [2023, 'REG_GC_OD', '2023-05-18 20:23:00'],
            'REG_AKTIVIT_OD'         => [2023, 'REG_AKTIVIT_OD', '2023-05-25 20:23:00'],
            'HROMADNE_ODHLASOVANI_1' => [2023, 'HROMADNE_ODHLASOVANI_1', '2023-06-30 23:59:00'],
            'HROMADNE_ODHLASOVANI_2' => [2023, 'HROMADNE_ODHLASOVANI_2', '2023-07-09 23:59:00'],
            'HROMADNE_ODHLASOVANI_3' => [2023, 'HROMADNE_ODHLASOVANI_3', '2023-07-16 23:59:00'],
        ];
    }

    /**
     * @dataProvider provideKonecUbytovani
     */
    public function testUkoceniUbytovani(string $konecUbytovaniDne, bool $ocekavaneUkoceniProdeje) {
        define('UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE', $konecUbytovaniDne);
        $nastaveni = new SystemoveNastaveni(ROK, new DateTimeImmutableStrict(), false, false);
        self::assertSame($ocekavaneUkoceniProdeje, $nastaveni->prodejUbytovaniUkoncen());
    }

    public function provideKonecUbytovani() {
        return [
            'Byl ukončen' => [
                (new \DateTimeImmutable())->setTime(0, 0, 0)->modify('-1 second')->format('Y-m-d'),
                true,
            ],
        ];
    }

    /**
     * @test
     */
    public function Zacatek_nejblizsi_vlny_ubytovani_je_ocekavany() {
        $nastaveni = new SystemoveNastaveni(ROK, new DateTimeImmutableStrict(), false, false);
        self::assertEquals(
            DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani($nastaveni),
            $nastaveni->zacatekNejblizsiVlnyOdhlasovani()
        );
    }
}
