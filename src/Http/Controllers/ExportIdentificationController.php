<?php

namespace iEducar\Packages\Educacenso\Http\Controllers;

use App\Http\Controllers\Controller;
use iEducar\Packages\Educacenso\Http\Requests\ExportIdentificationRequest;
use iEducar\Packages\Educacenso\Layout\Export\Identification\IdentificationFileBuilder;
use iEducar\Packages\Educacenso\Layout\Export\Identification\IdentificationRecordFactory;
use iEducar\Packages\Educacenso\Layout\Export\Identification\IdentificationRepositoryFactory;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportIdentificationController extends Controller
{
    public function create()
    {
        $this->breadcrumb('Nova Exportação', [
            url('/intranet/educar_configuracoes_index.php') => 'Configurações',
            route('export.index') => 'Exportações',
        ]);

        $this->menu(9998846);

        $years = config('educacenso.identification.years');
        rsort($years);

        return view('educacenso::export.identification', compact('years'));
    }

    public function store(ExportIdentificationRequest $request): StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        $year = (int) $request->get('year');
        $repository = IdentificationRepositoryFactory::fromYear($year);

        $array = [
            'alunos' => $repository->getStudentsToExport($year, (int) $request->get('school_id')),
        ];

        $record = IdentificationRecordFactory::fromYear($year, $array['alunos']);
        $validator = Validator::make($array, $record->rules(), $record->messages());

        if ($validator->fails()) {
            return redirect(route('export.identification.impediments'))
                ->withErrors($validator)
                ->withInput();
        }

        $schoolId = (int) $request->get('school_id');
        $name = IdentificationFileBuilder::buildFileName($schoolId, $year);
        $content = IdentificationFileBuilder::buildContent($array['alunos']);

        return response()->streamDownload(function () use ($content): void {
            echo mb_convert_encoding($content, 'ISO-8859-1', 'UTF-8');
        }, $name, [
            'Content-Type' => 'text/plain; charset=ISO-8859-1',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
        ]);
    }
}
