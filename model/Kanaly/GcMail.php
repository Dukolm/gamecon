<?php

namespace Gamecon\Kanaly;

use \Gamecon\Cas\DateTimeCz;
use Gamecon\Kanaly\Exceptions\ChybiEmailoveNastaveni;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

/**
 * Třída pro sestavování mailu
 */
class GcMail
{

    private string $text;
    private array $adresati = [];
    private string $predmet = '';
    private string $prilohaSoubor = '';
    private string $prilohaNazev = '';

    public function __construct(string $text = '') {
        $this->text = $text;
    }

    public function adresat(string $adresat): self {
        $this->adresati[] = $adresat;
        return $this;
    }

    public function adresati(array $adresati): self {
        $this->adresati = $adresati;
        return $this;
    }

    /**
     * @param string $text utf-8 řetězec
     * @return string enkódovaný řetězec pro použití v hlavičce
     */
    private static function encode($text) {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }

    /**
     * Odešle sestavenou zprávu.
     * @return bool jestli se zprávu podařilo odeslat
     */
    public function odeslat() {
        $mail = (new Email())
            ->from('GameCon <info@gamecon.cz>')
            ->subject($this->predmet)
            ->text($this->text);

        $odeslano = false;

        $adresatiDoSouboru = $this->adresatiDoSouboru();
        if ($adresatiDoSouboru) {
            $mail->addBcc(...$adresatiDoSouboru);
            $odeslano = $this->zalogovatDo(MAILY_DO_SOUBORU, $mail->toString()) || $odeslano;
        }
        $adresatiPovoleniPodleRoli = $this->adresatiPovoleniPodleRoli();
        if ($adresatiPovoleniPodleRoli) {
            $mail->addBcc(...$adresatiPovoleniPodleRoli);
            if ($this->prilohaSoubor) {
                // do souboru přílohy dávat nebudeme
                $mail->attachFromPath($this->prilohaSoubor, $this->prilohaNazev);
            }
            $mailer = new Mailer($this->mailerTransport());
            $mailer->send($mail);
            $odeslano = true;
        }
        return $odeslano;
    }

    private function mailerTransport(): Transport\TransportInterface {
        if (!defined('MAILER_DSN')) {
            /**
             * Návod @link https://symfony.com/doc/current/mailer.html#transport-setup
             * SMTP server @link https://client.wedos.com/webhosting/webhost-detail.html?id=16779 'Adresy služeb' dole
             * Pro Wedos SMTP použij port 587 (TLS), protože SSL z PHP z Wedos serveru nefunguje.
             */
            throw new ChybiEmailoveNastaveni(
                "Pro odeslání emailu je třeba nastavit konstantu 'MAILER_DSN'"
            );
        }
        return Transport::fromDsn(MAILER_DSN);
    }

    private function adresatiDoSouboru(): array {
        if (!defined('MAILY_DO_SOUBORU') || !MAILY_DO_SOUBORU) {
            return [];
        }
        return array_diff($this->adresati, $this->adresatiPovoleniPodleRoli());
    }

    private function adresatiPovoleniPodleRoli(): array {
        if (!defined('MAILY_DO_SOUBORU') || !MAILY_DO_SOUBORU) {
            return $this->adresati;
        }
        if (!defined('MAILY_ROLIM') || !MAILY_ROLIM) {
            return [];
        }
        $povoleniPodleRoli = [];
        foreach ($this->adresati as $adresat) {
            if (!preg_match('~(?<email>[^@\s<>]+@[^@\s<>]+)~', $adresat, $matches)) {
                continue;
            }
            $email    = $matches['email'];
            $uzivatel = \Uzivatel::zMailu($email);
            if (!$uzivatel) {
                continue;
            }
            foreach ((array)MAILY_ROLIM as $role) {
                if ($uzivatel->maRoli($role)) {
                    $povoleniPodleRoli[] = $adresat;
                    break;
                }
            }
        }
        return $povoleniPodleRoli;
    }

    public function predmet(string $predmet): self {
        $this->predmet = $predmet;
        return $this;
    }

    public function text(string $text): self {
        $this->text = $text;
        return $this;
    }

    private function zalogovatDo(string $soubor, string $obsah) {
        return file_put_contents($soubor, $obsah, FILE_APPEND);
    }

    public function dejText(): string {
        return $this->text;
    }

    public function dejPredmet(): string {
        return $this->predmet;
    }

    public function prilohaSoubor(string $cesta): self {
        $this->prilohaSoubor = $cesta;
        return $this;
    }

    public function prilohaNazev(string $nazev): self {
        $this->prilohaNazev = $nazev;
        return $this;
    }

}
