<?php

namespace iEducar\Packages\Educacenso\Tests\Services;

use App\Models\StudentInep;
use Database\Factories\LegacyStudentFactory;
use iEducar\Packages\Educacenso\Database\Factories\EducacensoIdentificationImportFactory;
use iEducar\Packages\Educacenso\Exception\ImportIdentificationException;
use iEducar\Packages\Educacenso\Services\EducacensoImportIdentificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EducacensoImportIdentificationServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function testParseFileRequiresNineFields(): void
    {
        $this->expectException(ImportIdentificationException::class);

        $content = "123|11111111111||||||||\n";
        $file = UploadedFile::fake()->createWithContent('ident.txt', $content);

        EducacensoImportIdentificationService::parseFile($file);
    }

    public function testParseFileRequiresInepInFieldNine(): void
    {
        $this->expectException(ImportIdentificationException::class);

        $content = "123|11111111111||||||||\n";
        $fields = array_fill(0, 9, '');
        $fields[0] = '123';
        $content = implode('|', $fields) . "\n";
        $file = UploadedFile::fake()->createWithContent('ident.txt', $content);

        EducacensoImportIdentificationService::parseFile($file);
    }

    public function testImportServiceUpdatesStudentInep(): void
    {
        $student = LegacyStudentFactory::new()->create();
        $inep = '123456789012';
        $fields = [
            (string) $student->getKey(),
            '11111111111',
            '',
            'JOAO DA SILVA',
            '01/01/2010',
            'MARIA DA SILVA',
            '',
            '3550308',
            $inep,
        ];

        $import = EducacensoIdentificationImportFactory::new()->create();

        (new EducacensoImportIdentificationService($import, [$fields]))->execute();

        $this->assertDatabaseHas('modules.educacenso_cod_aluno', [
            'cod_aluno' => $student->getKey(),
            'cod_aluno_inep' => $inep,
        ]);

        $import->refresh();
        $this->assertSame(1, $import->imported_count);
        $this->assertSame(0, $import->skipped_count);
    }

    public function testImportServiceSkipsLineWithoutInep(): void
    {
        $student = LegacyStudentFactory::new()->create();
        $fields = [
            (string) $student->getKey(),
            '11111111111',
            '',
            'JOAO DA SILVA',
            '01/01/2010',
            'MARIA DA SILVA',
            '',
            '3550308',
            '',
        ];

        $import = EducacensoIdentificationImportFactory::new()->create();

        (new EducacensoImportIdentificationService($import, [$fields]))->execute();

        $this->assertNull(StudentInep::query()->where('cod_aluno', $student->getKey())->first());
        $import->refresh();
        $this->assertSame(0, $import->imported_count);
        $this->assertSame(1, $import->skipped_count);
    }

    public function testImportServiceUpdatesStudentInepByCpfWhenCodAlunoDoesNotExist(): void
    {
        $student = LegacyStudentFactory::new()->create();
        $student->individual->update(['cpf' => '98765432100']);
        $inep = '123456789012';
        $fields = [
            '999999',
            '98765432100',
            '',
            'JOAO DA SILVA',
            '01/01/2010',
            'MARIA DA SILVA',
            '',
            '3550308',
            $inep,
        ];

        $import = EducacensoIdentificationImportFactory::new()->create();

        (new EducacensoImportIdentificationService($import, [$fields]))->execute();

        $this->assertDatabaseHas('modules.educacenso_cod_aluno', [
            'cod_aluno' => $student->getKey(),
            'cod_aluno_inep' => $inep,
        ]);

        $import->refresh();
        $this->assertSame(1, $import->imported_count);
        $this->assertSame(0, $import->skipped_count);
    }

    public function testImportServicePrefersCodAlunoOverCpf(): void
    {
        $student = LegacyStudentFactory::new()->create();
        $student->individual->update(['cpf' => '98765432100']);
        $otherStudent = LegacyStudentFactory::new()->create();
        $otherStudent->individual->update(['cpf' => '11111111111']);
        $inep = '123456789012';
        $fields = [
            (string) $student->getKey(),
            '11111111111',
            '',
            'JOAO DA SILVA',
            '01/01/2010',
            'MARIA DA SILVA',
            '',
            '3550308',
            $inep,
        ];

        $import = EducacensoIdentificationImportFactory::new()->create();

        (new EducacensoImportIdentificationService($import, [$fields]))->execute();

        $this->assertDatabaseHas('modules.educacenso_cod_aluno', [
            'cod_aluno' => $student->getKey(),
            'cod_aluno_inep' => $inep,
        ]);
        $this->assertNull(StudentInep::query()->where('cod_aluno', $otherStudent->getKey())->first());
    }

    public function testImportServiceSkipsWhenInepBelongsToAnotherStudent(): void
    {
        $student = LegacyStudentFactory::new()->create();
        $otherStudent = LegacyStudentFactory::new()->create();
        $inep = '123456789012';

        StudentInep::query()->create([
            'cod_aluno' => $otherStudent->getKey(),
            'cod_aluno_inep' => $inep,
        ]);

        $fields = [
            (string) $student->getKey(),
            '11111111111',
            '',
            'JOAO DA SILVA',
            '01/01/2010',
            'MARIA DA SILVA',
            '',
            '3550308',
            $inep,
        ];

        $import = EducacensoIdentificationImportFactory::new()->create();

        (new EducacensoImportIdentificationService($import, [$fields]))->execute();

        $this->assertNull(StudentInep::query()->where('cod_aluno', $student->getKey())->first());
        $import->refresh();
        $this->assertSame(0, $import->imported_count);
        $this->assertSame(1, $import->skipped_count);
    }
}
