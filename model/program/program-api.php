<?php

class ProgramApi implements JsPhpApi {

  /**
   * Převede aktivitu $a na formát, jak má vypadat v API.
   */
  private function aktivitaFormat($a) {
    $r = $a->rawDb();

    return [
      'id'            =>  (int) $a->id(),
      'nazev'         =>  $a->nazev(),
      'linie'         =>  (int) $a->typId(),
      'zacatek'       =>  $a->zacatek()->formatJs(),
      'konec'         =>  $a->konec()->formatJs(),
      'organizatori'  =>  array_map(function($o) { return $o->jmenoNick(); }, $a->organizatori()),
      'stitky'        =>  array_map(function($t) { return (string) $t; }, $a->tagy()),
      'prihlaseno_m'  =>  $a->prihlasenoMuzu(),
      'prihlaseno_f'  =>  $a->prihlasenoZen(),
      'otevreno_prihlasovani' => $a->prihlasovatelna(),
      'vDalsiVlne'    =>  $a->vDalsiVlne(),
      'probehnuta'    =>  $a->probehnuta(),
      'organizuje'    =>  rand(0, 99) < 2, // TODO test data
      'prihlasen'     =>  rand(0, 99) < 5, // TODO test data
      'tymova'        =>  (bool) $a->teamova(),
      'popis_kratky'  =>  rand(0, 99) >= 10 ? 'Naprosto skvělá záležitost. To chceš.' : 'Sračka.', // TODO test data

      // TODO údaje načítané přímo z DB řádku, smazat nebo nějak převést
      'kapacita_m'    =>  (int) $r['kapacita_m'],
      'kapacita_f'    =>  (int) $r['kapacita_f'],
      'kapacita_u'    =>  (int) $r['kapacita'],
    ];
  }

  /**
   * Vrátí pole všech aktivit v programu. Formát aktivit viz aktivitaFormat.
   */
  function aktivity() {
    // TODO aktuální rok
    // TODO listovat tech. aktivity jenom tomu, kdo je může vidět

    $aktivity = Aktivita::zProgramu();

    return array_map(
      [$this, 'aktivitaFormat'],
      array_values($aktivity->getArrayCopy())
    );
  }

  /**
   * Vrátí detail aktivity.
   */
  function detail($aktivitaId) {
    $necoSpocitat = 'něco spočítat zde.';

    return [
      'popis'     =>  rand(0,1) ? 'Moc dobrá aktivita. Doporučuji.' : 'Sračka.',
      'mistnost'  =>  ['nazev' => 'Holobyt na AB/B/2 v kukani.', 'dvere' => 123],
      'hraci'     =>  ['Pepa', 'Jarin'],
    ];
  }

  /**
   * Přihlásí aktuálního uživatele na aktivitu.
   */
  function prihlas($aktivitaId) {
    throw new Chyba('v daném čase už máš jinou aktivitu.');
  }

  /**
   * Jenom metoda s víc parametrama na test.
   */
  function test($foo, $bar, $baz) {
    return " $foo $bar $baz ";
  }

}
