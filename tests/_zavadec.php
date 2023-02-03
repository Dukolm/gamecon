<?php

namespace Gamecon\Tests;

use Gamecon\Tests\Db\DbTest;
use Gamecon\Tests\Db\DbWrapper;
use Godric\DbMigrations\DbMigrationsConfig;
use Godric\DbMigrations\DbMigrations;

require_once __DIR__ . '/../nastaveni/verejne-nastaveni-tests.php';
require_once __DIR__ . '/../nastaveni/zavadec-zaklad.php';

// příprava databáze
$connection = dbConnect(false);
dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', DB_NAME));
dbQuery(sprintf('CREATE DATABASE IF NOT EXISTS `%s` COLLATE "utf8_czech_ci"', DB_NAME));
dbQuery(sprintf('USE `%s`', DB_NAME));
// naimportujeme databázi s už proběhnutými staršími migracemi
(new \MySQLImport($connection))->load(__DIR__ . '/Db/data/localhost-2023_01_27_11_18_45-dump.sql');

(new DbMigrations(new DbMigrationsConfig([
    'connection'          => dbConnect(), // předpokládá se, že spojení pro testy má administrativní práva
    'migrationsDirectory' => __DIR__ . '/../migrace',
    'doBackups'           => false,
])))->run();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

DbTest::setConnection(new DbWrapper());

register_shutdown_function(static function () {
    dbQuery(sprintf('DROP DATABASE IF EXISTS `%s`', DB_NAME));
});
