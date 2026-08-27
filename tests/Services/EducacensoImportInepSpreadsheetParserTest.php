<?php

namespace iEducar\Packages\Educacenso\Tests\Services;

use iEducar\Packages\Educacenso\Enums\EducacensoInepImportLayout;
use iEducar\Packages\Educacenso\Exception\ImportInepException;
use iEducar\Packages\Educacenso\Services\EducacensoImportInepSpreadsheetParser;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

class EducacensoImportInepSpreadsheetParserTest extends TestCase
{
    public function testParsesStudentSpreadsheet(): void
    {
        $file = $this->xlsx('alunos.xlsx', [
            ['Censo Escolar 2026 – Educacenso'],
            ['Relação de alunos(as) da escola'],
            ['Código da escola:', '27011704'],
            ['Nome da escola:', 'ESCOLA MUNICIPAL GETULIO VARGAS'],
            ['Identificação única', 'Nome', 'Data de nascimento', 'CPF', 'Código da turma', 'Nome da turma'],
            ['116687283382', 'LUIZ ALEXANDRE DOS SANTOS SILVA', '21/03/1981', '06661029455', '37731938', 'EJA - 1º SEGMENTO'],
        ]);

        $parsed = EducacensoImportInepSpreadsheetParser::parse($file, EducacensoInepImportLayout::SPREADSHEET_STUDENT);

        $this->assertSame(EducacensoInepImportLayout::SPREADSHEET_STUDENT, $parsed['layout']);
        $this->assertSame('27011704', $parsed['school_inep']);
        $this->assertSame('ESCOLA MUNICIPAL GETULIO VARGAS', $parsed['school_name']);
        $this->assertSame(2026, $parsed['year']);
        $this->assertCount(1, $parsed['rows']);
        $this->assertSame('116687283382', $parsed['rows'][0]['inep']);
        $this->assertSame('LUIZ ALEXANDRE DOS SANTOS SILVA', $parsed['rows'][0]['name']);
        $this->assertSame('06661029455', $parsed['rows'][0]['cpf']);
        $this->assertSame('21/03/1981', $parsed['rows'][0]['birth_date']);
        $this->assertSame('37731938', $parsed['rows'][0]['class_inep']);
        $this->assertSame('EJA - 1º SEGMENTO', $parsed['rows'][0]['class_name']);
    }

    public function testParsesEmployeeSpreadsheet(): void
    {
        $file = $this->xlsx('profissionais.xlsx', [
            ['Censo Escolar 2026 – Educacenso'],
            ['Relação de Profissionais escolares em sala de aula da escola'],
            ['Código da escola:', '27011704'],
            ['Nome da escola:', 'ESCOLA MUNICIPAL GETULIO VARGAS'],
            ['Identificação única', 'Nome', 'Data de Nascimento', 'CPF', 'Código da turma', 'Nome da Turma'],
            ['112275230504', 'JULIENE IZIDIO DA SILVA', '30/07/1987', '6934916476', '40447910', '3º ANO'],
        ]);

        $parsed = EducacensoImportInepSpreadsheetParser::parse($file, EducacensoInepImportLayout::SPREADSHEET_EMPLOYEE);

        $this->assertSame(EducacensoInepImportLayout::SPREADSHEET_EMPLOYEE, $parsed['layout']);
        $this->assertSame('112275230504', $parsed['rows'][0]['inep']);
        $this->assertSame('JULIENE IZIDIO DA SILVA', $parsed['rows'][0]['name']);
        $this->assertSame('6934916476', $parsed['rows'][0]['cpf']);
        $this->assertSame('40447910', $parsed['rows'][0]['class_inep']);
    }

    public function testRejectsStudentSheetWhenEmployeeLayoutIsSelected(): void
    {
        $file = $this->xlsx('alunos.xlsx', [
            ['Censo Escolar 2026 – Educacenso'],
            ['Relação de alunos(as) da escola'],
            ['Código da escola:', '27011704'],
            ['Nome da escola:', 'ESCOLA TESTE'],
            ['Identificação única', 'Nome'],
            ['116687283382', 'ALUNO TESTE'],
        ]);

        $this->expectException(ImportInepException::class);

        EducacensoImportInepSpreadsheetParser::parse($file, EducacensoInepImportLayout::SPREADSHEET_EMPLOYEE);
    }

    /**
     * @param list<list<string>> $rows
     */
    private function xlsx(string $name, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = sys_get_temp_dir() . '/' . uniqid('inep_', true) . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
