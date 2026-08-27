<?php

namespace iEducar\Packages\Educacenso\Services\Version2026;

use App\Models\Employee;
use App\Models\Educacenso\RegistroEducacenso;
use iEducar\Modules\Educacenso\Model\FormacaoContinuada;
use iEducar\Packages\Educacenso\Services\Version2025\Registro30Import as Registro30Import2025;
use iEducar\Packages\Educacenso\Services\Version2026\Models\Registro30Model;

class Registro30Import extends Registro30Import2025
{
    public static function getModel($arrayColumns)
    {
        $registro = new Registro30Model();
        $registro->hydrateModel($arrayColumns);

        return $registro;
    }

    public function import(RegistroEducacenso $model, $year, $user): void
    {
        parent::import($model, $year, $user);
    }

    protected function storeEmployeeCourses(Employee $employee): void
    {
        parent::storeEmployeeCourses($employee);

        $arrayCourses = transformStringFromDBInArray($employee->curso_formacao_continuada) ?: [];

        if ($this->model->formacaoContinuadaAlfabetizacao) {
            $arrayCourses[] = FormacaoContinuada::ALFABETIZACAO;
        }

        if ($this->model->formacaoContinuadaEducacaoIntegralTempoIntegral) {
            $arrayCourses[] = FormacaoContinuada::EDUCACAO_INTEGRAL;
        }

        $employee->curso_formacao_continuada = $this->getPostgresIntegerArray(array_values(array_unique($arrayCourses)));
        $employee->save();
    }
}
