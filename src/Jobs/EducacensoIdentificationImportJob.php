<?php

namespace iEducar\Packages\Educacenso\Jobs;

use iEducar\Packages\Educacenso\Models\EducacensoIdentificationImport;
use iEducar\Packages\Educacenso\Services\EducacensoImportIdentificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class EducacensoIdentificationImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 3600;

    public function __construct(
        private EducacensoIdentificationImport $import,
        private array $lines
    ) {
    }

    public function handle(): void
    {
        $this->setConnection();
        (new EducacensoImportIdentificationService($this->import, $this->lines))->execute();
    }

    private function setConnection(): void
    {
        DB::setDefaultConnection($this->import->getConnectionName());
    }

    public function failed(Throwable $exception): void
    {
        $this->setConnection();
        (new EducacensoImportIdentificationService($this->import, $this->lines))->failed();
    }

    public function tags()
    {
        return [
            $this->import->getConnectionName(),
            'educacenso-identification-import',
        ];
    }
}
