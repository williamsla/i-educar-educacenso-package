<?php

namespace iEducar\Packages\Educacenso\Enums;

class EducacensoInepImportLayout
{
    public const TXT = 'txt';

    public const SPREADSHEET_STUDENT = 'planilha_aluno';

    public const SPREADSHEET_EMPLOYEE = 'planilha_profissional';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::TXT,
            self::SPREADSHEET_STUDENT,
            self::SPREADSHEET_EMPLOYEE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function spreadsheets(): array
    {
        return [
            self::SPREADSHEET_STUDENT,
            self::SPREADSHEET_EMPLOYEE,
        ];
    }
}
