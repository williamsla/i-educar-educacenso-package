<?php

namespace iEducar\Packages\Educacenso\Services\Version2026;

use App\Models\Educacenso\RegistroEducacenso;
use iEducar\Modules\Educacenso\Model\InstrumentosPedagogicos;
use iEducar\Modules\Educacenso\Model\Laboratorios;
use iEducar\Packages\Educacenso\Services\Version2022\LegacySchool;
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

    public function import(RegistroEducacenso $model, $year, $user): void
    {
        parent::import($model, $year, $user);

        $schoolInep = parent::getSchool();

        if (empty($schoolInep)) {
            return;
        }

        /** @var LegacySchool $school */
        $school = $schoolInep->school;

        $school->qtd_assistente_social = $this->model->qtdAssistenteSocial ?: null;

        $school->save();
    }

    protected function getArrayLaboratorios()
    {
        $laboratorios = parent::getArrayLaboratorios();
        $arrayLaboratorios = transformStringFromDBInArray($laboratorios) ?: [];

        if ($this->model->dependenciaLaboratorioRobotica) {
            $arrayLaboratorios[] = Laboratorios::ROBOTICA;
        }

        return parent::getPostgresIntegerArray($arrayLaboratorios);
    }

    protected function getArrayInstrumentosPedagogicos()
    {
        $instrumentos = parent::getArrayInstrumentosPedagogicos();
        $arrayInstrumentos = transformStringFromDBInArray($instrumentos) ?: [];

        if ($this->model->instrumentosPedagogicosEquipamentosAudiovisuais) {
            $arrayInstrumentos[] = InstrumentosPedagogicos::EQUIPAMENTOS_AUDIOVISUAIS;
        }

        if ($this->model->instrumentosPedagogicosKitsRobotica) {
            $arrayInstrumentos[] = InstrumentosPedagogicos::KITS_ROBOTICA;
        }

        if ($this->model->instrumentosPedagogicosEducacaoEmocional) {
            $arrayInstrumentos[] = InstrumentosPedagogicos::EDUCACAO_EMOCIONAL;
        }

        return parent::getPostgresIntegerArray($arrayInstrumentos);
    }
}
