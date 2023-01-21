<?php

// TODO: udělat REST api definice

use Gamecon\Cas\DateTimeCz;

$u = Uzivatel::zSession();

$this->bezStranky(true);
header('Content-type: application/json');
$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

if ($_SERVER["REQUEST_METHOD"] != "POST") {
  return;
}

$res = [];

if ($u) {
  $res["prihlasen"] = true;
  $res["organizator"] = $u->jeOrganizator();
  
}

echo json_encode($res, $config);
