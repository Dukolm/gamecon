<?php

// TODO: při pojmenování jako api/aktivity.php z nezámeho důvodu připisuje obsah aktivity.php
// TODO: udělat REST api definice

use Gamecon\Cas\DateTimeCz;
use Gamecon\Aktivita\Aktivita;

$u = Uzivatel::zSession();

// TODO: remove tesing snippet: 
/*
var downloadAsJSON = (storageObj, name= "object") =>{
  const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(storageObj));
  const dlAnchorElem = document.createElement("a");
  dlAnchorElem.setAttribute("href",     dataStr     );
  dlAnchorElem.setAttribute("download", `${name}.json`);
  dlAnchorElem.click();
}

Promise.all(
  [2016, 2017, 2022]
    .map(rok => 
      fetch(`/web/api/aktivityProgram?rok=${rok}`, {method:"POST"})
        .then(x=>x.json())
        .catch(x=>[])
        .then(x=>[rok, x])
      )
  )
  .then(x=>Object.fromEntries(x))
  .then(x=>downloadAsJSON(x, "aktivityProgram"))

fetch("/web/api/aktivityProgram", {method:"POST"}).then(x=>x.text()).then(x=>console.log(x))
*/
// TODO: je potřeba otestovat taky $u->gcPrihlasen() ?
// TODO: tohle nastavení by mělo platit pro všechny php soubory ve složce api
$this->bezStranky(true);
header('Content-type: application/json');
$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

// if ($_SERVER["REQUEST_METHOD"] != "POST") {
//   return;
// }

$res = [];

$rok = array_key_exists("rok", $_GET) ? intval($_GET["rok"], 10) : ROK;

$aktivity = Aktivita::zFiltru(["rok" => $rok]);

foreach ($aktivity as &$a) {
  if (!$a->zacatek()) continue;
  if (!$a->viditelnaPro($u)) continue;

  $zachovat = false;

  $aktivitaRes = [
    'id'        =>  $a->id(),
  ];

  $prihlasen = $u && $a->prihlasen($u);
  if ($u && $prihlasen) {
    $aktivitaRes['prihlasen'] = $prihlasen;
    $zachovat = true;
  }

  $slevaNasobic = $a->slevaNasobic($u);
  if ($slevaNasobic != 1) {
    $aktivitaRes['slevaNasobic'] = $slevaNasobic;
    $zachovat = true;
  }

  $vedu = $u && $u->organizuje($a);
  if ($vedu) {
    $aktivitaRes['vedu'] = $vedu;
    $zachovat = true;
  }

  /*
  $nahradnik = $u && $u->prihlasenJakoNahradnikNa($a);
  if ($nahradnik) {
    $aktivitaRes['nahradnik'] = $nahradnik;
    $zachovat = true;
  }
  */

  if ($zachovat)
    $res[] = $aktivitaRes;
}


echo json_encode($res, $config);
