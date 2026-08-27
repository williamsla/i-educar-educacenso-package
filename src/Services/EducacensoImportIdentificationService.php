<?php

namespace iEducar\Packages\Educacenso\Services;

use App\Models\LegacyStudent;
use App\Models\NotificationType;
use App\Models\StudentInep;
use App\Services\NotificationService;
use Generator;
use iEducar\Packages\Educacenso\Enums\EducacensoImportStatus;
use iEducar\Packages\Educacenso\Exception\ImportIdentificationException;
use iEducar\Packages\Educacenso\Layout\Export\Identification\IdentificationFormatter;
use iEducar\Packages\Educacenso\Models\EducacensoIdentificationImport;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EducacensoImportIdentificationService
{
    private const REASON_EMPTY_INEP = 'MEC não retornou código INEP no campo 9';

    private const REASON_INVALID_INEP = 'Código INEP inválido no campo 9';

    private const REASON_STUDENT_NOT_FOUND = 'Aluno não encontrado no i-educar';

    private const REASON_INEP_ON_OTHER_STUDENT = 'Código INEP já vinculado a outro aluno sem cadastro duplicado identificado';

    private int $importedCount = 0;

    private int $skippedCount = 0;

    /** @var array<int, array{student_code: string, name: string, reason: string}> */
    private array $skippedLines = [];

    private IdentificationFormatter $formatter;

    public function __construct(
        private EducacensoIdentificationImport $import,
        private array $lines
    ) {
        $this->formatter = new IdentificationFormatter();
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function parseFile(UploadedFile $file): array
    {
        $lines = [];

        foreach (self::readLines($file) as $lineNumber => $line) {
            $fields = explode('|', $line);

            if (count($fields) !== 9) {
                throw new ImportIdentificationException(
                    "Linha {$lineNumber} com número de campos diferente de 9. Foram encontrados " . count($fields) . ' campos.'
                );
            }

            $lines[] = $fields;
        }

        if ($lines === []) {
            throw new ImportIdentificationException('O arquivo não contém linhas para importação.');
        }

        $linesWithInep = array_filter($lines, static fn (array $fields) => self::field($fields, 9) !== '');

        if ($linesWithInep === []) {
            throw new ImportIdentificationException('O arquivo não contém códigos INEP no campo 9 para importação.');
        }

        return $lines;
    }

    public function execute(): void
    {
        foreach ($this->lines as $fields) {
            $this->importLine($fields);
        }

        $this->import->update([
            'status_id' => EducacensoImportStatus::SUCCESS,
            'imported_count' => $this->importedCount,
            'skipped_count' => $this->skippedCount,
            'skipped_lines' => $this->skippedLines,
        ]);

        $this->notifyUser();
    }

    public function failed(): void
    {
        $this->import->update([
            'status_id' => EducacensoImportStatus::ERROR,
            'imported_count' => $this->importedCount,
            'skipped_count' => $this->skippedCount,
            'skipped_lines' => $this->skippedLines,
        ]);
    }

    private function importLine(array $fields): void
    {
        $inep = self::field($fields, 9);

        if ($inep === '') {
            $this->skip($fields, self::REASON_EMPTY_INEP);

            return;
        }

        if (! ctype_digit($inep) || strlen($inep) !== 12) {
            $this->skip($fields, self::REASON_INVALID_INEP);

            return;
        }

        $studentId = $this->resolveStudentId($fields);

        if ($studentId === null) {
            $this->skip($fields, self::REASON_STUDENT_NOT_FOUND);

            return;
        }

        $existingStudentId = StudentInep::query()
            ->where('cod_aluno_inep', $inep)
            ->value('cod_aluno');

        if ($existingStudentId && (int) $existingStudentId !== $studentId) {
            if ($this->areDuplicateStudents($studentId, (int) $existingStudentId, $fields)) {
                $this->transferInep((int) $existingStudentId, $studentId, $inep);
                $this->importedCount++;

                return;
            }

            $this->skip($fields, self::REASON_INEP_ON_OTHER_STUDENT, $studentId);

            return;
        }

        StudentInep::query()->updateOrCreate(
            ['cod_aluno' => $studentId],
            ['cod_aluno_inep' => $inep]
        );

        $this->importedCount++;
    }

    private function transferInep(int $fromStudentId, int $toStudentId, string $inep): void
    {
        StudentInep::query()->where('cod_aluno', $fromStudentId)->delete();

        StudentInep::query()->updateOrCreate(
            ['cod_aluno' => $toStudentId],
            ['cod_aluno_inep' => $inep]
        );
    }

    private function areDuplicateStudents(int $targetStudentId, int $existingStudentId, array $fields): bool
    {
        $students = LegacyStudent::query()
            ->with([
                'person:idpes,nome',
                'individual:idpes,cpf,data_nasc',
                'document:idpes,certidao_nascimento',
            ])
            ->whereIn('cod_aluno', [$targetStudentId, $existingStudentId])
            ->get()
            ->keyBy('cod_aluno');

        $targetStudent = $students->get($targetStudentId);
        $existingStudent = $students->get($existingStudentId);

        if ($targetStudent === null || $existingStudent === null) {
            return false;
        }

        if ((int) $targetStudent->ref_idpes === (int) $existingStudent->ref_idpes) {
            return true;
        }

        $targetCpf = $this->normalizeCpf($targetStudent->individual?->cpf);
        $existingCpf = $this->normalizeCpf($existingStudent->individual?->cpf);
        $cpfFromFile = $this->normalizeCpf(self::field($fields, 2));

        if ($targetCpf !== null && $targetCpf === $existingCpf) {
            return true;
        }

        // O CPF do arquivo identifica a pessoa; o campo 1 indica o cadastro atual da escola.
        // Se o INEP está no cadastro antigo com o mesmo CPF do arquivo, trata como duplicata.
        if ($cpfFromFile !== null && $existingCpf !== null && $cpfFromFile === $existingCpf) {
            return true;
        }

        $targetCertificate = $this->normalizeBirthCertificate($targetStudent->document?->certidao_nascimento);
        $existingCertificate = $this->normalizeBirthCertificate($existingStudent->document?->certidao_nascimento);
        $certificateFromFile = $this->normalizeBirthCertificate(self::field($fields, 3));

        if ($targetCertificate !== null && $targetCertificate === $existingCertificate) {
            return true;
        }

        if ($certificateFromFile !== null
            && $this->certificateMatches($targetStudent, $certificateFromFile)
            && $this->certificateMatches($existingStudent, $certificateFromFile)) {
            return true;
        }

        if ($this->studentMatchesNameAndBirthDate($targetStudent, $fields)
            && $this->studentMatchesNameAndBirthDate($existingStudent, $fields)) {
            return true;
        }

        return false;
    }

    private function studentMatchesNameAndBirthDate(LegacyStudent $student, array $fields): bool
    {
        $fileName = self::field($fields, 4);
        $fileBirthDate = self::field($fields, 5);

        if ($fileName === '' || $fileBirthDate === '') {
            return false;
        }

        $studentName = $this->formatter->formatName($student->person?->nome);
        $studentBirthDate = $this->formatter->formatBirthDate($student->individual?->data_nasc);

        return $studentName === $fileName && $studentBirthDate === $fileBirthDate;
    }

    private function certificateMatches(LegacyStudent $student, string $certificate): bool
    {
        $studentCertificate = $this->normalizeBirthCertificate($student->document?->certidao_nascimento);

        return $studentCertificate !== null && $studentCertificate === $certificate;
    }

    private function normalizeBirthCertificate(?string $certificate): ?string
    {
        if ($certificate === null || trim($certificate) === '') {
            return null;
        }

        $certificate = $this->formatter->formatBirthCertificate($certificate);

        return $certificate === '' ? null : $certificate;
    }

    private function normalizeCpf(?string $cpf): ?string
    {
        if ($cpf === null || trim($cpf) === '') {
            return null;
        }

        $cpf = clearInt($cpf) ?? '';

        if (strlen($cpf) !== 11 || ! ctype_digit($cpf)) {
            return null;
        }

        return $cpf;
    }

    private function resolveStudentId(array $fields): ?int
    {
        $studentIdFromFile = self::field($fields, 1);

        if ($studentIdFromFile !== '' && ctype_digit($studentIdFromFile)) {
            $studentId = LegacyStudent::query()->whereKey($studentIdFromFile)->value('cod_aluno');

            if ($studentId !== null) {
                return (int) $studentId;
            }
        }

        return $this->resolveStudentIdByCpf(self::field($fields, 2));
    }

    private function resolveStudentIdByCpf(string $cpf): ?int
    {
        $cpf = clearInt($cpf) ?? '';

        if (strlen($cpf) !== 11 || ! ctype_digit($cpf)) {
            return null;
        }

        $studentId = LegacyStudent::query()
            ->whereHas('individual', fn ($query) => $query->where('cpf', $cpf))
            ->value('cod_aluno');

        if ($studentId !== null) {
            return (int) $studentId;
        }

        $formattedCpf = vsprintf('%s.%s.%s-%s', [
            substr($cpf, 0, 3),
            substr($cpf, 3, 3),
            substr($cpf, 6, 3),
            substr($cpf, 9, 2),
        ]);

        $studentId = LegacyStudent::query()
            ->whereHas('individual', fn ($query) => $query->where('cpf', $formattedCpf))
            ->value('cod_aluno');

        return $studentId !== null ? (int) $studentId : null;
    }

    private function notifyUser(): void
    {
        (new NotificationService())->createByUser(
            userId: $this->import->user_id,
            text: $this->getMessage(),
            link: $this->skippedCount > 0
                ? route('educacenso.import.identification.show', $this->import)
                : route('educacenso.import.identification.index'),
            type: NotificationType::OTHER
        );
    }

    private function skip(array $fields, string $reason, ?int $studentId = null): void
    {
        $this->skippedCount++;
        $this->skippedLines[] = [
            'student_code' => self::field($fields, 1),
            'name' => $this->resolveDisplayName($fields, $studentId),
            'reason' => $reason,
        ];
    }

    private function resolveDisplayName(array $fields, ?int $studentId = null): string
    {
        $nameFromFile = self::field($fields, 4);

        if ($nameFromFile !== '') {
            return $nameFromFile;
        }

        if ($studentId !== null) {
            $name = LegacyStudent::query()
                ->with('person:idpes,nome')
                ->find($studentId)
                ?->person
                ?->nome;

            if (! empty($name)) {
                return $name;
            }
        }

        $studentCode = self::field($fields, 1);

        return $studentCode !== '' ? "Aluno código {$studentCode}" : 'Não identificado';
    }

    private function getMessage(): string
    {
        $message = "Importação do arquivo de identificação {$this->import->file_name} finalizada. {$this->importedCount} INEP(s) importado(s), {$this->skippedCount} linha(s) ignorada(s).";

        if ($this->skippedLines === []) {
            return $message;
        }

        $names = collect($this->skippedLines)
            ->pluck('name')
            ->filter()
            ->unique()
            ->implode(', ');

        return "{$message} Ignorados: {$names}.";
    }

    private static function field(array $fields, int $position): string
    {
        return trim($fields[$position - 1] ?? '');
    }

    private static function readLines(UploadedFile $file): Generator
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new ImportIdentificationException('Não foi possível ler o arquivo enviado.');
        }

        $lineNumber = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = rtrim($line, "\r\n");

            if ($line === '') {
                continue;
            }

            yield $lineNumber => mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');
        }

        fclose($handle);
    }
}
