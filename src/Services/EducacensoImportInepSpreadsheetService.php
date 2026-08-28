<?php

namespace iEducar\Packages\Educacenso\Services;

use App\Models\NotificationType;
use App\Services\NotificationService;
use iEducar\Packages\Educacenso\Enums\EducacensoInepImportLayout;
use iEducar\Packages\Educacenso\Models\EducacensoInepImport;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        $failedRows = 0;

        foreach ($this->data['rows'] as $index => $row) {
            try {
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
            } catch (Throwable $exception) {
                $failedRows++;
                Log::error('Falha ao importar INEP da planilha.', [
                    'import_id' => $this->educacensoInepImport->getKey(),
                    'row' => $index + 1,
                    'inep' => $row['inep'] ?? null,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->educacensoInepImport->markAsSuccess(
            EducacensoImportErrorMessage::fromLineFailures($failedRows)
        );

        $this->notifyUser();
    }

    private function notifyUser(): void
    {
        try {
            (new NotificationService())->createByUser(
                userId: $this->educacensoInepImport->user_id,
                text: "Foram importados os INEPs da escola {$this->data['school_name']}. Clique aqui para visualizar.",
                link: route('educacenso.import.inep.index'),
                type: NotificationType::OTHER
            );
        } catch (Throwable $exception) {
            Log::warning('Não foi possível notificar o usuário após importar INEPs da planilha.', [
                'import_id' => $this->educacensoInepImport->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(?string $errorMessage = null): void
    {
        $this->educacensoInepImport->markAsError($errorMessage);
    }
}
