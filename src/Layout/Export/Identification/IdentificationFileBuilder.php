<?php

namespace iEducar\Packages\Educacenso\Layout\Export\Identification;

class IdentificationFileBuilder
{
    public const LINE_SEPARATOR = "\r\n";

    public static function buildFileName(int $schoolId, int $year): string
    {
        return "ident_{$schoolId}_{$year}.txt";
    }

    public static function buildContent(array $students): string
    {
        if ($students === []) {
            return '';
        }

        $lines = array_map(
            static fn (array $student) => self::buildLine($student),
            $students
        );

        return implode(self::LINE_SEPARATOR, $lines);
    }

    public static function buildLine(array $student): string
    {
        return collect(range(1, 9))
            ->map(static fn (int $field) => $student[(string) $field] ?? '')
            ->implode('|');
    }
}
