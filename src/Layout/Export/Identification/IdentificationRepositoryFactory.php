<?php

namespace iEducar\Packages\Educacenso\Layout\Export\Identification;

use iEducar\Packages\Educacenso\Layout\Export\Contracts\IdentificationRepository;
use InvalidArgumentException;

class IdentificationRepositoryFactory
{
    public static function fromYear(int $year): IdentificationRepository
    {
        return match ($year) {
            2026 => new Layout2026\IdentificationRepository(),
            default => throw new InvalidArgumentException("Year {$year} is not supported."),
        };
    }
}
