@extends('layout.default')

@push('styles')
    <link rel="stylesheet" type="text/css" href="{{ Asset::get('css/ieducar.css') }}"/>
@endpush

@section('content')
    @if(session('success'))
        <p style="text-align: center; margin-bottom: 15px;">{{ session('success') }}</p>
    @endif

    @if(session('error'))
        <p style="text-align: center; margin-bottom: 15px; color: #a94442;">{{ session('error') }}</p>
    @endif

    <table class="table-default">
        <tr class="titulo-tabela-listagem">
            <th colspan="8">Importações de Identificação - Listagem</th>
        </tr>
        <tr>
            <td style="font-weight:bold;">Ano</td>
            <td style="font-weight:bold;">Arquivo</td>
            <td style="font-weight:bold;">Usuário</td>
            <td style="font-weight:bold;">Data</td>
            <td style="font-weight:bold;">Importados</td>
            <td style="font-weight:bold;">Ignorados</td>
            <td style="font-weight:bold;">Situação</td>
            <td style="font-weight:bold;">Detalhe</td>
        </tr>
        @forelse($imports as $import)
            <tr>
                <td>{{ $import->year }}</td>
                <td>{{ $import->file_name }}</td>
                <td>{{ $import->user->realName }}</td>
                <td>{{ $import->created_at->format('d/m/y H:i') }}</td>
                <td>{{ $import->imported_count ?? '-' }}</td>
                <td>{{ $import->skipped_count ?? '-' }}</td>
                <td>{{ $import->status }}</td>
                <td>
                    @if(($import->skipped_count ?? 0) > 0)
                        <a href="{{ route('educacenso.import.identification.show', $import) }}">Ver ignorados</a>
                    @else
                        -
                    @endif
                </td>
            </tr>
            @if(($import->skipped_count ?? 0) > 0 && !empty($import->skipped_lines))
                <tr>
                    <td colspan="8" style="font-size: 12px; background: #fff8d6; padding: 10px;">
                        <strong>Ignorados:</strong>
                        {{ collect($import->skipped_lines)->pluck('name')->implode(', ') }}
                    </td>
                </tr>
            @endif
        @empty
            <tr>
                <td colspan="8" align=center>Não há informação para ser apresentada</td>
            </tr>
        @endforelse
    </table>
    <div class="separator"></div>
    <div style="text-align: center">
        {{ $imports->links() }}
    </div>

    <div style="text-align: center; margin-top: 30px; margin-bottom: 30px">
        <a href="{{ route('educacenso.import.identification.create') }}" class="btn-green">Nova Importação</a>
    </div>
@endsection
