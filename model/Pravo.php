<?php declare(strict_types=1);

namespace Gamecon;

class Pravo extends \DbObject
{
    protected static $tabulka = 'r_prava_soupis';

    /**
     * Konstanty jsou kopie SQL tabulky `r_prava_soupis`
     */
    public const PORADANI_AKTIVIT = 4; // Uživatel může pořádat aktivity (je v nabídce pořadatelů aktivit a má v administraci nabídku „moje aktivity“)
    public const PREKRYVANI_AKTIVIT = 5; // Smí mít zaregistrovaných víc aktivit v jeden čas
    public const PLNY_SERVIS = 7; // Uživatele kompletně platí a zajišťuje GC
    public const ZMENA_HISTORIE_AKTIVIT = 8; // Může přihlašovat a odhlašovat lidi z aktivit, které už proběhly

    public const ADMINISTRACE_UVOD = 100;
    public const ADMINISTRACE_UBYTOVANI = 101;
    public const ADMINISTRACE_AKCE = 102;
    public const ADMINISTRACE_PREZENCE = 103;
    public const ADMINISTRACE_REPORTY = 104;
    public const ADMINISTRACE_WEB = 105;
    public const ADMINISTRACE_PRAVA = 106;
    public const ADMINISTRACE_STATISTIKY = 107;
    public const ADMINISTRACE_FINANCE = 108;
    public const ADMINISTRACE_MOJE_AKTIVITY = 109;

    public const PLACKA_ZDARMA = 1002;
    public const KOSTKA_ZDARMA = 1003;
    public const JIDLO_SE_SLEVOU = 1004; // Může si objednávat jídlo se slevou
    public const JIDLO_ZDARMA = 1005; // Může si objednávat jídlo zdarma
    public const UBYTOVANI_ZDARMA = 1008; // Má zdarma ubytování po celou dobu
    public const MODRE_TRICKO_ZDARMA = 1012; // modré tričko zdarma při slevě, jejíž hodnota je určená konstantou MODRE_TRICKO_ZDARMA_OD
    public const STREDECNI_NOC_ZDARMA = 1015;
    public const NERUSIT_AUTOMATICKY_OBJEDNAVKY = 1016; // uživateli se při nezaplacení včas nebudou automaticky rušit objednávky
    public const NEDELNI_NOC_ZDARMA = 1018;
    public const CASTECNA_SLEVA_NA_AKTIVITY = 1019; // Sleva 40% na aktivity
    public const DVE_JAKAKOLI_TRICKA_ZDARMA = 1020;
    public const MUZE_OBJEDNAVAT_MODRA_TRICKA = 1021; // Může si objednávat modrá trička
    public const MUZE_OBJEDNAVAT_CERVENA_TRICKA = 1022; // Může si objednávat červená trička
    public const AKTIVITY_ZDARMA = 1023; // Sleva 100% na aktivity
    public const ZOBRAZOVAT_VE_STATISTIKACH_V_TABULCE_UCASTI = 1024; // V adminu v sekci statistiky v tabulce vlevo nahoře se tato židle vypisuje
    public const VYPISOVAT_V_REPORTU_NEUBYTOVANYCH = 1025; // V reportu Nepřihlášení a neubytovaní vypravěči se lidé na této židli vypisují
    public const TITUL_ORGANIZATOR = 1026; // V různých výpisech se označuje jako organizátor
    public const UNIKATNI_ZIDLE = 1027; // Uživatel může mít jen jednu židli s tímto právem
    public const BEZ_SLEVY_ZA_VEDENI_AKTIVIT = 1028; // Nedostává slevu za vedení aktivit ani účast na tech. aktivitách

    public static function obsahujePravoPoradatAktivity(array $idPrav): bool {
        return in_array(self::PORADANI_AKTIVIT, $idPrav, false);
    }
}
