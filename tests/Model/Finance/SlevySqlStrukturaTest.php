<?php

namespace Gamecon\Tests\Model\Finance;

use Gamecon\Finance\SqlStruktura\SlevySqlStruktura;
use Gamecon\Tests\Model\SqlStrukturaTest;

class SlevySqlStrukturaTest extends SqlStrukturaTest
{
    protected function strukturaClass(): string {
        return SlevySqlStruktura::class;
    }

}
