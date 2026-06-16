<?php

namespace iEducar\Packages\Educacenso\Layout\Export\Identification\Layout2026;

use App\Models\LegacyStudent;
use iEducar\Packages\Educacenso\Layout\Export\Contracts\IdentificationRepository as IdentificationRepositoryContract;
use iEducar\Packages\Educacenso\Layout\Export\Identification\IdentificationFormatter;

class IdentificationRepository extends IdentificationRepositoryContract
{
    private IdentificationFormatter $formatter;

    public function __construct()
    {
        $this->formatter = new IdentificationFormatter();
    }

    public function getStudentsToExport(int $year, int $schoolId): array
    {
        $dataBaseEducacenso = config('educacenso.data_base.' . $year);

        return LegacyStudent::query()
            ->select([
                'cod_aluno',
                'ref_idpes',
            ])
            ->with([
                'person:idpes,nome',
                'individual:idpes,cpf,data_nasc,idpes_mae,idpes_pai,idmun_nascimento',
                'individual.mother:idpes,nome',
                'individual.father:idpes,nome',
                'individual.cityBirth:id,ibge_code',
                'document:idpes,certidao_nascimento',
                'inep:cod_aluno,cod_aluno_inep',
            ])
            ->whereHas('registrations', function ($query) use ($year, $schoolId, $dataBaseEducacenso): void {
                $query->where('ano', $year);
                $query->where('ref_ref_cod_escola', $schoolId);
                $query->where(function ($query) use ($dataBaseEducacenso): void {
                    $query->whereNull('data_cancel');
                    $query->orWhere('data_cancel', '>=', $dataBaseEducacenso);
                });
                $query->whereHas('enrollments', function ($query) use ($dataBaseEducacenso): void {
                    $query->where('data_enturmacao', '<=', $dataBaseEducacenso);
                    $query->whereValid();
                });
            })
            ->whereDoesntHave('inep')
            ->orderBy('cod_aluno')
            ->get()
            ->unique('cod_aluno')
            ->map(fn (LegacyStudent $student) => $this->mapStudent($student))
            ->values()
            ->toArray();
    }

    private function mapStudent(LegacyStudent $student): array
    {
        return [
            '1' => $student->getKey(),
            '2' => $this->formatter->formatCpf($student->individual?->cpf),
            '3' => $this->formatter->formatBirthCertificate($student->document?->certidao_nascimento),
            '4' => $this->formatter->formatName($student->person?->nome),
            '5' => $this->formatter->formatBirthDate($student->individual?->data_nasc),
            '6' => $this->formatter->formatName($student->individual?->mother?->nome),
            '7' => $this->formatter->formatName($student->individual?->father?->nome),
            '8' => $student->individual?->cityBirth?->ibge_code,
            '9' => null,
        ];
    }
}
