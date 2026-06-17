<?php

namespace iEducar\Packages\Educacenso\Tests\Layout\Export;

use iEducar\Packages\Educacenso\Layout\Export\Identification\IdentificationFileBuilder;
use PHPUnit\Framework\TestCase;

class IdentificationFileBuilderTest extends TestCase
{
    public function testBuildContentDoesNotAddTrailingBlankLine(): void
    {
        $content = IdentificationFileBuilder::buildContent([
            ['1' => '10', '2' => '11111111111', '9' => ''],
            ['1' => '11', '2' => '22222222222', '9' => ''],
        ]);

        $this->assertSame(
            "10|11111111111|||||||\r\n11|22222222222|||||||",
            $content
        );
        $this->assertFalse(str_ends_with($content, "\r\n\r\n"));
        $this->assertSame(2, substr_count($content, "\r\n") + 1);
    }

    public function testBuildFileNameUsesOnlyAllowedCharacters(): void
    {
        $this->assertSame('ident_10_2026.txt', IdentificationFileBuilder::buildFileName(10, 2026));
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+\.txt$/', IdentificationFileBuilder::buildFileName(10, 2026));
    }
}
