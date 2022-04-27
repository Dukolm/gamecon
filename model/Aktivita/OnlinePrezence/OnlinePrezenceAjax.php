<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaPrezence;
use Gamecon\Aktivita\PosledniZmenyStavuPrihlaseni;
use Gamecon\Aktivita\ZmenaStavuPrihlaseni;

class OnlinePrezenceAjax
{
    public const POSLEDNI_ZMENY = 'posledni-zmeny';

    public static function urlPosledniZmenyPrihlaseni(): string {
        return getCurrentUrlWithQuery(['ajax' => 1, 'akce' => self::POSLEDNI_ZMENY]);
    }

    /**
     * @var OnlinePrezenceHtml
     */
    private $onlinePrezenceHtml;

    public function __construct(OnlinePrezenceHtml $onlinePrezenceHtml) {
        $this->onlinePrezenceHtml = $onlinePrezenceHtml;
    }

    public function odbavAjax(\Uzivatel $editujici) {
        if (!post('ajax') && !get('ajax')) {
            return false;
        }

        if (get('akce') === self::POSLEDNI_ZMENY) {
            $this->ajaxDejPosledniZmeny(post('zname_zmeny_prihlaseni'));
            return true;
        }

        if (post('akce') === 'uzavrit') {
            $this->ajaxUzavritAktivitu(
                (int)post('id'),
                ['maPravoNaZmenuHistorieAktivit' => $editujici->maPravoNaZmenuHistorieAktivit()]
            );
            return true;
        }

        if (post('akce') === 'zmenitUcastnika') {
            $dorazil = post('dorazil');
            if ($dorazil !== null) {
                $dorazil = (bool)$dorazil;
            }
            $this->ajaxZmenitUcastnikaAktivity((int)post('idUzivatele'), (int)post('idAktivity'), $dorazil);
            return true;
        }

        if (post('prezenceAktivity')) {
            $this->ajaxUlozPrezenci((int)post('prezenceAktivity'), array_keys(post('dorazil') ?: []));
            return true;
        }

        if (get('omnibox')) {
            $this->ajaxOmnibox(
                (int)get('idAktivity'),
                (string)get('term') ?: '',
                (array)get('dataVOdpovedi') ?: [],
                get('labelSlozenZ')
            );
            return true;
        }

        $this->echoErrorJson('Neznámý AJAX požadavek');
        return true;
    }

    /**
     * @param scalar[][]|scalar[][][] $posledniZnameZmenyPrihlaseniNaAktivity
     * @return void
     */
    private function ajaxDejPosledniZmeny(array $posledniZnameZmenyPrihlaseniNaAktivity) {
        $zmenyProJson = [];
        foreach ($posledniZnameZmenyPrihlaseniNaAktivity as $posledniZnameZmenyPrihlaseniNaAktivitu) {
            /** struktura dat viz admin/files/online-prezence-posledni-zname-zmeny-prihlaseni.js */
            $posledniZnameZmenyStavuPrihlaseni = new PosledniZmenyStavuPrihlaseni((int)$posledniZnameZmenyPrihlaseniNaAktivitu['id_aktivity']);
            foreach ($posledniZnameZmenyPrihlaseniNaAktivitu['ucastnici'] ?? [] as $posledniZnamaZmenaPrihlaseni) {
                $zmenaStavuPrihlaseni = new ZmenaStavuPrihlaseni(
                    (int)$posledniZnamaZmenaPrihlaseni['id_uzivatele'],
                    new \DateTimeImmutable($posledniZnamaZmenaPrihlaseni['cas_zmeny_prihlaseni']),
                    $posledniZnamaZmenaPrihlaseni['stav_prihlaseni'],
                );
                $posledniZnameZmenyStavuPrihlaseni->addPosledniZmenaStavuPrihlaseni($zmenaStavuPrihlaseni);
            }
            $nejnovejsiZmenyStavuPrihlaseni = AktivitaPrezence::dejPosledniZmeny($posledniZnameZmenyStavuPrihlaseni);
            foreach ($nejnovejsiZmenyStavuPrihlaseni->zmenyStavuPrihlaseni() as $zmenaStavuPrihlaseni) {
                $zmenyProJson[] = [
                    'id_aktivity' => $nejnovejsiZmenyStavuPrihlaseni->getIdAktivity(),
                    'id_uzivatele' => $zmenaStavuPrihlaseni->idUzivatele(),
                    'cas_zmeny' => $zmenaStavuPrihlaseni->casZmeny()->format(DATE_ATOM),
                    'stav_prihlaseni' => $zmenaStavuPrihlaseni->stavPrihlaseni(),
                ];
            }
        }
        $this->echoJson(['zmeny' => $zmenyProJson]);
    }

    private function ajaxUzavritAktivitu(int $idAktivity, array $dataPriUspechu) {
        $aktivita = Aktivita::zId($idAktivity);
        if (!$aktivita) {
            $this->echoErrorJson('Chybné ID aktivity ' . $idAktivity);
            return;
        }
        $aktivita->ulozPrezenci($aktivita->prihlaseni());
        $aktivita->zamci();
        $aktivita->uzavri();
        $aktivita->refresh();

        $this->echoJson(
            array_merge(
                [
                    'zamcena' => $aktivita->zamcena(),
                    'uzavrena' => $aktivita->uzavrena(),
                ],
                $dataPriUspechu
            )
        );
    }

    private function echoErrorJson(string $error): void {
        header("HTTP/1.1 400 Bad Request");
        $this->echoJson(['errors' => [$error]]);
    }

    private function echoJson(array $data): void {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }

    private function ajaxZmenitUcastnikaAktivity(int $idUzivatele, int $idAktivity, ?bool $dorazil) {
        $ucastnik = \Uzivatel::zId($idUzivatele);
        $aktivita = Aktivita::zId($idAktivity);
        if (!$ucastnik || !$aktivita || $dorazil === null) {
            $this->echoErrorJson('Chybné ID účastníka nebo aktivity nebo chybejici priznak zda dorazil');
            return;
        }
        if ($dorazil) {
            $aktivita->ulozZeDorazil($ucastnik);
        } else if (!$aktivita->zrusZeDorazil($ucastnik)) {
            $this->echoErrorJson("Nepodařilo se zrušit účastníka {$ucastnik->jmenoNick()} z aktivity {$aktivita->nazev()}");
            return;
        }
        /** Abychom mměli nová data pro @see Aktivita::dorazilJakoCokoliv */
        $aktivita->refresh();

        $this->echoJson(['prihlasen' => $aktivita->dorazilJakoCokoliv($ucastnik)]);
    }

    private function ajaxUlozPrezenci(int $idAktivity, array $idDorazivsich) {
        $aktivita = Aktivita::zId($idAktivity);
        if (!$aktivita) {
            $this->echoErrorJson('Chybné ID aktivity' . $idAktivity);
            return;
        }
        $dorazili = \Uzivatel::zIds($idDorazivsich);
        $aktivita->ulozPrezenci($dorazili);

        $this->echoJson(['aktivita' => $aktivita->rawDb(), 'doazili' => $dorazili]);
    }

    private function ajaxOmnibox(
        int    $idAktivity,
        string $term,
        array  $dataVOdpovedi,
        ?array $labelSlozenZ
    ) {
        $aktivita = Aktivita::zId($idAktivity);
        if (!$aktivita) {
            $this->echoErrorJson('Chybné ID aktivity ' . $idAktivity);
            return;
        }
        $omniboxData = omnibox(
            $term,
            true,
            $dataVOdpovedi,
            $labelSlozenZ,
            array_map(
                static function (\Uzivatel $prihlaseny) {
                    return (int)$prihlaseny->id();
                }, $aktivita->prihlaseni()
            ),
            true,
            1 // znaky ovladame v JS pres minLength, v PHP uz to omezovat nechceme
        );
        foreach ($omniboxData as &$prihlasenyUzivatelOmnibox) {
            $prihlasenyUzivatel = \Uzivatel::zId($prihlasenyUzivatelOmnibox['value']);
            if (!$prihlasenyUzivatel) {
                continue;
            }
            $ucastnikHtml = $this->onlinePrezenceHtml->sestavHmlUcastnikaAktivity(
                $prihlasenyUzivatel,
                $aktivita,
                true /* jenom zobrazeni - skutečné uložení, že dorazil, řešíme už po vybrání uživatele z omniboxu, což je ještě před vykreslením účastníka */,
                false
            );
            $prihlasenyUzivatelOmnibox['html'] = $ucastnikHtml;
        }
        unset($prihlasenyUzivatelOmnibox);

        $this->echoJson($omniboxData);
    }
}
