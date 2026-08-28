<?php

namespace iEducar\Packages\Educacenso\Jobs;

use iEducar\Packages\Educacenso\Models\EducacensoInepImport;
use iEducar\Packages\Educacenso\Services\EducacensoImportErrorMessage;
use iEducar\Packages\Educacenso\Services\EducacensoImportInepService;
use iEducar\Packages\Educacenso\Services\EducacensoImportInepSpreadsheetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class EducacensoInepImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 3600;

    public function __construct(private EducacensoInepImport $educacensoInepImport, private array $data)
    {
    }

    public function handle(): void
    {
        set_time_limit(0);

        $this->setConnection();

        if (isset($this->data['layout'])) {
            (new EducacensoImportInepSpreadsheetService($this->educacensoInepImport, $this->data))->execute();

            return;
        }

        (new EducacensoImportInepService($this->educacensoInepImport, $this->data))->execute();
    }

    private function setConnection(): void
    {
        DB::setDefaultConnection($this->educacensoInepImport->getConnectionName());
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Falha no job de importação de INEP.', [
            'import_id' => $this->educacensoInepImport->getKey(),
            'school' => $this->educacensoInepImport->school_name,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        $this->setConnection();

        try {
            $errorMessage = EducacensoImportErrorMessage::fromThrowable($exception);

            if (isset($this->data['layout'])) {
                (new EducacensoImportInepSpreadsheetService($this->educacensoInepImport, $this->data))->failed($errorMessage);

                return;
            }

            (new EducacensoImportInepService($this->educacensoInepImport, $this->data))->failed($errorMessage);
        } catch (Throwable $nested) {
            Log::error('Não foi possível marcar a importação de INEP como erro.', [
                'import_id' => $this->educacensoInepImport->getKey(),
                'message' => $nested->getMessage(),
            ]);
        }
    }

    public function tags()
    {
        return [
            $this->educacensoInepImport->getConnectionName(),
            'educacenso-inep-import',
        ];
    }
}
