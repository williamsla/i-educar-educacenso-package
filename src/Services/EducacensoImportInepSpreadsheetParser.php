<?php

namespace iEducar\Packages\Educacenso\Services;

use iEducar\Packages\Educacenso\Enums\EducacensoInepImportLayout;
use iEducar\Packages\Educacenso\Exception\ImportInepException;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EducacensoImportInepSpreadsheetParser
{
    private const TITLE_STUDENT = 'relacao de alunos';

    private const TITLE_EMPLOYEE = 'relacao de profissionais';

    /**
     * @return array{
     *     layout: string,
     *     school_inep: string,
     *     school_name: string,
     *     year: int|null,
     *     rows: list<array{inep: string, name: string, cpf: string, birth_date: string, class_inep: string, class_name: string}>
     * }
     */
    public static function parse(UploadedFile $file, string $expectedLayout): array
    {
        $rows = self::readRows($file);
        $detectedLayout = self::detectLayout($rows, $file->getClientOriginalName());

        if ($detectedLayout !== $expectedLayout) {
            $expectedLabel = $expectedLayout === EducacensoInepImportLayout::SPREADSHEET_STUDENT
                ? 'Relação de alunos da escola'
                : 'Relação de profissionais escolares';
            $detectedLabel = $detectedLayout === EducacensoInepImportLayout::SPREADSHEET_STUDENT
                ? 'Relação de alunos da escola'
                : 'Relação de profissionais escolares';

            throw new ImportInepException(
                "A planilha \"{$file->getClientOriginalName()}\" é do tipo \"{$detectedLabel}\", mas o filtro selecionado foi \"{$expectedLabel}\"."
            );
        }

        $schoolInep = self::findLabeledValue($rows, 'codigo da escola');
        $schoolName = self::findLabeledValue($rows, 'nome da escola');
        $year = self::findYear($rows);
        [$headerIndex, $columns] = self::findHeader($rows);

        if ($schoolInep === '' || ! ctype_digit($schoolInep) || strlen($schoolInep) !== 8) {
            throw new ImportInepException(
                "Não foi possível ler o código INEP da escola na planilha \"{$file->getClientOriginalName()}\"."
            );
        }

        if ($schoolName === '') {
            throw new ImportInepException(
                "Não foi possível ler o nome da escola na planilha \"{$file->getClientOriginalName()}\"."
            );
        }

        $dataRows = self::extractDataRows($rows, $headerIndex, $columns);

        if ($dataRows === []) {
            throw new ImportInepException(
                "A planilha \"{$file->getClientOriginalName()}\" não contém linhas de dados para importação."
            );
        }

        return [
            'layout' => $detectedLayout,
            'school_inep' => $schoolInep,
            'school_name' => mb_strtoupper($schoolName),
            'year' => $year,
            'rows' => $dataRows,
        ];
    }

    /**
     * @return list<list<string>>
     */
    private static function readRows(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);

        $rows = [];
        foreach ($rawRows as $row) {
            $rows[] = array_map(static fn ($value) => self::stringify($value), $row);
        }

        return $rows;
    }

    /**
     * @param list<list<string>> $rows
     */
    private static function detectLayout(array $rows, string $fileName): string
    {
        $haystack = self::normalize(implode(' ', array_map(static fn (array $row) => implode(' ', $row), array_slice($rows, 0, 15))));

        if (str_contains($haystack, self::TITLE_STUDENT)) {
            return EducacensoInepImportLayout::SPREADSHEET_STUDENT;
        }

        if (str_contains($haystack, self::TITLE_EMPLOYEE)) {
            return EducacensoInepImportLayout::SPREADSHEET_EMPLOYEE;
        }

        throw new ImportInepException(
            "A planilha \"{$fileName}\" não corresponde aos layouts de Relação de alunos ou Relação de profissionais do Censo Escolar 2026."
        );
    }

    /**
     * @param list<list<string>> $rows
     */
    private static function findLabeledValue(array $rows, string $normalizedLabel): string
    {
        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                if (! str_starts_with(self::normalize($cell), $normalizedLabel)) {
                    continue;
                }

                $inline = trim((string) preg_replace('/^' . preg_quote($cell, '/') . '/i', '', $cell));
                if ($inline !== '' && $inline !== ':') {
                    return trim($inline, " \t:-");
                }

                for ($next = $index + 1, $count = count($row); $next < $count; $next++) {
                    $value = trim($row[$next]);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param list<list<string>> $rows
     */
    private static function findYear(array $rows): ?int
    {
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if (preg_match('/censo escolar\s+(\d{4})/i', $cell, $matches) === 1) {
                    return (int) $matches[1];
                }
            }
        }

        return null;
    }

    /**
     * @param list<list<string>> $rows
     * @return array{0: int, 1: array<string, int>}
     */
    private static function findHeader(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $map = [];
            foreach ($row as $column => $cell) {
                $normalized = self::normalize($cell);
                $field = match ($normalized) {
                    'identificacao unica' => 'inep',
                    'nome' => 'name',
                    'data de nascimento' => 'birth_date',
                    'cpf' => 'cpf',
                    'codigo da turma' => 'class_inep',
                    'nome da turma' => 'class_name',
                    default => null,
                };

                if ($field !== null) {
                    $map[$field] = $column;
                }
            }

            if (isset($map['inep'], $map['name'])) {
                return [$index, $map];
            }
        }

        throw new ImportInepException('Não foi possível localizar o cabeçalho da planilha (Identificação única / Nome).');
    }

    /**
     * @param list<list<string>> $rows
     * @param array<string, int> $columns
     * @return list<array{inep: string, name: string, cpf: string, birth_date: string, class_inep: string, class_name: string}>
     */
    private static function extractDataRows(array $rows, int $headerIndex, array $columns): array
    {
        $data = [];

        for ($index = $headerIndex + 1, $total = count($rows); $index < $total; $index++) {
            $row = $rows[$index];
            $inep = self::cell($row, $columns['inep'] ?? null);
            $name = self::cell($row, $columns['name'] ?? null);

            if ($inep === '' && $name === '') {
                continue;
            }

            if (! ctype_digit(clearInt($inep) ?? '') || strlen(clearInt($inep) ?? '') !== 12) {
                continue;
            }

            $data[] = [
                'inep' => clearInt($inep) ?? '',
                'name' => $name,
                'cpf' => self::cell($row, $columns['cpf'] ?? null),
                'birth_date' => self::formatBirthDate(self::cell($row, $columns['birth_date'] ?? null)),
                'class_inep' => clearInt(self::cell($row, $columns['class_inep'] ?? null)) ?? '',
                'class_name' => self::cell($row, $columns['class_name'] ?? null),
            ];
        }

        return $data;
    }

    /**
     * @param list<string> $row
     */
    private static function cell(array $row, ?int $column): string
    {
        if ($column === null) {
            return '';
        }

        return trim((string) ($row[$column] ?? ''));
    }

    private static function formatBirthDate(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^(\d{2}\/\d{2}\/\d{4})/', $value, $matches) === 1) {
            return $matches[1];
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('d/m/Y');
            } catch (\Throwable) {
                return $value;
            }
        }

        try {
            return (new \DateTime($value))->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private static function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        return trim((string) $value);
    }

    private static function normalize(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
