<?php

namespace iEducar\Packages\Educacenso\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Process;
use Exception;
use iEducar\Packages\Educacenso\Exception\ImportIdentificationException;
use iEducar\Packages\Educacenso\Http\Requests\EducacensoImportIdentificationRequest;
use iEducar\Packages\Educacenso\Jobs\EducacensoIdentificationImportJob;
use iEducar\Packages\Educacenso\Models\EducacensoIdentificationImport;
use iEducar\Packages\Educacenso\Services\EducacensoImportIdentificationService;
use Illuminate\Support\Facades\DB;

class ImportIdentificationController extends Controller
{
    public function index()
    {
        $this->breadcrumb('Importação de Identificação', [
            url('intranet/educar_educacenso_index.php') => 'Educacenso',
        ]);
        $this->menu(Process::EDUCACENSO_IMPORT_IDENTIFICATION);

        $imports = EducacensoIdentificationImport::query()
            ->orderByDesc('created_at')
            ->paginate();

        return view('educacenso::import-identification.index', [
            'imports' => $imports,
        ]);
    }

    public function create()
    {
        $this->menu(Process::EDUCACENSO_IMPORT_IDENTIFICATION);
        $this->breadcrumb('Importação de Identificação', [
            url('intranet/educar_educacenso_index.php') => 'Educacenso',
        ]);

        $years = config('educacenso.identification.years');
        rsort($years);

        return view('educacenso::import-identification.create', compact('years'));
    }

    public function store(EducacensoImportIdentificationRequest $request)
    {
        $files = $request->file('arquivos');
        $jobs = [];
        $fileCount = 0;

        try {
            DB::beginTransaction();

            foreach ($files as $file) {
                $lines = EducacensoImportIdentificationService::parseFile($file);

                $import = EducacensoIdentificationImport::create([
                    'year' => $request->integer('ano'),
                    'file_name' => $file->getClientOriginalName(),
                    'user_id' => $request->user()->getKey(),
                ]);

                $jobs[] = [$import, $lines];
                $fileCount++;
            }

            DB::commit();

            foreach ($jobs as $job) {
                EducacensoIdentificationImportJob::dispatch(...$job);
            }
        } catch (Exception $exception) {
            DB::rollBack();

            return redirect(route('educacenso.import.identification.create'))
                ->with('error', $exception instanceof ImportIdentificationException
                    ? $exception->getMessage()
                    : 'Não foi possível realizar a importação!');
        }

        return redirect()
            ->route('educacenso.import.identification.index')
            ->with('success', "Iniciado o processamento de {$fileCount} arquivo(s) de identificação.");
    }
}
