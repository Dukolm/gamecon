<?php

/**
 * Kód starající o přihlášení uživatele a výběr uživatele pro práci
 */
if (!empty($_GET['update_code'])) {
    exec('git pull 2>&1', $output, $returnValue);
    print_r($output);
    exit($returnValue);
}
// Přihlášení
$u = null;
if (post('loginNAdm') && post('hesloNAdm')) {
    $pravePrihlaseny = Uzivatel::prihlas(post('loginNAdm'), post('hesloNAdm'));
    if (!$pravePrihlaseny) {
        chyba("Chybné přihlašovací jméno nebo heslo");
    }
    back();
}
$u = Uzivatel::zSession();
if (post('odhlasNAdm')) {
    if ($u) {
        $u->odhlas();
    }
    back();
}

// Výběr uživatele pro práci
$uPracovni = null;
if (post('vybratUzivateleProPraci')) {
    $u = Uzivatel::prihlasId(post('id'), Uzivatel::UZIVATEL_PRACOVNI);
    back();
}

if (get('pracovni_uzivatel')) {
    $u = Uzivatel::prihlasId(get('pracovni_uzivatel'), Uzivatel::UZIVATEL_PRACOVNI);
    back();
}

$uPracovni = Uzivatel::zSession(Uzivatel::UZIVATEL_PRACOVNI);
if (post('zrusitUzivateleProPraci')) {
    Uzivatel::odhlasKlic(Uzivatel::UZIVATEL_PRACOVNI);
    back();
}

if (post('prihlasitSeJakoUzivatel')) {
    try {
        if ($u->jeSuperAdmin() || ($u->jeInfopultak() && Uzivatel::zIdUrcite(post('id'))->jeVypravec())) {
            $u = Uzivatel::prihlasId(post('id'));
            back($u->jeVypravec()
                ? $u->mojeAktivityAdminUrl()
                : null
            );
        }
    } catch (\Gamecon\Exceptions\UzivatelNenalezen $uzivatelNenalezen) {
        chyba($uzivatelNenalezen->getMessage());
    }
}
