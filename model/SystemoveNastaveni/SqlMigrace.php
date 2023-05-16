<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Godric\DbMigrations\DbMigrations;
use Godric\DbMigrations\DbMigrationsConfig;
use Symfony\Component\Filesystem\Filesystem;

class SqlMigrace
{
    public function migruj()
    {
        (new Filesystem())->mkdir(ZALOHA_DB_SLOZKA);

        $this->dbMigrations()->run(true);
    }

    private function dbMigrations(): DbMigrations
    {
        return new DbMigrations(new DbMigrationsConfig([
            'connection'          => new \mysqli(
                DBM_SERV,
                DBM_USER,
                DBM_PASS,
                DBM_NAME,
                defined('DBM_PORT')
                    ? constant('DBM_PORT')
                    : 3306
            ),
            'doBackups'           => true,
            'migrationsDirectory' => SQL_MIGRACE_DIR,
            'backupsDirectory'    => ZALOHA_DB_SLOZKA,
        ]));
    }

    public function nejakeMigraceKeSpusteni(): bool
    {
        return $this->dbMigrations()->hasUnappliedMigrations();
    }
}
