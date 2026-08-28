<?php

namespace iEducar\Packages\Educacenso\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SchoolInep;
use App\Process;
use Carbon\Carbon;
use iEducar\Packages\Educacenso\Enums\EducacensoInepImportLayout;
use iEducar\Packages\Educacenso\Exception\ImportInepException;
use iEducar\Packages\Educacenso\Http\Requests\EducacensoImportInepRequest;
use iEducar\Packages\Educacenso\Jobs\EducacensoInepImportJob;
use iEducar\Packages\Educacenso\Models\EducacensoInepImport;
use iEducar\Packages\Educacenso\Services\EducacensoImportErrorMessage;
use iEducar\Packages\Educacenso\Services\EducacensoImportInepService;
use iEducar\Packages\Educacenso\Services\EducacensoImportInepSpreadsheetParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ImportInepController extends Controller
{
    public function index()
    {
        $this->breadcrumb('Importação INEPs', [
            url('intranet/educar_educacenso_index.php') => 'Educacenso',
        ]);
        $this->menu(Process::EDUCACENSO_IMPORT_INEP);
        $imports = EducacensoInepImport::query()
            ->orderByDesc('created_at')
            ->paginate();

        return view('educacenso::import-inep.index', [
            'imports' => $imports,
        ]);
    }

    public function store(EducacensoImportInepRequest $request)
    {
        $files = $request->file('arquivos');
        $jobs = [];
        $schoolCount = 0;
        $skippedWithoutStartDate = 0;
        $year = (int) $request->get('ano');
        $tipo = (string) $request->get('tipo');
        try {
            DB::beginTransaction();
            foreach ($files as $file) {
                if (in_array($tipo, EducacensoInepImportLayout::spreadsheets(), true)) {
                    $parsed = EducacensoImportInepSpreadsheetParser::parse($file, $tipo);
                    $this->validateSpreadsheetYear($parsed['year'], $year, $file->getClientOriginalName());
                    $this->validateSchoolInep((int) $parsed['school_inep'], $parsed['school_name']);
                    $educacensoInepImport = EducacensoInepImport::create([
                        'year' => $year,
                        'user_id' => $request->user()->getKey(),
                        'school_name' => $parsed['school_name'],
                    ]);
                    $schoolCount++;
                    $jobs[] = [
                        $educacensoInepImport,
                        $parsed,
                    ];

                    continue;
                }

                $fileName = $file->getClientOriginalName();
                $schoolsData = EducacensoImportInepService::getDataBySchool($file);
                foreach ($schoolsData as $schoolData) {
                    $schoolLine = explode('|', $schoolData[0] ?? '');
                    $schoolInep = $schoolLine[1] ?? '';
                    $schoolName = mb_strtoupper($schoolLine[5] ?? '');
                    $fileDate = trim((string) ($schoolLine[3] ?? ''));

                    if ($fileDate === '') {
                        $skippedWithoutStartDate++;
                        Log::info('Escola ignorada na importação de INEP por não ter data de início do ano letivo.', [
                            'file' => $fileName,
                            'school_inep' => $schoolInep,
                            'school_name' => $schoolName,
                        ]);

                        continue;
                    }

                    $this->validateFileYear($fileDate, $year, $fileName, $schoolInep, $schoolName);
                    $this->validateSchoolInep((int) $schoolInep, $schoolName);
                    $educacensoInepImport = EducacensoInepImport::create([
                        'year' => $year,
                        'user_id' => $request->user()->getKey(),
                        'school_name' => $schoolName,
                    ]);
                    $schoolCount++;
                    $jobs[] = [
                        $educacensoInepImport,
                        $schoolData,
                    ];
                }
            }
            DB::commit();
            foreach ($jobs as $job) {
                EducacensoInepImportJob::dispatch(...$job);
            }
        } catch (Throwable $exception) {
            DB::rollBack();

            return redirect(route('educacenso.import.inep.create'))
                ->with('error', EducacensoImportErrorMessage::fromThrowable($exception));
        }

        return redirect()->route('educacenso.import.inep.index')
            ->with('success', $this->storeSuccessMessage($schoolCount, $skippedWithoutStartDate));
    }

    private function validateSchoolInep(int $inep, string $schoolName): void
    {
        $doesntExist = SchoolInep::query()->where('cod_escola_inep', $inep)->doesntExist();
        if ($doesntExist) {
            throw new ImportInepException("Não foi possível encontrar a escola {$schoolName} com o INEP {$inep}");
        }
    }

    private function validateFileYear(string $fileDate, int $year, string $fileName, string $schoolInep, string $schoolName): void
    {
        $origin = $this->describeFileOrigin($fileName, $schoolInep, $schoolName);

        $validator = Validator::make(['year' => $fileDate], [
            'year' => [
                'required',
                'date_format:d/m/Y',
            ],
        ]);
        if ($validator->fails()) {
            throw new ImportInepException(
                "A data de início do ano letivo {$origin} é inválida: \"{$fileDate}\". Esperado o formato dd/mm/aaaa no 4º campo do registro 00."
            );
        }
        $fileYear = Carbon::createFromFormat('d/m/Y', $fileDate)->year;
        if ($year !== $fileYear) {
            throw new ImportInepException(
                "O ano selecionado foi {$year}, mas {$origin} é referente ao ano {$fileYear} (data de início do ano letivo: {$fileDate})."
            );
        }
    }

    private function describeFileOrigin(string $fileName, string $schoolInep, string $schoolName): string
    {
        $origin = "no arquivo \"{$fileName}\"";

        if ($schoolName !== '' && $schoolInep !== '') {
            return "{$origin} (escola {$schoolName}, INEP {$schoolInep})";
        }

        if ($schoolInep !== '') {
            return "{$origin} (INEP {$schoolInep})";
        }

        return $origin;
    }

    private function storeSuccessMessage(int $schoolCount, int $skippedWithoutStartDate): string
    {
        $skippedLabel = $skippedWithoutStartDate === 1
            ? '1 escola foi ignorada por não ter data de início do ano letivo no registro 00.'
            : "{$skippedWithoutStartDate} escolas foram ignoradas por não terem data de início do ano letivo no registro 00.";

        if ($schoolCount === 0 && $skippedWithoutStartDate > 0) {
            return "Nenhuma escola foi importada. {$skippedLabel}";
        }

        $message = "Iniciado o processamento dos INEPs de {$schoolCount} escolas.";

        if ($skippedWithoutStartDate > 0) {
            $message .= " {$skippedLabel}";
        }

        return $message;
    }

    private function validateSpreadsheetYear(?int $fileYear, int $year, string $fileName): void
    {
        if ($fileYear === null) {
            return;
        }

        if ($fileYear !== $year) {
            throw new ImportInepException(
                "O ano selecionado foi {$year}, mas a planilha \"{$fileName}\" é referente ao Censo Escolar {$fileYear}."
            );
        }
    }

    public function create()
    {
        $this->menu(Process::EDUCACENSO_IMPORT_INEP);
        $this->breadcrumb('Importação INEPs', [
            url('intranet/educar_educacenso_index.php') => 'Educacenso',
        ]);

        $years = config('educacenso.stage-2.years');
        rsort($years);

        return view('educacenso::import-inep.create', compact('years'));
    }
}
