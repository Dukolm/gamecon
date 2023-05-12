<?php declare(strict_types=1);

namespace Gamecon\Shop;

use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura as SQL;

/**
 * @method static Predmet zId($id, bool $zCache = false)
 */
class Predmet extends \DbObject
{
    protected static $tabulka         = 'shop_predmety';
    protected static $pk              = 'id_predmetu';
    protected static $letosniPredmety = [];

    public static function jeToKostka(string $nazev): bool
    {
        return mb_stripos($nazev, 'Kostka') !== false;
    }

    public static function jeToPlacka(string $nazev): bool
    {
        return mb_stripos($nazev, 'Placka') !== false;
    }

    public static function jeToModre(string $nazev): bool
    {
        return mb_stripos($nazev, 'modré') !== false;
    }

    public static function letosniKostka(int $rocnik): ?static
    {
        return self::letosniPredmet('Kostka', $rocnik);
    }

    private static function letosniPredmet(string $castNazvu, int $rocnik): ?static
    {
        if (!array_key_exists($castNazvu, self::$letosniPredmety)) {
            $typPredmet       = TypPredmetu::PREDMET;
            $castNazvuSql     = dbQv($castNazvu);
            $letosniPredmetId = (int)dbFetchSingle(<<<SQL
SELECT id_predmetu
FROM shop_predmety
WHERE
    -- letošní je ten, která má nejnovější model a v dřívějších letech si ho nikdo neobjednal
    NOT EXISTS(SELECT * FROM shop_nakupy WHERE shop_nakupy.id_predmetu = shop_predmety.id_predmetu AND shop_nakupy.rok < {$rocnik})
    AND typ = {$typPredmet} AND nazev COLLATE utf8_czech_ci LIKE '%{$castNazvuSql}%'
ORDER BY model_rok DESC, cena_aktualni DESC, id_predmetu DESC
LIMIT 1 -- pro jistotu
SQL,
            );
            $letosniPredmet   = $letosniPredmetId
                ? static::zId($letosniPredmetId, true)
                : null;
        }
        return $letosniPredmet;
    }

    public static function letosniPlacka(int $rocnik): ?static
    {
        return self::letosniPredmet('Placka', $rocnik);
    }

    public function kusuVyrobeno(int $kusuVyrobeno = null): int
    {
        if ($kusuVyrobeno !== null) {
            $this->r['kusu_vyrobeno'] = $kusuVyrobeno;
        }
        return (int)$this->r['kusu_vyrobeno'];
    }

    public function nazev(): string
    {
        return (string)$this->r[SQL::NAZEV];
    }

    public function cenaAktualni(): float
    {
        return (float)$this->r[SQL::CENA_AKTUALNI];
    }
}
