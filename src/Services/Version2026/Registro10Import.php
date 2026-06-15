<?php

namespace iEducar\Packages\Educacenso\Services\Version2026;

use iEducar\Packages\Educacenso\Services\Version2025\Registro10Import as Registro10Import2025;
use iEducar\Packages\Educacenso\Services\Version2026\Models\Registro10Model;

class Registro10Import extends Registro10Import2025
{
    public static function getModel($arrayColumns)
    {
        $registro = new Registro10Model();
        $registro->hydrateModel($arrayColumns);

        return $registro;
    }
}
