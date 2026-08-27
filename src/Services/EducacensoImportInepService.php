<?php

namespace iEducar\Packages\Educacenso\Services;

use App\Models\Employee;
use App\Models\EmployeeInep;
use App\Models\EnrollmentInep;
use App\Models\LegacyEnrollment;
use App\Models\LegacySchoolClass;
use App\Models\LegacyStudent;
use App\Models\NotificationType;
use App\Models\SchoolClassInep;
use App\Models\StudentInep;
use App\Services\NotificationService;
use Generator;
use iEducar\Packages\Educacenso\Enums\EducacensoImportStatus;
use iEducar\Packages\Educacenso\Models\EducacensoInepImport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class EducacensoImportInepService
{
    private string $schoolName;

    private string $schoolInep = '';

    private string $dataBaseEducacenso;

    private EducacensoInepIdentityMatcher $matcher;

    /** @var array<string, true> */
    private array $studentIneps = [];

    public function __construct(private EducacensoInepImport $educacensoInepImport, private array $data)
    {
        $this->dataBaseEducacenso = config("educacenso.data_base.{$this->educacensoInepImport->year}");
    }

    public static function getDataBySchool(UploadedFile $file): Generator
    {
        $lines = self::readFile($file);
        $school = [];
        foreach ($lines as $key => $line) {
            if (Str::startsWith($line, '00|')) {
                if (count($school)) {
                    yield $school;
                }
                $school = [];
            }
            $school[] = $line;
        }
        if (count($school)) {
            yield $school;
        }
    }

    private static function readFile($file): Generator
    {
        $handle = fopen($file, 'r');
        while (($line = fgets($handle)) !== false) {
            yield rtrim($line, "\r\n");
        }
    }

    public function execute(): void
    {
        $schoolData = explode('|', $this->data[0]);
        $this->schoolInep = trim((string) ($schoolData[1] ?? ''));
        $this->schoolName = trim(html_entity_decode((string) ($schoolData[5] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $this->matcher = new EducacensoInepIdentityMatcher(
            (int) $this->educacensoInepImport->year,
            $this->schoolInep,
            $this->schoolName,
        );
        $this->studentIneps = $this->collectStudentIneps();

        foreach ($this->data as $line) {
            $lineArray = explode('|', $line);
            $register = $lineArray[0] ?? '';
            $id = trim((string) ($lineArray[2] ?? ''));
            $inep = trim((string) ($lineArray[3] ?? ''));

            if ($register === '20') {
                $this->importSchoolClass($id, $inep, $this->matcher->decode((string) ($lineArray[4] ?? '')));

                continue;
            }

            if ($register === '30') {
                $this->importStudentByIdentity($lineArray);

                continue;
            }

            if (in_array($register, ['40', '50'], true) && $id !== '' && $inep !== '') {
                $this->updateEmployee($id, $inep);

                continue;
            }

            if ($register === '60' && $id !== '' && $inep !== '') {
                $this->updateStudent($id, $inep, $lineArray[5] ?? null, $lineArray[6] ?? null);
            }
        }
        $this->updateImporter();
        $this->notifyUser();
    }

    private function importSchoolClass(string $id, string $inep, string $className): void
    {
        if ($inep === '') {
            return;
        }

        if ($id !== '') {
            $this->updateSchoolClass($id, $inep);

            return;
        }

        $this->matcher->updateSchoolClassByName($className, $inep);
    }

    private function importStudentByIdentity(array $fields): void
    {
        $inep = trim((string) ($fields[3] ?? ''));

        if ($inep === '' || ! isset($this->studentIneps[$inep])) {
            return;
        }

        $cpf = trim((string) ($fields[4] ?? ''));
        $name = $this->matcher->decode((string) ($fields[5] ?? ''));
        $birthDate = trim((string) ($fields[6] ?? ''));

        $student = $this->matcher->findStudent($cpf, $name, $birthDate);

        if ($student === null) {
            return;
        }

        $this->matcher->saveStudentInep($student, $inep);
    }

    /**
     * @return array<string, true>
     */
    private function collectStudentIneps(): array
    {
        $ineps = [];

        foreach ($this->data as $line) {
            $fields = explode('|', $line);

            if (($fields[0] ?? '') !== '60') {
                continue;
            }

            $inep = trim((string) ($fields[3] ?? ''));

            if ($inep !== '') {
                $ineps[$inep] = true;
            }
        }

        return $ineps;
    }

    private function updateSchoolClass($id, $inep): void
    {
        $data = [
            'cod_turma_inep' => $inep,
        ];

        if (str_contains($id, '-')) {
            [$id, $turnoId] = explode('-', $id);

            $data['turma_turno_id'] = $turnoId;

            SchoolClassInep::query()
                ->updateOrCreate([
                    'cod_turma' => $id,
                    'turma_turno_id' => $turnoId,
                ], $data);

            return;
        }

        $doesntExist = LegacySchoolClass::query()->whereKey($id)->doesntExist();
        if ($doesntExist) {
            return;
        }
        SchoolClassInep::query()
            ->updateOrCreate([
                'cod_turma' => $id,
            ], $data);
    }

    private function updateEmployee($id, $inep): void
    {
        $employee = Employee::query()->with('person')->find($id);
        if (empty($employee)) {
            return;
        }

        EmployeeInep::query()->where('cod_servidor', $id)->delete();

        EmployeeInep::query()->create([
            'cod_servidor' => $id,
            'cod_docente_inep' => $inep,
            'nome_inep' => $employee->person?->nome,
        ]);
    }

    private function updateStudent($id, $inep, $inepSchoolClass, $matricula): void
    {
        $students = LegacyStudent::query()->where('ref_idpes', $id)->get(['cod_aluno']);
        if ($students->isEmpty()) {
            return;
        }

        foreach ($students as $student) {
            StudentInep::query()
                ->updateOrCreate([
                    'cod_aluno' => $student->getKey(),
                ], [
                    'cod_aluno_inep' => $inep,
                ]);


            $schoolClassInep = SchoolClassInep::query()
                ->where('cod_turma_inep', $inepSchoolClass)
                ->first();

            if ($schoolClassInep) {
                $enrollment = LegacyEnrollment::query()
                    ->where('ref_cod_turma', $schoolClassInep->cod_turma)
                    ->where('data_enturmacao', '<=', $this->dataBaseEducacenso)
                    ->whereHas('registration', function ($q) use ($student): void {
                        $q->where('ref_cod_aluno', $student->getKey());
                    })
                    ->orderByDesc('data_enturmacao')
                    ->get(['id'])
                    ->first();

                if ($enrollment) {
                    EnrollmentInep::query()
                        ->updateOrCreate([
                            'matricula_turma_id' => $enrollment->getKey(),
                            'matricula_inep' => $matricula,
                        ], [
                            'matricula_turma_id' => $enrollment->getKey(),
                            'matricula_inep' => $matricula,
                        ]);
                }
            }
        }
    }

    private function updateImporter(): void
    {
        $this->educacensoInepImport->update([
            'status_id' => EducacensoImportStatus::SUCCESS,
            'error_message' => null,
        ]);
    }

    private function notifyUser(): void
    {
        try {
            (new NotificationService())->createByUser(
                userId: $this->educacensoInepImport->user_id,
                text: $this->getMessage(),
                link: route('educacenso.import.inep.index'),
                type: NotificationType::OTHER
            );
        } catch (Throwable $exception) {
            Log::warning('Não foi possível notificar o usuário após importar INEPs.', [
                'import_id' => $this->educacensoInepImport->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function getMessage(): string
    {
        return "Foram importados os INEPs da escola {$this->schoolName}. Clique aqui para visualizar.";
    }

    public function failed(?string $errorMessage = null): void
    {
        $this->educacensoInepImport->update([
            'status_id' => EducacensoImportStatus::ERROR,
            'error_message' => $errorMessage,
        ]);
    }
}
