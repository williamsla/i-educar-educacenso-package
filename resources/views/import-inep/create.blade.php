@extends('layout.default')

@section('content')
    @if(session('error'))
        <p style="text-align: center; margin-bottom: 15px; color: #a94442;">{{ session('error') }}</p>
    @endif

    <form id="formcadastro" action="{{ route('educacenso.import.inep.store') }}" method="post"
          enctype="multipart/form-data">
        <table class="tablecadastro" width="100%" border="0" cellpadding="2" cellspacing="0">
            <tbody>
            <tr>
                <td class="formdktd" colspan="2" height="24">
                    <b>Nova importação</b>
                </td>
            </tr>
            <tr id="tr_nm_ano">
                <td class="formmdtd" valign="top">
                    <span class="form">Ano</span>
                    <span class="campo_obrigatorio">*</span>
                    <br>
                    <sub style="vertical-align:top;">somente números</sub>
                </td>
                <td class="formmdtd" valign="top">
                    <span class="form">
                        <select name="ano" id="ano" required class="formcampo">
                            @foreach($years as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </span>
                </td>
            </tr>
            <tr id="tr_nm_tipo">
                <td class="formmdtd" valign="top">
                    <span class="form">Tipo de arquivo</span>
                    <span class="campo_obrigatorio">*</span>
                </td>
                <td class="formmdtd" valign="top">
                    <span class="form">
                        <select name="tipo" id="tipo" required class="formcampo">
                            <option value="txt">Arquivo TXT (Matrícula Inicial / relatório Censo)</option>
                            <option value="planilha_aluno">Planilha Relação de alunos da escola (2026)</option>
                            <option value="planilha_profissional">Planilha Relação de profissionais escolares (2026)</option>
                        </select>
                    </span>
                </td>
            </tr>
            <tr id="tr_nm_arquivo">
                <td class="formmdtd" valign="top" style="padding-bottom: 30px">
                    <span class="form">Arquivos</span>
                    <span class="campo_obrigatorio">*</span>
                </td>
                <td class="formmdtd" valign="top" style="padding-top: 20px;padding-bottom: 20px">
                   <span class="form">
                       <input data-multiple-caption="{count} arquivos" class="inputfile inputfile-buttom" name="arquivos[]" id="arquivos" type="file" accept=".txt" multiple required>
                       <label for="arquivos"><span></span> <strong>Escolha um arquivo</strong></label>&nbsp;<br>
                       <span id="arquivo-ajuda" style="font-style: italic; font-size: 10px;">* Somente arquivos com formato txt serão aceitos</span>
                   </span>
                </td>
            </tr>
            </tbody>
        </table>

        <div style="text-align: center">
            <button id="importButton" class="btn-green" type="submit">Importar Ineps</button>
        </div>
    </form>
@endsection

@prepend('scripts')
    <script>
        $j(document).ready(function () {
            var help = {
                txt: '* Somente arquivos com formato txt serão aceitos',
                planilha_aluno: '* Planilha xlsx: Relação de alunos(as) da escola (Censo Escolar 2026)',
                planilha_profissional: '* Planilha xlsx: Relação de profissionais escolares em sala de aula (Censo Escolar 2026)'
            };

            function updateFileFilter() {
                var tipo = $j('#tipo').val();
                $j('#arquivos').attr('accept', tipo === 'txt' ? '.txt' : '.xlsx');
                $j('#arquivo-ajuda').text(help[tipo] || help.txt);
            }

            $j('#tipo').on('change', updateFileFilter);
            updateFileFilter();

            $j('#formcadastro').submit(function () {
                $j('#importButton').prop('disabled', true);
            });
        });
    </script>
@endprepend

@prepend('styles')
    <link rel="stylesheet" type="text/css" href="{{ Asset::get('css/ieducar.css') }}"/>
@endprepend
