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
            <th colspan="5">Importações - Listagem</th>
        </tr>
        <tr>
            <td style="font-weight:bold;">Ano</td>
            <td style="font-weight:bold;">Escola</td>
            <td style="font-weight:bold;">Usuário</td>
            <td style="font-weight:bold;">Data</td>
            <td style="font-weight:bold;">Situação</td>
        </tr>
        @forelse($imports as $import)
            <tr>
                <td>
                    {{ $import->year }}
                </td>
                <td>
                    {{ $import->school_name }}
                </td>
                <td>
                    {{ $import->user->realName }}
                </td>
                <td>
                    {{ $import->created_at->format('d/m/y H:i') }}
                </td>
                <td>
                    {{ $import->status }}
                </td>
            </tr>
            @if($import->detail)
                <tr>
                    <td colspan="5" style="font-size: 12px; padding: 10px; {{ $import->statusIsError() ? 'background: #f8d7da; color: #721c24;' : 'background: #fff8d6; color: #6c5a00;' }}">
                        <strong>{{ $import->statusIsError() ? 'Motivo:' : 'Observação:' }}</strong> {{ $import->detail }}
                    </td>
                </tr>
            @endif
        @empty
            <tr>
                <td colspan="5" align=center>Não há informação para ser apresentada</td>
            </tr>
        @endforelse
    </table>
    <div class="separator"></div>
    <div style="text-align: center">
        {{ $imports->links() }}
    </div>

    <div style="text-align: center; margin-top: 30px; margin-bottom: 30px">
        <a href="{{ route('educacenso.import.inep.create') }}" class="btn-green">Nova Importação</a>
    </div>
@endsection
