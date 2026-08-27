@extends('layout.default')

@push('styles')
    <link rel="stylesheet" type="text/css" href="{{ Asset::get('css/ieducar.css') }}"/>
@endpush

@section('content')
    <table class="table-default">
        <tr class="titulo-tabela-listagem">
            <th colspan="2">Importação de Identificação - Detalhe</th>
        </tr>
        <tr>
            <td style="font-weight:bold; width: 200px;">Arquivo</td>
            <td>{{ $import->file_name }}</td>
        </tr>
        <tr>
            <td style="font-weight:bold;">Ano</td>
            <td>{{ $import->year }}</td>
        </tr>
        <tr>
            <td style="font-weight:bold;">Situação</td>
            <td>{{ $import->status }}</td>
        </tr>
        <tr>
            <td style="font-weight:bold;">Importados</td>
            <td>{{ $import->imported_count ?? 0 }}</td>
        </tr>
        <tr>
            <td style="font-weight:bold;">Ignorados</td>
            <td>{{ $import->skipped_count ?? 0 }}</td>
        </tr>
    </table>

    @if(($import->skipped_count ?? 0) > 0)
        <div class="separator"></div>

        <table class="table-default">
            <tr class="titulo-tabela-listagem">
                <th colspan="3">Alunos ignorados na importação</th>
            </tr>
            <tr>
                <td style="font-weight:bold;">Código do aluno</td>
                <td style="font-weight:bold;">Nome</td>
                <td style="font-weight:bold;">Motivo</td>
            </tr>
            @foreach($import->skipped_lines ?? [] as $skipped)
                <tr>
                    <td>{{ $skipped['student_code'] ?: '-' }}</td>
                    <td>{{ $skipped['name'] }}</td>
                    <td>{{ $skipped['reason'] }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p style="text-align: center; margin-top: 20px;">Nenhum aluno foi ignorado nesta importação.</p>
    @endif

    <div style="text-align: center; margin-top: 30px; margin-bottom: 30px">
        <a href="{{ route('educacenso.import.identification.index') }}" class="btn-green">Voltar ao histórico</a>
    </div>
@endsection
