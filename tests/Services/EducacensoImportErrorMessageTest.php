<?php

namespace iEducar\Packages\Educacenso\Tests\Services;

use iEducar\Packages\Educacenso\Exception\ImportInepException;
use iEducar\Packages\Educacenso\Services\EducacensoImportErrorMessage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EducacensoImportErrorMessageTest extends TestCase
{
    public function testKeepsImportInepExceptionMessage(): void
    {
        $exception = new ImportInepException('A planilha "alunos.xlsx" é referente ao Censo Escolar 2025.');

        $this->assertSame(
            'A planilha "alunos.xlsx" é referente ao Censo Escolar 2025.',
            EducacensoImportErrorMessage::fromThrowable($exception)
        );
    }

    public function testTranslatesNumericSqlError(): void
    {
        $exception = new RuntimeException('SQLSTATE[22P02]: Invalid text representation: 7 ERROR: invalid input syntax for type numeric: "066.610.294-55"');

        $this->assertSame(
            'Há um valor numérico inválido no arquivo (INEP, CPF ou código da turma). Verifique a planilha e tente novamente.',
            EducacensoImportErrorMessage::fromThrowable($exception)
        );
    }

    public function testUsesGenericMessageForUnexpectedErrors(): void
    {
        $this->assertSame(
            EducacensoImportErrorMessage::GENERIC,
            EducacensoImportErrorMessage::fromThrowable(new RuntimeException('Call to a member function foo() on null'))
        );
    }
}
