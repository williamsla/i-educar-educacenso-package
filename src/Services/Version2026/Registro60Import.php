<?php

namespace iEducar\Packages\Educacenso\Services\Version2026;

use App\Models\Educacenso\RegistroEducacenso;
use App\Models\LegacyEnrollment;
use App\Models\LegacyRegistration;
use iEducar\Packages\Educacenso\Services\Version2025\Registro60Import as Registro60Import2025;
use iEducar\Packages\Educacenso\Services\Version2026\Models\Registro60Model;

class Registro60Import extends Registro60Import2025
{
    public static function getModel($arrayColumns)
    {
        $registro = new Registro60Model();
        $registro->hydrateModel($arrayColumns);

        return $registro;
    }

    public function import(RegistroEducacenso $model, $year, $user): void
    {
        parent::import($model, $year, $user);

        if (!$model->cargaHorariaIntegralizada) {
            return;
        }

        $schoolClass = parent::getSchoolClass();
        $student = parent::getStudent();
        $registration = LegacyRegistration::where('ref_ref_cod_serie', $schoolClass->grade->getKey())
            ->where('ref_cod_aluno', $student->getKey())
            ->where('ano', $year)
            ->first();

        if (!$registration) {
            return;
        }

        $enrollment = LegacyEnrollment::where('ref_cod_matricula', $registration->getKey())
            ->where('ref_cod_turma', $schoolClass->getKey())
            ->first();

        if ($enrollment) {
            $enrollment->carga_horaria_integralizada = $model->cargaHorariaIntegralizada;
            $enrollment->save();
        }
    }
}
