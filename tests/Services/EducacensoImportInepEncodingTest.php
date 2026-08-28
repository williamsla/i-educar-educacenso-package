<?php

namespace iEducar\Packages\Educacenso\Tests\Services;

use iEducar\Packages\Educacenso\Services\EducacensoImportInepService;
use PHPUnit\Framework\TestCase;

class EducacensoImportInepEncodingTest extends TestCase
{
    public function testKeepsUtf8Lines(): void
    {
        $this->assertSame('JOÃO DA SILVA', EducacensoImportInepService::toUtf8('JOÃO DA SILVA'));
    }

    public function testConvertsWindows1252LinesToUtf8(): void
    {
        $latin1 = 'JO' . chr(0xE3) . 'O DA SILVA';

        $this->assertSame('JOÃO DA SILVA', EducacensoImportInepService::toUtf8($latin1));
    }
}
