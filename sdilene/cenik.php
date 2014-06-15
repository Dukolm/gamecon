<?php

/**
 * Třída zodpovědná za stanovení / prezentaci cen a slev věcí
 */

class Cenik {

  protected $u;
  protected $slevaKostky;
  protected $slevaPlacky;
  protected $slevaTricka;

  /**
   * Zobrazitelné texty k právům (jen statické). Nestatické texty nutno řešit
   * ručně. V polích se případně udává, které právo daný index „přebíjí“.
   */
  protected static $textSlev = array(
    P_KOSTKA_ZDARMA => 'kostka zdarma',
    P_PLACKA_ZDARMA => 'placka zdarma',
    P_TRIKO_ZDARMA  => 'jedno červené tričko zdarma',
    P_TRIKO_ZAPUL   => array('jedno modré vypravěčské tričko za polovic', P_TRIKO_ZDARMA),
    P_UBYTOVANI_ZDARMA  => 'ubytování zdarma',
    P_JIDLO_ZDARMA  => 'jídlo zdarma',
    P_JIDLO_SLEVA   => array('jídlo se slevou', P_JIDLO_ZDARMA),
    P_JIDLO_SNIDANE => 'možnost objednat si snídani',
  );

  /**
   * Konstruktor
   * @param Uzivatel $u pro kterého uživatele se cena počítá
   */
  function __construct(Uzivatel $u) {
    $this->u = $u;
    $this->slevaKostky = $u->maPravo(P_KOSTKA_ZDARMA) ? 15 : 0;
    $this->slevaPlacky = $u->maPravo(P_PLACKA_ZDARMA) ? 15 : 0;
    $this->slevaTricka = $u->maPravo(P_TRIKO_ZAPUL) ? 100 : 0;
    $this->slevaTricka = $u->maPravo(P_TRIKO_ZDARMA) ? 200 : $this->slevaTricka; // přebíjí předchozí
  }

  /**
   * Sníží $cena o částku $sleva až do nuly. Změnu odečte i z $sleva.
   */
  static function aplikujSlevu(&$cena, &$sleva) {
    if($sleva <= 0) return; // nedělat nic
    if($sleva <= $cena) {
      $cena -= $sleva;
      $sleva = 0;
    } else { // $sleva > $cena
      $sleva -= $cena;
      $cena = 0;
    }
  }

  /**
   * Vrátí pole s popisy obecných slev uživatele (typicky procentuálních na
   * aktivity)
   * @todo možnost (zvážit) použití objektu Sleva, který by se uměl aplikovat
   */
  function slevyObecne() {
    return array('nic');
  }

  /**
   * Vrátí pole s popisy speciálních slev a extra možností uživatele (typicky
   * vypravěčských, věci se slevami nebo zdarma apod.)
   * @todo vypravěčská sleva s číslem apod. (migrovat z financí)
   */
  function slevySpecialni() {
    $u = $this->u;
    $slevy = array();
    foreach(self::$textSlev as $pravo => $text) {
      // přeskočení práv, která mohou být přebita + normalizace textu
      if(is_array($text)) {
        foreach($text as $i => $pravoPrebiji) {
          if($i && $u->maPravo($pravoPrebiji)) continue 2;
        }
        $text = $text[0];
      }
      // přidání infotextu o slevě
      if($u->maPravo($pravo)) $slevy[] = $text;
    }
    return $slevy;
  }

  /**
   * Vrátí cenu věci v e-shopu pro daného uživatele
   */
  function shop($r) {
    if(isset($r['cena_aktualni'])) $cena = $r['cena_aktualni'];
    if(isset($r['cena_nakupni'])) $cena = $r['cena_nakupni'];
    if(!isset($cena)) throw new Exception('Nelze načíst cenu předmětu');
    if(!($typ = $r['typ'])) throw new Exception('Nenačten typ předmetu');
    // aplikace možných slev
    if($typ == Shop::PREDMET) {
      // hack podle názvu
      if($r['nazev'] == 'Kostka' && $this->slevaKostky) {
        self::aplikujSlevu($cena, $this->slevaKostky);
      } elseif($r['nazev'] == 'Placka' && $this->slevaPlacky) {
        self::aplikujSlevu($cena, $this->slevaPlacky);
      }
    } elseif($typ == Shop::TRICKO && $this->slevaTricka) {
      self::aplikujSlevu($cena, $this->slevaTricka);
    } elseif($typ == Shop::UBYTOVANI && $this->u->maPravo(P_UBYTOVANI_ZDARMA)) {
      $cena = 0;
    } elseif($typ == Shop::JIDLO) {
      if($this->u->maPravo(P_JIDLO_ZDARMA)) $cena = 0;
      elseif($this->u->maPravo(P_JIDLO_SLEVA) && strpos($r['nazev'], 'Snídaně') === false) $cena -= 20;
    }
    return (float)$cena;
  }

}


