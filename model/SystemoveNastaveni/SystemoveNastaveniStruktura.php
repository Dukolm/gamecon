<?php

declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\SystemoveNastaveni\SqlStruktura\SystemoveNastaveniSqlStruktura;

class SystemoveNastaveniStruktura extends SystemoveNastaveniSqlStruktura
{
    /** @see \Gamecon\SystemoveNastaveni\SystemoveNastaveni::dejSqlNaZaznamyNastaveni */
    public const ID_UZIVATELE = 'id_uzivatele';
    /** @see SystemoveNastaveni::pridejVychoziHodnoty */
    public const VYCHOZI_HODNOTA = 'vychozi_hodnota';
}
