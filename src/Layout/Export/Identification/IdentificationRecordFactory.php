<?php

namespace iEducar\Packages\Educacenso\Layout\Export\Identification;

use iEducar\Packages\Educacenso\Layout\Export\Contracts\Validation;
use InvalidArgumentException;

class IdentificationRecordFactory
{
    public static function fromYear(int $year, array $students): Validation
    {
        return match ($year) {
            2026 => new Layout2026\Record($students),
            default => throw new InvalidArgumentException("Year {$year} is not supported."),
        };
    }
}
