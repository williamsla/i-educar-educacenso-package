<?php

namespace iEducar\Packages\Educacenso\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EducacensoImportIdentificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ano' => [
                'required',
                'integer',
                'in:' . implode(',', config('educacenso.identification.years')),
            ],
            'arquivos' => ['required', 'array', 'max:500'],
            'arquivos.*' => ['required', 'file', 'max:20000', 'mimes:txt'],
        ];
    }

    public function attributes(): array
    {
        return [
            'arquivos' => 'Arquivos',
            'arquivos.*' => 'Arquivos',
        ];
    }

    public function messages(): array
    {
        return [
            'ano.required' => 'Você precisa informar o ano',
            'ano.in' => 'O ano informado não possui layout de identificação disponível.',
        ];
    }
}
