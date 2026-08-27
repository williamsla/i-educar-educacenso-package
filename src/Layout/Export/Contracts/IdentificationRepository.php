<?php

namespace iEducar\Packages\Educacenso\Layout\Export\Contracts;

abstract class IdentificationRepository
{
    abstract public function getStudentsToExport(int $year, int $schoolId): array;
}
