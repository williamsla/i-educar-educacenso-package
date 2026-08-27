<?php

namespace iEducar\Packages\Educacenso\Services;

use App\Models\NotificationType;
use App\Services\NotificationService;
use iEducar\Packages\Educacenso\Enums\EducacensoInepImportLayout;
use iEducar\Packages\Educacenso\Enums\EducacensoImportStatus;
use iEducar\Packages\Educacenso\Models\EducacensoInepImport;

class EducacensoImportInepSpreadsheetService
{
    private EducacensoInepIdentityMatcher $matcher;

    /**
     * @param array{
     *     layout: string,
     *     school_inep: string,
     *     school_name: string,
     *     year: int|null,
     *     rows: list<array{inep: string, name: string, cpf: string, birth_date: string, class_inep: string, class_name: string}>
     * } $data
     */
    public function __construct(private EducacensoInepImport $educacensoInepImport, private array $data)
    {
        $this->matcher = new EducacensoInepIdentityMatcher(
            (int) $this->educacensoInepImport->year,
            $this->data['school_inep'],
            $this->data['school_name'],
        );
    }

    public function execute(): void
    {
        $isStudentSheet = $this->data['layout'] === EducacensoInepImportLayout::SPREADSHEET_STUDENT;
        $importedPeople = [];
        $importedClasses = [];

        foreach ($this->data['rows'] as $row) {
            $inep = $row['inep'];

            if ($inep !== '' && ! isset($importedPeople[$inep])) {
                if ($isStudentSheet) {
                    $student = $this->matcher->findStudent($row['cpf'], $row['name'], $row['birth_date']);
                    if ($student !== null) {
                        $this->matcher->saveStudentInep($student, $inep);
                    }
                } else {
                    $employee = $this->matcher->findEmployee($row['cpf'], $row['name'], $row['birth_date']);
                    if ($employee !== null) {
                        $this->matcher->saveEmployeeInep($employee, $inep);
                    }
                }

                $importedPeople[$inep] = true;
            }

            $classInep = $row['class_inep'];
            $className = $row['class_name'];
            $classKey = $classInep . '|' . $className;

            if ($classInep !== '' && $className !== '' && ! isset($importedClasses[$classKey])) {
                $this->matcher->updateSchoolClassByName($className, $classInep);
                $importedClasses[$classKey] = true;
            }
        }

        $this->educacensoInepImport->update([
            'status_id' => EducacensoImportStatus::SUCCESS,
        ]);

        (new NotificationService())->createByUser(
            userId: $this->educacensoInepImport->user_id,
            text: "Foram importados os INEPs da escola {$this->data['school_name']}. Clique aqui para visualizar.",
            link: route('educacenso.import.inep.index'),
            type: NotificationType::OTHER
        );
    }

    public function failed(): void
    {
        $this->educacensoInepImport->update([
            'status_id' => EducacensoImportStatus::ERROR,
        ]);
    }
}
