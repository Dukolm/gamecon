<?php declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * https://trello.com/c/Zzo2htqI/892-vytvo%C5%99it-nov%C3%BD-report-email%C5%AF-p%C5%99i-odhla%C5%A1ov%C3%A1n%C3%AD-neplati%C4%8D%C5%AF
 */
class KategorieNeplatice
{

    public const LETOS_NEZAPLATIL_VUBEC_NIC = 1;
    public const LETOS_POSLAL_MALO_A_MA_VELKY_DLUH = 2;
    public const LETOS_POSLAL_MALO_A_MA_MALY_DLUH = 3;
    public const LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY = 4;
    public const LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU = 5;
    public const MA_PRAVO_PLATIT_AZ_NA_MISTE = 6; // orgové a tak

    /**
     * @var \DateTimeInterface|null
     */
    private $zacatekVlnyOdhlasovani;
    /**
     * @var Finance
     */
    private $finance;
    /**
     * @var int
     */
    private $rok;
    /**
     * @var bool
     */
    private $maPravoPlatitAzNaMiste;

    public static function vytvorProNadchazejiciVlnuZGlobals(
        \Uzivatel $uzivatel
    ) {
        return new self(
            $uzivatel->finance(),
            $uzivatel->kdySePrihlasilNaLetosniGc(),
            $uzivatel->maPravoNerusitObjednavky(),
            SystemoveNastaveni::zacatekNejblizsiVlnyOdhlasovani(),
            ROK,
            NEPLATIC_CASTKA_VELKY_DLUH,
            NEPLATIC_CASTKA_POSLAL_DOST,
            NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN
        );
    }

    /**
     * @var float
     */
    private $castkaVelkyDluh;
    /**
     * @var float
     */
    private $castkaPoslalDost;
    /**
     * @var int
     */
    private $pocetDnuPredVlnouKdyJeJesteChrane;
    /**
     * @var \DateTimeInterface|null
     */
    private $kdySePrihlasilNaLetosniGc;

    public function __construct(
        Finance             $finance,
        ?\DateTimeInterface $kdySePrihlasilNaLetosniGc,
        bool                $maPravoPlatitAzNaMiste,
        ?\DateTimeInterface $zacatekVlnyOdhlasovani, // prvni nebo druha vlna
        int                 $rok,
        float               $castkaVelkyDluh,
        float               $castkaPoslalDost,
        int                 $pocetDnuPredVlnouKdyJeJesteChrane
    ) {
        $this->castkaVelkyDluh = -abs($castkaVelkyDluh);
        $this->castkaPoslalDost = $castkaPoslalDost;
        $this->pocetDnuPredVlnouKdyJeJesteChrane = $pocetDnuPredVlnouKdyJeJesteChrane;
        $this->kdySePrihlasilNaLetosniGc = $kdySePrihlasilNaLetosniGc;
        $this->zacatekVlnyOdhlasovani = $zacatekVlnyOdhlasovani;
        $this->finance = $finance;
        $this->rok = $rok;
        $this->maPravoPlatitAzNaMiste = $maPravoPlatitAzNaMiste;
    }

    /**
     * Specifikace viz
     * https://trello.com/c/Zzo2htqI/892-vytvo%C5%99it-nov%C3%BD-report-email%C5%AF-p%C5%99i-odhla%C5%A1ov%C3%A1n%C3%AD-neplati%C4%8D%C5%AF
     * https://docs.google.com/document/d/1pP3mp9piPNAl1IKCC5YYe92zzeFdTLDMiT-xrUhVLdQ/edit
     */
    public function dejCiselnouKategoriiNeplatice(): ?int {
        if ($this->maPravoPlatitAzNaMiste) {
            // kategorie 6
            return self::MA_PRAVO_PLATIT_AZ_NA_MISTE;
        }

        if (!$this->kdySePrihlasilNaLetosniGc || !$this->zacatekVlnyOdhlasovani
            // zjišťovat neplatiče už nejde, některé platby mohly přijít až po začátku hromadného odhlašování (leda bychom filtrovali jednotlivé platby, ale tou dobou už to stejně nepotřebujeme)
            || $this->zacatekVlnyOdhlasovani < $this->kdySePrihlasilNaLetosniGc
        ) {
            return null;
        }

        $sumaPlateb = $this->finance->sumaPlateb($this->rok);
        if ($sumaPlateb >= $this->castkaPoslalDost) {
            // kategorie 4
            return self::LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY;
        }

        if ($this->prihlasilSeParDniPredVlnouOdhlasovani()) {
            // kategorie 5
            return self::LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU;
        }

        if ($sumaPlateb <= 0.0 && $this->finance->stav() > $this->castkaVelkyDluh /* jsme schovívaví k těm, co nemají velký dluh */) {
            // kategorie 1
            return self::LETOS_NEZAPLATIL_VUBEC_NIC;
        }

        return $this->finance->stav() /* ještě zápornější než velký dluh */ <= $this->castkaVelkyDluh
            // poslal málo na to, abychom mu ignorovali dluh a ještě k tomu má dluh velký
            ? self::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH // kategorie 2
            : self::LETOS_POSLAL_MALO_A_MA_MALY_DLUH; // kategorie 3
    }

    private function prihlasilSeParDniPredVlnouOdhlasovani(): bool {
        /** pozor, @see \DateInterval::$days vrací vždy absolutní hodnotu */
        return $this->zacatekVlnyOdhlasovani->diff($this->kdySePrihlasilNaLetosniGc)->days <= $this->pocetDnuPredVlnouKdyJeJesteChrane;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function zacatekVlnyOdhlasovani(): ?\DateTimeInterface {
        return $this->zacatekVlnyOdhlasovani;
    }
}