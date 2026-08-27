<?php

namespace iEducar\Packages\Educacenso\Layout\Export\Identification\Layout2026;

use App\Models\LegacyStudent;
use Closure;
use iEducar\Modules\Educacenso\Validator\BirthCertificateValidator;
use iEducar\Packages\Educacenso\Helpers\ErrorMessage;
use iEducar\Packages\Educacenso\Layout\Export\Contracts\Validation;
use iEducar\Packages\Educacenso\Layout\Export\Identification\IdentificationFormatter;

class Record extends Validation
{
    private IdentificationFormatter $formatter;

    public function __construct(
        public array $data
    ) {
        $this->formatter = new IdentificationFormatter();
    }

    public function rules(): array
    {
        $students = $this->data;

        return [
            'alunos.*.1' => [
                'required',
                'max:20',
            ],
            'alunos.*.2' => [
                function ($attribute, $value, Closure $fail) use ($students): void {
                    if (is_null($value) || $value === '') {
                        return;
                    }

                    $index = (int) explode('.', $attribute)[1];
                    $studentId = $students[$index]['1'] ?? null;

                    $errorMessage = new ErrorMessage($fail, [
                        'key' => 'cod_aluno',
                        'value' => $studentId,
                        'breadcrumb' => 'Pessoas -> Cadastros -> Pessoas físicas -> Editar -> Campo: CPF',
                        'url' => '/intranet/atendidos_cad.php?cod_pessoa_fj=' . LegacyStudent::find($studentId)?->ref_idpes,
                    ]);

                    if (strlen($value) !== 11) {
                        $errorMessage->toString([
                            'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Número do CPF" está com tamanho diferente do especificado.',
                        ]);
                    } elseif (! ctype_digit($value)) {
                        $errorMessage->toString([
                            'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Número do CPF" foi preenchido com valor inválido.',
                        ]);
                    }
                },
            ],
            'alunos.*.3' => [
                function ($attribute, $value, Closure $fail) use ($students): void {
                    if (is_null($value) || $value === '') {
                        return;
                    }

                    $index = (int) explode('.', $attribute)[1];
                    $studentId = $students[$index]['1'] ?? null;
                    $birthDate = $students[$index]['5'] ?? null;

                    $errorMessage = new ErrorMessage($fail, [
                        'key' => 'cod_aluno',
                        'value' => $studentId,
                        'breadcrumb' => 'Pessoas -> Cadastros -> Pessoas físicas -> Editar -> Campo: Tipo certidão civil (novo formato)',
                        'url' => '/intranet/atendidos_cad.php?cod_pessoa_fj=' . LegacyStudent::find($studentId)?->ref_idpes,
                    ]);

                    if (strlen($value) !== 32) {
                        $errorMessage->toString([
                            'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Número da Matrícula (Registro Civil - Certidão de nascimento)" está com tamanho diferente do especificado.',
                        ]);

                        return;
                    }

                    $birthDateIso = $birthDate
                        ? \DateTime::createFromFormat('d/m/Y', $birthDate)?->format('Y-m-d')
                        : null;

                    $validator = new BirthCertificateValidator($value, $birthDateIso);

                    if (! $validator->isValid()) {
                        $errorMessage->toString([
                            'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Número da Matrícula (Registro Civil - Certidão de nascimento)" foi preenchido com valor inválido.',
                        ]);
                    }
                },
            ],
            'alunos.*.4' => [
                'required',
                'string',
                'max:100',
                function ($attribute, $value, Closure $fail) use ($students): void {
                    $index = (int) explode('.', $attribute)[1];
                    $studentId = $students[$index]['1'] ?? null;

                    $errorMessage = new ErrorMessage($fail, [
                        'key' => 'cod_aluno',
                        'value' => $studentId,
                        'breadcrumb' => 'Pessoas -> Cadastros -> Pessoas físicas -> Editar -> Campo: Nome',
                        'url' => '/intranet/atendidos_cad.php?cod_pessoa_fj=' . LegacyStudent::find($studentId)?->ref_idpes,
                    ]);

                    if (! $this->isValidAlphaName($value)) {
                        $errorMessage->toString([
                            'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Nome completo" foi preenchido com valor inválido.',
                        ]);
                    }
                },
            ],
            'alunos.*.5' => [
                'required',
                'date_format:d/m/Y',
            ],
            'alunos.*.6' => [
                'nullable',
                'max:100',
                function ($attribute, $value, Closure $fail) use ($students): void {
                    if (is_null($value) || $value === '') {
                        return;
                    }

                    $index = (int) explode('.', $attribute)[1];
                    $studentId = $students[$index]['1'] ?? null;

                    $errorMessage = new ErrorMessage($fail, [
                        'key' => 'cod_aluno',
                        'value' => $studentId,
                        'breadcrumb' => 'Pessoas -> Cadastros -> Pessoas físicas -> Editar -> Campo: Filiação 1',
                        'url' => '/intranet/atendidos_cad.php?cod_pessoa_fj=' . LegacyStudent::find($studentId)?->ref_idpes,
                    ]);

                    if (! $this->isValidAlphaName($value)) {
                        $errorMessage->toString([
                            'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Filiação 1" foi preenchido com valor inválido.',
                        ]);
                    }
                },
            ],
            'alunos.*.7' => [
                'nullable',
                'max:100',
                function ($attribute, $value, Closure $fail) use ($students): void {
                    if (is_null($value) || $value === '') {
                        return;
                    }

                    $index = (int) explode('.', $attribute)[1];
                    $studentId = $students[$index]['1'] ?? null;

                    $errorMessage = new ErrorMessage($fail, [
                        'key' => 'cod_aluno',
                        'value' => $studentId,
                        'breadcrumb' => 'Pessoas -> Cadastros -> Pessoas físicas -> Editar -> Campo: Filiação 2',
                        'url' => '/intranet/atendidos_cad.php?cod_pessoa_fj=' . LegacyStudent::find($studentId)?->ref_idpes,
                    ]);

                    if (! $this->isValidAlphaName($value)) {
                        $errorMessage->toString([
                            'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Filiação 2" foi preenchido com valor inválido.',
                        ]);
                    }
                },
            ],
            'alunos.*.8' => [
                'required',
                'digits:7',
            ],
            'alunos.*.9' => [
                'nullable',
                function ($attribute, $value, Closure $fail) use ($students): void {
                    if (! is_null($value) && $value !== '') {
                        $index = (int) explode('.', $attribute)[1];
                        $studentId = $students[$index]['1'] ?? null;

                        (new ErrorMessage($fail, [
                            'key' => 'cod_aluno',
                            'value' => $studentId,
                        ]))->toString([
                            'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Identificação única do aluno (Inep)" foi preenchido quando deveria não ser preenchido.',
                        ]);
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        $errorMessage = new ErrorMessage();

        return [
            'alunos.*.1.required' => $errorMessage->toString([
                'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Código do aluno na Entidade/Escola" não foi preenchido quando deveria ser preenchido.',
            ]),
            'alunos.*.1.max' => $errorMessage->toString([
                'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Código do aluno na Entidade/Escola" está com tamanho diferente do especificado.',
            ]),
            'alunos.*.4.required' => $errorMessage->toString([
                'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Nome completo" é uma informação obrigatória.',
            ]),
            'alunos.*.4.max' => $errorMessage->toString([
                'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Nome completo" está com tamanho diferente do especificado.',
            ]),
            'alunos.*.5.required' => $errorMessage->toString([
                'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Data de nascimento" não foi preenchido quando deveria ser preenchido.',
            ]),
            'alunos.*.5.date_format' => $errorMessage->toString([
                'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Data de nascimento" foi preenchido com valor inválido.',
            ]),
            'alunos.*.6.max' => $errorMessage->toString([
                'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Filiação 1" está com tamanho diferente do especificado.',
            ]),
            'alunos.*.7.max' => $errorMessage->toString([
                'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Filiação 2" está com tamanho diferente do especificado.',
            ]),
            'alunos.*.8.required' => $errorMessage->toString([
                'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Município de nascimento" não foi preenchido quando deveria ser preenchido.',
            ]),
            'alunos.*.8.digits' => $errorMessage->toString([
                'message' => 'Dados para formular o arquivo de identificação inválidos. O campo "Município de nascimento" foi preenchido com valor inválido.',
            ]),
        ];
    }

    private function isValidAlphaName(?string $value): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        return $value === $this->formatter->formatName($value);
    }
}
