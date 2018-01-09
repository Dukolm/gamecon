<?php

require_once __DIR__ . '/program-api.php';

class Program {

  private
    $api,
    $cacheSouboru,
    $jsElementId = 'cProgramElement', // TODO v případě použití více instancí řešit příslušnost k instancím
    $jsPromenna = 'cProgramPromenna',
    $jsObserveri = [],
    $uzivatel;

  function __construct(?Uzivatel $uzivatel) {
    $this->uzivatel = $uzivatel;

    $this->cacheSouboru = new PerfectCache(CACHE . '/sestavene', URL_CACHE . '/sestavene');
    $this->cacheSouboru->nastav('reactVProhlizeci', REACT_V_PROHLIZECI);
    $this->cacheSouboru->nastav('babelBinarka', BABEL_BINARKA);
    $this->cacheSouboru->pridejReact(__DIR__ . '/*.jsx');

    // TODO přidat do elementu něco jako `class=cProgramCssClass` a v css
    // souboru pak dávat `.cProgramCssClass něco {`, nebo to celé obalit
    // pomocí lessu
    $this->cacheSouboru->pridejCss(__DIR__ . '/program.css');

    $this->api = new JsPhpApiHandler(new ProgramApi($this->uzivatel));
  }

  /**
   * @todo toto by mohla být statická metoda (pro případ více programů v
   * stránce), ovšem může být problém s více komponentami vkládajícími
   * opakovaně react a s více daty (např. jiné aktivity pro dvě instance
   * programu)
   */
  function htmlHlavicky() {
    return $this->cacheSouboru->htmlHlavicky();
  }

  function htmlObsah() {
    return
      '<div id="'.$this->jsElementId.'"></div>' .
      $this->jsData() .
      $this->jsRender();
  }

  private function jsData() {
    return '
      <script>
        var '.$this->jsPromenna.' = {
          "aktivity": '.$this->api->zavolej('aktivity')->jsonObsah().',
          "linie": '.$this->jsonLinie().',
          "notifikace": '.$this->jsonNotifikace().',
          "uzivatelPrihlasen": '.json_encode((bool) $this->uzivatel).',
          "uzivatelPohlavi": '.json_encode($this->uzivatel ? $this->uzivatel->pohlavi() : null).'
        }
      </script>
    ';
  }

  private function jsRender() {
    return $this->cacheSouboru->inlineCekejNaBabel('
      ReactDOM.render(
        React.createElement(Program, {
          data: '.$this->jsPromenna.',
          api: '.$this->api->jsApiObjekt($this->jsPromenna).'
        }),
        document.getElementById("'.$this->jsElementId.'")
      )
    ');
  }

  private function jsonLinie() {
    $q = dbQuery('
      SELECT
        t.id_typu as "id",
        t.typ_1pmn as "nazev",
        t.poradi
      FROM akce_typy t
    ');

    return json_encode($q->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
  }

  private function jsonNotifikace() {
    return '[' . implode(',', $this->jsObserveri) . ']';
  }

  function zaregistrujJsObserver($nazevFunkce) {
    $this->jsObserveri[] = $nazevFunkce;
  }

  function zpracujAjax() {
    $this->api->zpracujVolani();
  }

}
