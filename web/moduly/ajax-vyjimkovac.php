<?php

$this->bezStranky(true);

if (parse_url($_SERVER['HTTP_REFERER'] ?? null, PHP_URL_HOST) === parse_url(URL_ADMIN, PHP_URL_HOST)) {
    // povolit hlášení JS chyb z adminu, který je na jiné sub-doméně
    header('Access-Control-Allow-Origin: ' . URL_ADMIN);
}

(new \Gamecon\Vyjimkovac\Vyjimkovac(SPEC . '/chyby.sqlite'))->jsZpracuj();
