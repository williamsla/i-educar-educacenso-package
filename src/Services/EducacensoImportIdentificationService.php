<?php

namespace iEducar\Packages\Educacenso\Services;

use App\Models\LegacyStudent;
use App\Models\NotificationType;
use App\Models\StudentInep;
use App\Services\NotificationService;
use Generator;
use iEducar\Packages\Educacenso\Enums\EducacensoImportStatus;
use iEducar\Packages\Educacenso\Exception\ImportIdentificationException;
use iEducar\Packages\Educacenso\Models\EducacensoIdentificationImport;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EducacensoImportIdentificationService
{
    private int $importedCount = 0;

    private int $skippedCount = 0;

    public function __construct(
        private EducacensoIdentificationImport $import,
        private array $lines
    ) {
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
        ]);

        $this->notifyUser();
    }

    public function failed(): void
    {
        $this->import->update([
            'status_id' => EducacensoImportStatus::ERROR,
            'imported_count' => $this->importedCount,
            'skipped_count' => $this->skippedCount,
        ]);
    }

    private function importLine(array $fields): void
    {
        $inep = self::field($fields, 9);

        if ($inep === '') {
            $this->skippedCount++;

            return;
        }

        if (! ctype_digit($inep) || strlen($inep) !== 12) {
            $this->skippedCount++;

            return;
        }

        $studentId = $this->resolveStudentId($fields);

        if ($studentId === null) {
            $this->skippedCount++;

            return;
        }

        $existingStudentId = StudentInep::query()
            ->where('cod_aluno_inep', $inep)
            ->value('cod_aluno');

        if ($existingStudentId && (int) $existingStudentId !== $studentId) {
            $this->skippedCount++;

            return;
        }

        StudentInep::query()->updateOrCreate(
            ['cod_aluno' => $studentId],
            ['cod_aluno_inep' => $inep]
        );

        $this->importedCount++;
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
            link: route('educacenso.import.identification.index'),
            type: NotificationType::OTHER
        );
    }

    private function getMessage(): string
    {
        return "Importação do arquivo de identificação {$this->import->file_name} finalizada. {$this->importedCount} INEP(s) importado(s), {$this->skippedCount} linha(s) ignorada(s).";
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
