<?php

/**
 * Třída starající se o e-shop, nákupy, formy a související
 */

class Shop
{

  protected
    $u,
    $cenik,               // instance ceníku
    $nastaveni = array(   // případné spec. chování shopu
      'ubytovaniBezZamku' => false,   // ignorovat pozastavení objednávek u ubytování
      'jidloBezZamku'     => false,   // ignorovat pozastavení objednávek u jídla
    ),
    $ubytovani=array(),
    $tricka=array(),
    $predmety=array(),
    $jidlo=array(),
    $ubytovaniOd,
    $ubytovaniDo,
    $ubytovaniTypy=array(),
    $vstupne,       // dobrovolné vstupné (složka zaplacená regurélně včas)
    $vstupnePozde,  // dobrovolné vstupné (složka zaplacená pozdě)
    $vstupneJeVcas, // jestli se dobrovolné vstupné v tento okamžik chápe jako zaplacené včas
    $klicU='shopU', //klíč formu pro identifikaci polí
    $klicUPokoj='shopUPokoj', //s kým chce být na pokoji
    $klicV='shopV', //klíč formu pro identifikaci vstupného
    $klicP='shopP', //klíč formu pro identifikaci polí
    $klicT='shopT', //klíč formu pro identifikaci polí s tričkama
    $klicS='shopS'; //klíč formu pro identifikaci polí se slevami
    //$quiet //todo

  protected static $skoly=array(
    'UK Univerzita Karlova Praha',
    'MU Masarykova univerzita Brno',
    'VUT Vysoké učení technické Brno',
    'VŠE Vysoká škola ekonomická Praha',
    'ČVUT České vysoké učení technické Praha',
    'VŠB-TU Vysoká škola báňská-Technická univerzita Ostrava',
    'ZU Západočeská univerzita v Plzni',
    'UP Univerzita Palackého v Olomouci',
    'ČZU Česká zemědělská univerzita v Praze',
    'MENDELU Mendelova zemědělská a lesnická univerzita v Brně',
    'UTB Univerzita Tomáše Bati ve Zlíně',
    'JU Jihočeská univerzita v Českých Budějovicích',
    'Univerzita Pardubice',
    'TU Technická univerzita v Liberci',
    'UJEP Univerzita J. E. Purkyně v Ústí nad Labem',
    'Univerzita Hradec Králové',
    'SU Slezská univerzita v Opavě',
    'VŠO Vysoká škola obchodní v Praze',
    'UJAK Univerzita Jana Amose Komenského',
    'VŠCHT Vysoká škola chemicko-technologická v Praze'
  );
  protected static $dny = array('středa', 'čtvrtek', 'pátek', 'sobota', 'neděle');

  const
    PREDMET = 1,
    UBYTOVANI = 2,
    TRICKO = 3,
    JIDLO = 4,
    VSTUPNE = 5,
    PN_JIDLO = 'cShopJidlo',          // post proměnná pro jídlo
    PN_JIDLO_ZMEN = 'cShopJidloZmen'; // post proměnná indikující, že se má jídlo aktualizovat

  /**
   * Konstruktor
   */
  function __construct(Uzivatel $u, $nastaveni = null)
  {
    $this->u = $u;
    $this->cenik = new Cenik($u);
    if(is_array($nastaveni)) {
      $this->nastaveni = array_replace($this->nastaveni, $nastaveni);
    }

    // vybrat všechny předměty pro tento rok + předměty v nabídce + předměty, které si koupil
    $o = dbQuery('
      SELECT
        p.*,
        IF(p.model_rok = $1, nazev, CONCAT(nazev," ",model_rok)) as nazev,
        COUNT(IF(n.rok = $1, 1, NULL)) kusu_prodano,
        COUNT(IF(n.id_uzivatele = $2 AND n.rok = $1, 1, NULL)) kusu_uzivatele,
        SUM(  IF(n.id_uzivatele = $2 AND n.rok = $1, cena_nakupni, 0)) sum_cena_nakupni
      FROM shop_predmety p
      LEFT JOIN shop_nakupy n USING(id_predmetu)
      WHERE stav > 0 OR n.rok = '.ROK.'
      GROUP BY id_predmetu
      ORDER BY typ, ubytovani_den, nazev, model_rok DESC
    ', [ROK, $this->u->id()]);

    //inicializace
    $this->jidlo['dny'] = array();
    $this->jidlo['druhy'] = array();

    while($r = mysql_fetch_assoc($o)) {
      $typ = $r['typ'];
      unset($fronta); // $fronta reference na frontu kam vložit předmět (nelze dát =null, přepsalo by předchozí vrch fronty)
      $r['nabizet'] = $r['stav'] == 1; // v základu nabízet vše v stavu 1
      // rozlišení kam ukládat a jestli nabízet podle typu
      if($typ == self::PREDMET) {
        $fronta = &$this->predmety[];
      } elseif( $typ == self::JIDLO ) {
        $r['nabizet'] = $r['nabizet'] ||
          $r['stav'] == 2 && strpos($r['nazev'],'Snídaně')!==false && $this->u->maPravo(P_JIDLO_SNIDANE) ||
          //TODO pokud ostatní jídla nebudou public, nutno přidat nabízení na základě dalších práv
          $r['stav'] == 3 && $this->nastaveni['jidloBezZamku'];
        $den = $r['ubytovani_den'];
        $druh = self::bezDne($r['nazev']);
        if($r['kusu_uzivatele'] > 0) $this->jidlo['jidloObednano'][$r['id_predmetu']] = true;
        if($r['kusu_uzivatele'] || $r['nabizet']) {
          //zobrazení jen dnů / druhů, které mají smysl
          $this->jidlo['dny'][$den] = true;
          $this->jidlo['druhy'][$druh] = true;
        }
        $fronta = &$this->jidlo['jidla'][$den][$druh];
      } elseif( $typ == self::UBYTOVANI ) {
        $r['nabizet'] = $r['nabizet'] ||
          $r['stav'] == 3 && $this->nastaveni['ubytovaniBezZamku'];
        $fronta = &$this->ubytovani[];
      } elseif( $typ == self::TRICKO ) {
        $r['nabizet'] = $r['nabizet'] ||
          $r['stav'] == 2 && strpos($r['nazev'],'modré')!==false && $this->u->maPravo(P_TRIKO_ZAPUL) ||  // modrá trička
          $r['stav'] == 2 && strpos($r['nazev'],'červené')!==false && $this->u->maPravo(P_TRIKO_ZDARMA); // červená trička
        $fronta = &$this->tricka[];
        // hack pro výběr správného automaticky objednaného trička
        $barva = 'zelené';
        if($this->u->maPravo(P_TRIKO_ZAPUL)) $barva = 'modré';
        if($this->u->maPravo(P_TRIKO_ZDARMA)) $barva = 'červené';
        $r['auto'] = $r['nabizet'] && (
          $this->u->pohlavi() == 'm' && strpos($r['nazev'], "Tričko $barva pánské L") !== false ||
          $this->u->pohlavi() == 'f' && strpos($r['nazev'], "Tílko $barva dámské S") !== false
        );
      } elseif($typ == self::VSTUPNE) {
        if(strpos($r['nazev'], 'pozdě') === false) {
          $this->vstupne = $r;
          $this->vstupneJeVcas = $r['stav'] == 2;
        } else {
          $this->vstupnePozde = $r;
        }
      } else {
        throw new Exception('Objevil se nepodporovaný typ předmětu s č.'.$r['typ']);
      }
      // vybrané předměty nastavit jako automaticky objednané
      if($r['nabizet'] && $r['auto'] && $this->prvniNakup()) {
        $r['kusu_uzivatele']++;
      }
      // finální uložení předmětu na vrchol dané fronty
      $fronta = $r;
    }
    $this->ubytovani = new ShopUbytovani($this->ubytovani, $this->u); // náhrada reprezentace polem za objekt
  }

  /** Smaže z názvu identifikaci dne */
  static function bezDne($nazev) {
    $re = ' ?pondělí| ?úterý| ?středa| ?čtvrtek| ?pátek| ?sobota| ?neděle';
    return preg_replace('@'.$re.'@', '', $nazev);
  }

  protected static function denNazev($cislo) {
    return self::$dny[$cislo];
  }

  /**
   * Vrátí html kód formuláře s výběrem jídla
   */
  function jidloHtml() {
    // inicializace
    ksort($this->jidlo['druhy']);
    $dny = $this->jidlo['dny'];
    $druhy = $this->jidlo['druhy'];
    $jidla = $this->jidlo['jidla'];
    // vykreslení
    $t = new XTemplate(__DIR__ . '/shop-jidlo.xtpl');
    foreach($druhy as $druh => $i) {
      foreach($dny as $den => $i) {
        $jidlo = @$jidla[$den][$druh];
        if($jidlo && ($jidlo['nabizet'] || $jidlo['kusu_uzivatele'])) {
          $t->assign('selected', $jidlo['kusu_uzivatele'] > 0 ? 'checked' : '');
          $t->assign('pnName', self::PN_JIDLO . '[' . $jidlo['id_predmetu'] . ']');
          $t->parse( $jidlo['stav'] == 3 && !$this->nastaveni['jidloBezZamku'] ? 'jidlo.druh.den.locked' : 'jidlo.druh.den.checkbox');
        }
        $t->parse('jidlo.druh.den');
      }
      $t->assign('druh', $druh);
      $t->assign('cena', $this->cenik->shop($jidlo).'&thinsp;Kč');
      $t->parse('jidlo.druh');
    }
    // hlavička
    foreach($dny as $den => $i) {
      $t->assign('den', mb_ucfirst(self::denNazev($den)));
      $t->parse('jidlo.den');
    }
    // info o pozastaveni
    if(!$dny || $jidlo['stav'] == 3) {
      $t->parse('jidlo.pozastaveno');
    }
    $t->assign('pnJidloZmen', self::PN_JIDLO_ZMEN);
    $t->parse('jidlo');
    return $t->text('jidlo');
  }

  /**
   * Vrátí html kód formuláře s předměty a tričky (bez form značek kvůli
   * integraci více věcí naráz).
   * @todo vyprodání věcí
   */
  function predmetyHtml()
  {
    $out = '';
    if(current($this->predmety)['stav'] == 3) $out .= 'Objednávka předmětů je ukončena.<br>';
    $out .= $this->vyberPlusminus($this->predmety);
    if(current($this->tricka)['stav'] == 3) $out .= 'Objednávka triček je ukončena.<br>';
    $out .= $this->vyberSelect($this->tricka);

    // slovně popsané slvey fixme nedokonalé, na pevno zadrátované
    $ka = $this->u->pohlavi() == 'f' ? 'ka' : '';
    if($this->u->maPravo(P_TRIKO_ZDARMA))
      $out .= '<p><i>Jako pro organizátora pro tebe výš uvedené ceny neplatí a máš jedno červené tričko, kostku, placku a veškeré jídlo zdarma :)</i></p>';
    else if($this->u->maPravo(P_TRIKO_ZAPUL))
      $out .= "<p><i>Jako vypravěč$ka máš poloviční slevu na modré (vypravěčské) tričko nebo tílko. Kostku a placku máš zdarma. Výš uvedené ceny pro tebe tedy neplatí.</i></p>";

    return $out;
  }

  /**
   * Jestli je toto prvním nákupem daného uživatele
   */
  protected function prvniNakup() {
    return !$this->u->gcPrihlasen();
  }

  /** Vrátí html kód formuláře pro naklikání slev */
  function slevyHtml() {
    $t = new XTemplate(__DIR__.'/shop-slevy.xtpl');
    $t->assign([
      'postname'  =>  $this->klicS,
      'checked'   =>  $this->u->maZidli(Z_STUDENT) ? 'checked' : '',
      'skola'     =>  dbOneCol('SELECT skola FROM uzivatele_hodnoty WHERE id_uzivatele = '.$this->u->id()),
      'skoly'     =>  '"' . implode('","', self::$skoly) . '"',
      'slevaDo'   =>  date('j.n.', strtotime(SLEVA_DO)),
      'novacci'   =>  dbOneCol('SELECT GROUP_CONCAT(id_uzivatele) FROM uzivatele_hodnoty WHERE guru = '.$this->u->id().' AND YEAR(registrovan) >= '.ROK),
    ]);
    if(SLEVA_AKTIVNI) $t->parse('slevy.vcas');
    $t->parse('slevy');
    return $t->text('slevy');
  }

  /** Vrátí html kód s rádiobuttonky pro vyklikání ubytování */
  function ubytovaniHtml() {
    return $this->ubytovani->html();
  }

  /** Vrátí html formuláře se vstupným */
  function vstupneHtml() {
    $t = new XTemplate(__DIR__.'/shop-vstupne.xtpl');
    $t->assign([
      'jsSlider'  =>  URL_WEBU.'/soubory/jquery.sglide.2.1.2.min.js',
      'knoflik'   =>  URL_WEBU.'/soubory/styl/knob.png',
      'stav'      =>  $this->vstupne['kusu_uzivatele'] || $this->vstupnePozde['kusu_uzivatele'] ?
        $this->vstupne['sum_cena_nakupni'] + $this->vstupnePozde['sum_cena_nakupni'] :
        50, // výchozí hodnota
      'postname'  =>  $this->klicV,
      'min'       =>  $this->vstupneJeVcas ? 0 : $this->vstupne['sum_cena_nakupni'],
    ]);
    $t->parse('vstupne');
    return $t->text('vstupne');
  }

  /**
   * Upraví objednávku z pole id $stare na pole $nove
   * @todo zaintegrovat i jinde (ale zároveň nutno zobecnit pro vícenásobné
   * nákupy jednoho ID)
   */
  protected function zmenObjednavku($stare, $nove) {
    $nechce = array_diff($stare, $nove);
    $chceNove = array_diff($nove, $stare);
    // přírustky
    $values = '';
    foreach($chceNove as $n) {
      $sel = 'SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu = '.$n;
      $values .= "\n".'('.$this->u->id().','.$n.','.ROK.',('.$sel.'),NOW()),';
    }
    if($values) {
      $values[strlen($values)-1] = ';';
      dbQuery('INSERT INTO shop_nakupy(id_uzivatele, id_predmetu, rok, cena_nakupni, datum) VALUES '.$values);
    }
    // mazání
    if($nechce) {
      dbQueryS('DELETE FROM shop_nakupy WHERE id_uzivatele = $1 AND rok = $2 AND id_predmetu IN($3)', array(
        $this->u->id(), ROK, $nechce
      ));
    }
  }

  /**
   * Zpracuje část formuláře s předměty a tričky
   * Čáry máry s ručním počítáním diference (místo smazání a náhrady) jsou nut-
   * né kvůli zachování původní nákupní ceny (aktuální cena se totiž mohla od
   * nákupu změnit).
   */
  function zpracujPredmety()
  {
    if(isset($_POST[$this->klicP]) && isset($_POST[$this->klicT]))
    {
      // pole s předměty, které jsou vyplněné ve formuláři
      $nove=array();
      foreach($_POST[$this->klicP] as $idPredmetu => $pocet)
        for($i=0;$i<$pocet;$i++)
          $nove[]=(int)$idPredmetu;
      foreach($_POST[$this->klicT] as $idTricka) // připojení triček
        if($idTricka) // odstranění výběrů „žádné tričko“
          $nove[]=(int)$idTricka;
      sort($nove);
      // pole s předměty, které už má objednané dříve (bez ubytování)
      $stare=array();
      $o=dbQuery('SELECT id_predmetu FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE id_uzivatele='.$this->u->id().' AND rok='.ROK.' AND typ IN('.self::PREDMET.','.self::TRICKO.') ORDER BY id_predmetu');
      while($r=mysql_fetch_assoc($o))
        $stare[]=(int)$r['id_predmetu'];
      // určení rozdílů polí (note: array_diff ignoruje vícenásobné výskyty hodnot a nedá se použít)
      $i=$j=0;
      $odstranit=array(); //čísla (kvůli nutností více delete dotazů s limitem)
      $pridat=''; //část sql dotazu
      while(!empty($nove[$i]) || !empty($stare[$j]))
        if(empty($stare[$j]) || (!empty($nove[$i]) && $nove[$i]<$stare[$j]))
          // tento prvek není v staré objednávce
          // zapíšeme si ho pro přidání a přeskočíme na další
          $pridat.="\n".'('.$this->u->id().','.$nove[$i].','.ROK.',(SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu='.$nove[$i++].'),NOW()),'; //$i se inkrementuje se po provedení druhého!
        else if(empty($nove[$i]) || $stare[$j]<$nove[$i])
          // tento prvek ze staré objednávky není v nové objednávce
          // zapíšeme si ho, že má být odstraněn, a skočíme na další
          $odstranit[]=$stare[$j++];
        else
          // prvky jsou shodné, skočíme o jedna v obou seznamech a neděláme nic
          $i++ == $j++; //porovnání bez efektu
      // odstranění předmětů, které z objednávky oproti DB zmizely
      foreach($odstranit as $idPredmetu)
        dbQuery('DELETE FROM shop_nakupy WHERE id_uzivatele='.$this->u->id().' AND id_predmetu='.$idPredmetu.' AND rok='.ROK.' LIMIT 1');
      // přidání předmětů, které doposud objednané nemá
      $q='INSERT INTO shop_nakupy(id_uzivatele,id_predmetu,rok,cena_nakupni,datum) VALUES ';
      $q.=$pridat;
      if(substr($q,-1)!=' ') //hack testující, jestli se přidala nějaká část
        dbQuery(substr($q,0,-1)); //odstranění nadbytečné čárky z poslední přidávané části a spuštění dotazu
    }
  }

  /**
   * Zpracuje část formuláře s ubytováním
   * @return bool jestli došlo k zpracování dat
   */
  function zpracujUbytovani() {
    return $this->ubytovani->zpracuj();
  }

  /**
   * Zpracuje část formuláře s vstupným
   */
  function zpracujVstupne() {
    $castka = post($this->klicV);
    if($castka === null) return;
    // rozdělení zadané částky na "včas" a "pozdě"
    $vstupneVcas  = $this->vstupneJeVcas ? $castka : $this->vstupne['sum_cena_nakupni'];
    $vstupnePozde = $this->vstupneJeVcas ? 0 : max(0, $castka - $this->vstupne['sum_cena_nakupni']);
    // funkce pro provedení změn
    $zmeny = function($radek, $cena) {
      if($radek['kusu_uzivatele'] == 0) {
        dbInsert('shop_nakupy', [
          'cena_nakupni'  =>  $cena,
          'id_uzivatele'  =>  $this->u->id(),
          'id_predmetu'   =>  $radek['id_predmetu'],
          'rok'           =>  ROK,
        ]);
      } else {
        dbUpdate('shop_nakupy', [
          'cena_nakupni'  =>  $cena,
        ],[
          'id_uzivatele'  =>  $this->u->id(),
          'id_predmetu'   =>  $radek['id_predmetu'],
          'rok'           =>  ROK,
        ]);
      }
    };
    // zpracování změn
    if($vstupneVcas != $this->vstupne['sum_cena_nakupni']) {
      $zmeny($this->vstupne, $vstupneVcas);
    }
    if($vstupnePozde != $this->vstupnePozde['sum_cena_nakupni']) {
      $zmeny($this->vstupnePozde, $vstupnePozde);
    }
  }

  /**
   * Zpracuje část formuláře se slevami
   */
  function zpracujSlevy()
  {
    if(isset($_POST[$this->klicS]))
    {
      $slevy=$_POST[$this->klicS];
      if(@$slevy['student'])
        $this->u->dejZidli(Z_STUDENT);
      else
        $this->u->vemZidli(Z_STUDENT);
      if(@$slevy['skola'])
        dbQueryS('UPDATE uzivatele_hodnoty SET skola=$0 WHERE id_uzivatele='.$this->u->id(),array($slevy['skola']));
      else
        dbQueryS('UPDATE uzivatele_hodnoty SET skola=NULL WHERE id_uzivatele='.$this->u->id());
      dbQuery('UPDATE uzivatele_hodnoty SET guru=NULL WHERE guru='.$this->u->id()); // reset nováčků
      if(@$slevy['novacek'])
      {
        $novacci=preg_replace('/\s+/','',$slevy['novacek']);
        $novacci=explode(',',$novacci);
        if(count($novacci)>0 && count($novacci)<10)
        {
          $upd='UPDATE uzivatele_hodnoty SET guru='.$this->u->id().' WHERE 0';
          foreach($novacci as $novacek)
            $upd.=' OR id_uzivatele='.(int)$novacek;
          dbQuery($upd);
        }
      }
    }
  }

  /** Zpracuje formulář s jídlem */
  function zpracujJidlo() {
    if(!isset($_POST[self::PN_JIDLO_ZMEN])) return;
    $ma = array_keys( @$this->jidlo['jidloObednano'] ?: array() );
    $chce = array_keys( post(self::PN_JIDLO) ?: array() );
    $this->zmenObjednavku($ma, $chce);
  }

  ////////////////////
  // Protected věci //
  ////////////////////

  /**
   * Vrátí html s výběrem předmetů s každou možností zvlášť a vybírátky + a -
   * @todo nerozlišovat hardcode jídlo, ale např. přidat do db sloupec limit
   *  objednávek nebo něco podobného
   * @todo dodělat ne/dostupnost předmětu do db
   */
  protected function vyberPlusminus($predmety) {
    foreach($predmety as &$p) {
      $name = $this->klicP.'['.$p['id_predmetu'].']';
      $p['cena'] = round($p['cena_aktualni']).'&thinsp;Kč';
      $p['vybiratko'] = '';
      if(!$p['nabizet'] && $p['kusu_uzivatele']) {
        // pouze znovuposlat stávající stav
        $p['vybiratko'] = '<input type="hidden"  name="'.$name.'" value="'.$p['kusu_uzivatele'].'">&#128274;';
      } elseif($p['nabizet'] && $p['typ'] == 4) {
        // checkbox pro jídlo
        $checked = $p['kusu_uzivatele'] ? 'checked' : '';
        $p['vybiratko'] = '<input type="checkbox" name="'.$name.'" value="1" '.$checked.'>';
      } elseif($p['nabizet']) {
        // plusmínus pro předměty
        $p['vybiratko'] = '
          <input type="hidden"  name="'.$name.'" value="'.$p['kusu_uzivatele'].'">
          <a href="#" onclick="return sniz('.$p['id_predmetu'].', this)" class="minus'.($p['kusu_uzivatele']?'':' neaktivni').'">-</a>
          <a href="#" onclick="return prikup('.$p['id_predmetu'].' ,this)" class="plus">+</a>
        ';
      }
    }
    unset($p); //php internal hack, viz dokumentace referencí a foreach

    ob_start();
    ?>
    <script>
      function lokator(id) {
        return $('[name="<?=$this->klicP?>['+id+']"]');
      }
      function prikup(id, tlacitko) {
        var pocet = lokator(id).val();
        pocet++;
        lokator(id).val(pocet);
        $('#pocet'+id).html(pocet);
        if(pocet==1) // po inkrementu
          $(tlacitko).siblings('.minus').removeClass('neaktivni');
        return false;
      }
      function sniz(id, tlacitko) {
        var pocet = lokator(id).val();
        if(pocet>0) {
          pocet--;
          lokator(id).val(pocet);
          $('#pocet'+id).html(pocet);
        }
        if(pocet<=0) // po dekrementu
          $(tlacitko).addClass('neaktivni');
        return false;
      }
    </script>
    <table class="predmety cShopPredmety">
      <?php foreach($predmety as $p) { ?>
      <?php if($p['nabizet'] || $p['kusu_uzivatele']) { ?>
      <tr>
        <td><?=$p['nazev']?></td>
        <td><?=$p['cena']?></td>
        <td>
          <span id="pocet<?=$p['id_predmetu']?>"><?=$p['kusu_uzivatele']?></span>&times;
        </td>
        <td><?=$p['vybiratko']?></td>
      </tr>
      <?php } ?>
      <?php } ?>
    </table>
    <?php
    return ob_get_clean();
  }

  /**
   * Vrátí html kód s výběrem předmětů pomocí selectboxu s automatickým
   * vytvářením dalších boxů pro výběr více kusů
   */
  protected function vyberSelect($predmety) {
    // načtení aktuálně koupených triček
    $koupene = array();
    foreach($predmety as $p) {
      for($i = 0; $i < $p['kusu_uzivatele']; $i++) {
        $koupene[] = $p['id_predmetu'];
      }
    }
    $koupene[] = 0; // plus jedno "default" na závěr
    // tisk boxů
    $out = '';
    $i = 0;
    foreach($koupene as $pid) {
      $out .= '<select name="'.$this->klicT.'['.$i.']">';
      $trikaOut = '';
      $zamceno = '';
      foreach($this->tricka as $t) {
        // nagenerovat výběry triček, případně pokud je aktuální tričko zamčené, nagenerovat jediný výběr zvlášť
        $sel = $t['id_predmetu'] == $pid ? 'selected' : '';
        if($sel || $t['nabizet']) {
          $trikaOut .= '<option value="'.$t['id_predmetu'].'" '.$sel.'>'.$t['nazev'].'</option>';
        }
        if($sel && !$t['nabizet']) {
          $zamceno = '<option value="'.$t['id_predmetu'].'" '.$sel.'>&#128274;'.$t['nazev'].'</option>';
        }
      }
      if(!$zamceno || $pid == 0) {
        $out .= '<option value="0">(žádné tričko)</option>';
      }
      $out .= $zamceno ?: $trikaOut; // pokud je zamčeno, nevypisovat jiné nabídky
      $out .= '</select>';
      $out .= ' '.round($t['cena_aktualni']).'&thinsp;Kč';
      $out .= '<br>';
      $i++;
    }
    return $out;
  }

}
