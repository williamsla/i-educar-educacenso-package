<?php

namespace iEducar\Packages\Educacenso\Services\Version2026;

use App\Models\Educacenso\RegistroEducacenso;
use iEducar\Packages\Educacenso\Services\Version2024\Registro00Import as Registro00Import2024;
use iEducar\Packages\Educacenso\Services\Version2026\Models\Registro00Model;

class Registro00Import extends Registro00Import2024
{
    public static function getModel($arrayColumns)
    {
        $registro = new Registro00Model();
        $registro->hydrateModel($arrayColumns);

        return $registro;
    }

    public function import(RegistroEducacenso $model, $year, $user): void
    {
        parent::import($model, $year, $user);
    }
}
