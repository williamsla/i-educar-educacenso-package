<?php

namespace iEducar\Packages\Educacenso\Http\Requests;

use iEducar\Packages\Educacenso\Enums\EducacensoInepImportLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EducacensoImportInepRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $spreadsheet = $this->isSpreadsheet();

        return [
            'ano' => [
                'required',
                'date_format:Y',
            ],
            'tipo' => [
                'required',
                Rule::in(EducacensoInepImportLayout::values()),
            ],
            'arquivos' => ['required',  'array', 'max:500'],
            'arquivos.*' => [
                'required',
                'file',
                'max:20000',
                $spreadsheet ? 'extensions:xlsx' : 'extensions:txt',
            ],
        ];
    }

    public function attributes()
    {
        return [
            'tipo' => 'Tipo de arquivo',
            'arquivos' => 'Arquivos',
            'arquivos.*' => 'Arquivos',
        ];
    }

    public function messages()
    {
        return [
            'ano.required' => 'Você precisa informar o ano',
            'tipo.required' => 'Você precisa selecionar o tipo de arquivo',
            'arquivos.*.extensions' => $this->isSpreadsheet()
                ? 'Somente planilhas no formato xlsx serão aceitas para o tipo selecionado'
                : 'Somente arquivos com formato txt serão aceitos para o tipo selecionado',
        ];
    }

    public function isSpreadsheet(): bool
    {
        return in_array($this->input('tipo'), EducacensoInepImportLayout::spreadsheets(), true);
    }
}
