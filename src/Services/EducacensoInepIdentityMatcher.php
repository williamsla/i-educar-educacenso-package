<?php

namespace iEducar\Packages\Educacenso\Services;

use App\Models\Employee;
use App\Models\EmployeeInep;
use App\Models\LegacyIndividual;
use App\Models\LegacySchool;
use App\Models\LegacySchoolClass;
use App\Models\LegacySchoolClassTeacher;
use App\Models\LegacyStudent;
use App\Models\SchoolClassInep;
use App\Models\SchoolInep;
use App\Models\StudentInep;
use Carbon\Carbon;
use iEducar\Packages\Educacenso\Layout\Export\Identification\IdentificationFormatter;

class EducacensoInepIdentityMatcher
{
    private IdentificationFormatter $formatter;

    private ?LegacySchool $school = null;

    private bool $schoolResolved = false;

    public function __construct(
        private int $year,
        private string $schoolInep,
        private string $schoolName,
    ) {
        $this->formatter = new IdentificationFormatter();
    }

    public function findStudent(string $cpf, string $name, string $birthDate): ?LegacyStudent
    {
        return $this->findStudentByCpf($cpf)
            ?? $this->findStudentBySchoolNameAndBirthDate($name, $birthDate);
    }

    public function findEmployee(string $cpf, string $name, string $birthDate): ?Employee
    {
        return $this->findEmployeeByCpf($cpf)
            ?? $this->findEmployeeBySchoolNameAndBirthDate($name, $birthDate);
    }

    public function saveStudentInep(LegacyStudent $student, string $inep): void
    {
        StudentInep::query()->updateOrCreate(
            ['cod_aluno' => $student->getKey()],
            ['cod_aluno_inep' => $inep]
        );
    }

    public function saveEmployeeInep(Employee $employee, string $inep): void
    {
        EmployeeInep::query()->where('cod_servidor', $employee->getKey())->delete();

        EmployeeInep::query()->create([
            'cod_servidor' => $employee->getKey(),
            'cod_docente_inep' => $inep,
            'nome_inep' => $employee->person?->nome,
        ]);
    }

    public function updateSchoolClassByName(string $className, string $inep): void
    {
        $school = $this->getSchool();

        if ($school === null || ! $this->schoolNameMatches($school) || $this->normalizeName($className) === '') {
            return;
        }

        $matches = LegacySchoolClass::query()
            ->active()
            ->whereSchool($school->getKey())
            ->whereYearEq($this->year)
            ->get(['cod_turma', 'nm_turma'])
            ->filter(fn (LegacySchoolClass $schoolClass): bool => $this->normalizeName($schoolClass->nm_turma) === $this->normalizeName($className));

        if ($matches->count() !== 1) {
            return;
        }

        SchoolClassInep::query()->updateOrCreate(
            ['cod_turma' => $matches->first()->getKey()],
            ['cod_turma_inep' => $inep]
        );
    }

    private function findStudentByCpf(string $cpf): ?LegacyStudent
    {
        $variants = $this->cpfVariants($cpf);

        if ($variants === []) {
            return null;
        }

        $students = LegacyStudent::query()
            ->whereHas('individual', fn ($query) => $query->whereIn('cpf', $variants))
            ->get(['cod_aluno']);

        if ($students->count() === 1) {
            return $students->first();
        }

        if ($students->isEmpty()) {
            return null;
        }

        $school = $this->getSchool();

        if ($school === null) {
            return null;
        }

        $inSchool = $students->filter(fn (LegacyStudent $student): bool => $this->studentBelongsToSchool($student, $school));

        return $inSchool->count() === 1 ? $inSchool->first() : null;
    }

    private function findStudentBySchoolNameAndBirthDate(string $name, string $birthDate): ?LegacyStudent
    {
        $normalizedName = $this->normalizeName($name);
        $parsedBirthDate = $this->parseBirthDate($birthDate);
        $school = $this->getSchool();

        if ($normalizedName === '' || $parsedBirthDate === null || $school === null) {
            return null;
        }

        $matches = LegacyStudent::query()
            ->with(['person:idpes,nome'])
            ->whereHas('individual', fn ($query) => $query->whereDate('data_nasc', $parsedBirthDate->toDateString()))
            ->whereHas('registrations', function ($query) use ($school): void {
                $query->where('ano', $this->year)
                    ->where('ref_ref_cod_escola', $school->getKey());
            })
            ->get()
            ->filter(fn (LegacyStudent $student): bool => $this->normalizeName($student->person?->nome) === $normalizedName);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function findEmployeeByCpf(string $cpf): ?Employee
    {
        $variants = $this->cpfVariants($cpf);

        if ($variants === []) {
            return null;
        }

        $personIds = LegacyIndividual::query()
            ->whereIn('cpf', $variants)
            ->pluck('idpes');

        $employees = Employee::query()
            ->with('person')
            ->whereIn('cod_servidor', $personIds)
            ->get();

        return $this->uniqueEmployee($employees);
    }

    private function findEmployeeBySchoolNameAndBirthDate(string $name, string $birthDate): ?Employee
    {
        $normalizedName = $this->normalizeName($name);
        $parsedBirthDate = $this->parseBirthDate($birthDate);

        if ($normalizedName === '' || $parsedBirthDate === null) {
            return null;
        }

        $personIds = LegacyIndividual::query()
            ->with(['person:idpes,nome'])
            ->whereDate('data_nasc', $parsedBirthDate->toDateString())
            ->get()
            ->filter(fn (LegacyIndividual $individual): bool => $this->normalizeName($individual->person?->nome) === $normalizedName)
            ->pluck('idpes');

        $employees = Employee::query()
            ->with('person')
            ->whereIn('cod_servidor', $personIds)
            ->get();

        return $this->uniqueEmployee($employees);
    }

    /**
     * @param \Illuminate\Support\Collection<int, Employee> $employees
     */
    private function uniqueEmployee($employees): ?Employee
    {
        if ($employees->count() === 1) {
            return $employees->first();
        }

        if ($employees->isEmpty()) {
            return null;
        }

        $school = $this->getSchool();

        if ($school === null) {
            return null;
        }

        $inSchool = $employees->filter(fn (Employee $employee): bool => $this->employeeBelongsToSchool($employee, $school));

        return $inSchool->count() === 1 ? $inSchool->first() : null;
    }

    private function studentBelongsToSchool(LegacyStudent $student, LegacySchool $school): bool
    {
        return $student->registrations()
            ->where('ano', $this->year)
            ->where('ref_ref_cod_escola', $school->getKey())
            ->exists();
    }

    private function employeeBelongsToSchool(Employee $employee, LegacySchool $school): bool
    {
        $classIds = LegacySchoolClass::query()
            ->whereSchool($school->getKey())
            ->whereYearEq($this->year)
            ->pluck('cod_turma');

        if ($classIds->isEmpty()) {
            return false;
        }

        return LegacySchoolClassTeacher::query()
            ->where('servidor_id', $employee->getKey())
            ->where('ano', $this->year)
            ->whereIn('turma_id', $classIds)
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function cpfVariants(string $cpf): array
    {
        $digits = clearInt($cpf) ?? '';

        if ($digits === '') {
            return [];
        }

        if (strlen($digits) < 11) {
            $digits = str_pad($digits, 11, '0', STR_PAD_LEFT);
        }

        if (strlen($digits) !== 11 || ! ctype_digit($digits)) {
            return [];
        }

        // cadastro.fisica.cpf is numeric(11,0). Values with dots/hyphens make PostgreSQL throw
        // "invalid input syntax for type numeric" and abort the whole import.
        return array_values(array_unique([
            $digits,
            ltrim($digits, '0') ?: '0',
            (string) (int) $digits,
        ]));
    }

    public function parseBirthDate(string $birthDate): ?Carbon
    {
        $birthDate = trim($birthDate);

        if ($birthDate === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!d/m/Y', $birthDate);
        } catch (\Throwable) {
            return null;
        }

        if ($date === false || $date->format('d/m/Y') !== $birthDate) {
            return null;
        }

        return $date;
    }

    public function getSchool(): ?LegacySchool
    {
        if ($this->schoolResolved) {
            return $this->school;
        }

        $this->schoolResolved = true;

        if ($this->schoolInep === '') {
            return null;
        }

        $this->school = SchoolInep::query()
            ->where('cod_escola_inep', $this->schoolInep)
            ->first()
            ?->school;

        return $this->school;
    }

    public function schoolNameMatches(LegacySchool $school): bool
    {
        $fileName = $this->normalizeName($this->schoolName);
        $schoolName = $this->normalizeName((string) $school->name);

        return $fileName !== '' && $fileName === $schoolName;
    }

    public function normalizeName(?string $name): string
    {
        $name = $this->decode((string) $name);
        $formatted = $this->formatter->convertStringToCenso($name) ?? '';

        return trim(preg_replace('/\s+/', ' ', $formatted) ?? '');
    }

    public function decode(string $value): string
    {
        return trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
