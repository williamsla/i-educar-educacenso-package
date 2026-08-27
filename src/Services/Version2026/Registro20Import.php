<?php

namespace iEducar\Packages\Educacenso\Services\Version2026;

use App\Models\Educacenso\RegistroEducacenso;
use App\Models\LegacySchoolClass;
use iEducar\Packages\Educacenso\Services\Version2025\Registro20Import as Registro20Import2025;
use iEducar\Packages\Educacenso\Services\Version2026\Models\Registro20Model;

class Registro20Import extends Registro20Import2025
{
    public function import(RegistroEducacenso $model, $year, $user): void
    {
        parent::import($model, $year, $user);

        $schoolClassInep = parent::getSchoolClass();
        $schoolClass = LegacySchoolClass::find($schoolClassInep->cod_turma);
        $model = $this->model;

        if ($schoolClass) {
            if ($model->formasOrganizacaoTurma) {
                $schoolClass->formas_organizacao_turma = $model->formasOrganizacaoTurma;
            }

            if ($model->codigoEixoCursoProfissional) {
                $schoolClass->codigo_eixo_curso_profissional = $model->codigoEixoCursoProfissional;
            }

            if ($model->cargaHorariaCurso) {
                $schoolClass->carga_horaria_curso = $model->cargaHorariaCurso;
            }

            $schoolClass->save();
        }
    }

    public static function getModel($arrayColumns)
    {
        $registro = new Registro20Model();
        $registro->hydrateModel($arrayColumns);

        return $registro;
    }
}
