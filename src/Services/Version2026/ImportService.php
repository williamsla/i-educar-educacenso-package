<?php

namespace iEducar\Packages\Educacenso\Services\Version2026;

use iEducar\Packages\Educacenso\Services\Version2020\Registro40Import;
use iEducar\Packages\Educacenso\Services\Version2025\ImportService as ImportServiceVersion2025;

class ImportService extends ImportServiceVersion2025
{
    public function getYear()
    {
        return 2026;
    }

    public function getRegistroById($lineId)
    {
        $arrayRegistros = [
            '00' => Registro00Import::class,
            '10' => Registro10Import::class,
            '20' => Registro20Import::class,
            '30' => Registro30Import::class,
            '40' => Registro40Import::class,
            '50' => Registro50Import::class,
            '60' => Registro60Import::class,
        ];

        if (! isset($arrayRegistros[$lineId])) {
            return;
        }

        return new $arrayRegistros[$lineId]();
    }
}
