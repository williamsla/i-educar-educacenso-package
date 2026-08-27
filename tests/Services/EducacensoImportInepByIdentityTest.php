<?php

namespace iEducar\Packages\Educacenso\Tests\Services;

use App\Models\SchoolClassInep;
use App\Models\StudentInep;
use Database\Factories\LegacyGradeFactory;
use Database\Factories\LegacyInstitutionFactory;
use Database\Factories\LegacyRegistrationFactory;
use Database\Factories\LegacySchoolClassFactory;
use Database\Factories\LegacySchoolFactory;
use Database\Factories\LegacySchoolGradeFactory;
use Database\Factories\LegacyStudentFactory;
use Database\Factories\SchoolInepFactory;
use iEducar\Packages\Educacenso\Database\Factories\EducacensoInepImportFactory;
use iEducar\Packages\Educacenso\Services\EducacensoInepIdentityMatcher;
use iEducar\Packages\Educacenso\Services\EducacensoImportInepService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EducacensoImportInepByIdentityTest extends TestCase
{
    use DatabaseTransactions;

    public function testUpdatesStudentInepByCpfWhenInternalIdIsEmpty(): void
    {
        $student = LegacyStudentFactory::new()->create();
        $student->individual->update(['cpf' => '18173448469']);
        $import = EducacensoInepImportFactory::new()->create(['year' => 2025]);

        $data = $this->schoolLines(
            studentInep: '185477950497',
            cpf: '18173448469',
            name: 'MAISA KELLY GOMES DA SILVA',
            birthDate: '19/08/2016',
        );

        (new EducacensoImportInepService($import, $data))->execute();

        $this->assertDatabaseHas('modules.educacenso_cod_aluno', [
            'cod_aluno' => $student->getKey(),
            'cod_aluno_inep' => '185477950497',
        ]);
    }

    public function testDoesNotUpdateTeacherInepFromRegister30(): void
    {
        $student = LegacyStudentFactory::new()->create();
        $student->individual->update(['cpf' => '03387533462']);
        $import = EducacensoInepImportFactory::new()->create(['year' => 2025]);

        $data = [
            '00|27011739|1|24/02/2025|31/12/2025|ESCOLA MUNICIPAL JOAO MATEUS||||||',
            '30|27011739||115034226587|03387533462|CICERA JOSE DA SILVA|29/04/1975|',
        ];

        (new EducacensoImportInepService($import, $data))->execute();

        $this->assertNull(StudentInep::query()->where('cod_aluno', $student->getKey())->first());
    }

    public function testUpdatesStudentInepBySchoolNameAndBirthDateWhenCpfIsEmpty(): void
    {
        $school = $this->createSchool('ESCOLA MUNICIPAL JOAO MATEUS', '27011739');
        $student = LegacyStudentFactory::new()->create();
        $student->individual->update([
            'cpf' => null,
            'data_nasc' => '2016-08-19',
        ]);
        $student->person->update(['nome' => 'MAISA KELLY GOMES DA SILVA']);
        LegacyRegistrationFactory::new()->create([
            'ref_cod_aluno' => $student->getKey(),
            'ref_ref_cod_escola' => $school->getKey(),
            'ano' => 2025,
        ]);

        $import = EducacensoInepImportFactory::new()->create(['year' => 2025]);
        $data = $this->schoolLines(
            studentInep: '185477950497',
            cpf: '',
            name: 'MAISA KELLY GOMES DA SILVA',
            birthDate: '19/08/2016',
        );

        (new EducacensoImportInepService($import, $data))->execute();

        $this->assertDatabaseHas('modules.educacenso_cod_aluno', [
            'cod_aluno' => $student->getKey(),
            'cod_aluno_inep' => '185477950497',
        ]);
    }

    public function testUpdatesSchoolClassInepWhenSchoolAndClassNamesMatch(): void
    {
        $school = $this->createSchool('ESCOLA MUNICIPAL JOAO MATEUS', '27011739');
        $institution = $school->ref_cod_instituicao;
        $grade = LegacyGradeFactory::new()->create();
        LegacySchoolGradeFactory::new()->create([
            'ref_cod_escola' => $school->getKey(),
            'ref_cod_serie' => $grade->id,
        ]);
        $schoolClass = LegacySchoolClassFactory::new()->create([
            'nm_turma' => '3º ANO',
            'ano' => 2025,
            'ref_ref_cod_serie' => $grade->id,
            'ref_ref_cod_escola' => $school->getKey(),
            'ref_cod_instituicao' => $institution,
        ]);

        $import = EducacensoInepImportFactory::new()->create(['year' => 2025]);
        $data = [
            '00|27011739|1|24/02/2025|31/12/2025|ESCOLA MUNICIPAL JOAO MATEUS||||||',
            '20|27011739||32597944|3º ANO|1||',
        ];

        (new EducacensoImportInepService($import, $data))->execute();

        $this->assertDatabaseHas('modules.educacenso_cod_turma', [
            'cod_turma' => $schoolClass->getKey(),
            'cod_turma_inep' => '32597944',
        ]);
    }

    public function testDoesNotUpdateSchoolClassWhenSchoolNameDiffers(): void
    {
        $school = $this->createSchool('OUTRA ESCOLA', '27011739');
        $institution = $school->ref_cod_instituicao;
        $grade = LegacyGradeFactory::new()->create();
        LegacySchoolGradeFactory::new()->create([
            'ref_cod_escola' => $school->getKey(),
            'ref_cod_serie' => $grade->id,
        ]);
        $schoolClass = LegacySchoolClassFactory::new()->create([
            'nm_turma' => '3º ANO',
            'ano' => 2025,
            'ref_ref_cod_serie' => $grade->id,
            'ref_ref_cod_escola' => $school->getKey(),
            'ref_cod_instituicao' => $institution,
        ]);

        $import = EducacensoInepImportFactory::new()->create(['year' => 2025]);
        $data = [
            '00|27011739|1|24/02/2025|31/12/2025|ESCOLA MUNICIPAL JOAO MATEUS||||||',
            '20|27011739||32597944|3º ANO|1||',
        ];

        (new EducacensoImportInepService($import, $data))->execute();

        $this->assertNull(SchoolClassInep::query()->where('cod_turma', $schoolClass->getKey())->first());
    }

    public function testCpfVariantsDoNotIncludeFormattedValues(): void
    {
        $matcher = new EducacensoInepIdentityMatcher(2026, '27011704', 'ESCOLA MUNICIPAL GETULIO VARGAS');

        $variants = $matcher->cpfVariants('066.610.294-55');

        $this->assertNotEmpty($variants);
        $this->assertContains('06661029455', $variants);
        $this->assertContains('6661029455', $variants);

        foreach ($variants as $variant) {
            $this->assertMatchesRegularExpression('/^\d+$/', $variant);
        }
    }

    public function testUpdatesSchoolClassInepWhenMultipleClassesShareTheWordAno(): void
    {
        $school = $this->createSchool('ESCOLA MUNICIPAL JOAO MATEUS', '27011739');
        $institution = $school->ref_cod_instituicao;
        $grade = LegacyGradeFactory::new()->create();
        LegacySchoolGradeFactory::new()->create([
            'ref_cod_escola' => $school->getKey(),
            'ref_cod_serie' => $grade->id,
        ]);
        $firstYear = LegacySchoolClassFactory::new()->create([
            'nm_turma' => '1º ANO',
            'ano' => 2025,
            'ref_ref_cod_serie' => $grade->id,
            'ref_ref_cod_escola' => $school->getKey(),
            'ref_cod_instituicao' => $institution,
        ]);
        $thirdYear = LegacySchoolClassFactory::new()->create([
            'nm_turma' => '3º ANO',
            'ano' => 2025,
            'ref_ref_cod_serie' => $grade->id,
            'ref_ref_cod_escola' => $school->getKey(),
            'ref_cod_instituicao' => $institution,
        ]);

        $import = EducacensoInepImportFactory::new()->create(['year' => 2025]);
        $data = [
            '00|27011739|1|24/02/2025|31/12/2025|ESCOLA MUNICIPAL JOAO MATEUS||||||',
            '20|27011739||32597944|3º ANO|1||',
        ];

        (new EducacensoImportInepService($import, $data))->execute();

        $this->assertDatabaseHas('modules.educacenso_cod_turma', [
            'cod_turma' => $thirdYear->getKey(),
            'cod_turma_inep' => '32597944',
        ]);
        $this->assertNull(SchoolClassInep::query()->where('cod_turma', $firstYear->getKey())->first());
    }

    /**
     * @return list<string>
     */
    private function schoolLines(string $studentInep, string $cpf, string $name, string $birthDate): array
    {
        return [
            '00|27011739|1|24/02/2025|31/12/2025|ESCOLA MUNICIPAL JOAO MATEUS||||||',
            "30|27011739||{$studentInep}|{$cpf}|{$name}|{$birthDate}|",
            "60|27011739||{$studentInep}||32597944|669816248|",
        ];
    }

    private function createSchool(string $name, string $inep)
    {
        $institution = LegacyInstitutionFactory::new()->create();
        $school = LegacySchoolFactory::new()->create([
            'ref_cod_instituicao' => $institution->id,
        ]);
        $school->person->update(['nome' => $name]);
        $school->organization?->update(['fantasia' => $name]);
        SchoolInepFactory::new()->create([
            'cod_escola' => $school->getKey(),
            'cod_escola_inep' => $inep,
        ]);

        return $school->refresh();
    }
}
