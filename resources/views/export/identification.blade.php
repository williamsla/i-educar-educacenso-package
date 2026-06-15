@extends('layout.default')

@push('styles')
    <link rel="stylesheet" type="text/css" href="{{ Asset::get('css/ieducar.css') }}" />
@endpush

@section('content')
    <form id="formcadastro" target="_blank" action="{{ route('educacenso-export-identification') }}" method="post">
        @csrf
        <table class="tablecadastro" width="100%" border="0" cellpadding="2" cellspacing="0">
            <tbody>
            <tr>
                <td class="formdktd" colspan="2" height="24"><b>Exportação - Identificação</b></td>
            </tr>
            <tr>
                <td class="formlttd" valign="top">
                    <span class="form">
                        Ano
                    </span>
                    <span class="campo_obrigatorio">*</span>
                </td>
                <td class="formlttd" valign="top">
                    <span class="form">
                        <select name="ano" id="ano" required class="formcampo">
                            @foreach($years as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </span>
                </td>
            </tr>
            <tr>
                <td class="formlttd" valign="top">
                    <span class="form">
                        Instituição
                    </span>
                    <span class="campo_obrigatorio">*</span>
                </td>
                <td class="formlttd" valign="top">
                    <span class="form">
                        @include('form.select-institution')
                    </span>
                </td>
            </tr>
            <tr>
                <td class="formmdtd" valign="top">
                    <span class="form">
                        Escola
                    </span>
                    <span class="campo_obrigatorio">*</span>
                </td>
                <td class="formmdtd" valign="top">
                    <span class="form">
                        @include('form.select-school')
                    </span>
                </td>
            </tr>
            </tbody>
        </table>

        <div class="separator"></div>

        <div style="text-align: center; margin-bottom: 10px">
            <button id="export-button" class="btn-green" type="submit">Exportar</button>
        </div>

        <style>
            #export-button[disabled] {
                opacity: 0.7;
            }
        </style>
        <link type='text/css' rel='stylesheet' href='{{ Asset::get("/vendor/legacy/Portabilis/Assets/Plugins/Chosen/chosen.css") }}'>
        <script type='text/javascript' src='{{ Asset::get('/vendor/legacy/Portabilis/Assets/Plugins/Chosen/chosen.jquery.min.js') }}'></script>
        <script type="text/javascript" src="{{ Asset::get("/vendor/legacy/Portabilis/Assets/Javascripts/ClientApi.js") }}"></script>
        <script type="text/javascript" src="{{ Asset::get("/vendor/legacy/DynamicInput/Assets/Javascripts/DynamicInput.js") }}"></script>
        <script type="text/javascript" src="{{ Asset::get("/vendor/legacy/DynamicInput/Assets/Javascripts/Escola.js") }}"></script>
    </form>
@endsection
