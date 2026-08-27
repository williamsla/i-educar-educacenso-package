<?php

namespace iEducar\Packages\Educacenso\Layout\Export\Identification;

use iEducar\Modules\Educacenso\Formatters;

class IdentificationFormatter
{
    use Formatters;

    public function formatCpf(?string $cpf): ?string
    {
        if (empty($cpf)) {
            return null;
        }

        return $this->cpfToCenso($cpf);
    }

    public function formatBirthCertificate(?string $certificate): ?string
    {
        if (empty($certificate)) {
            return null;
        }

        return $this->convertStringToCertNovoFormato($certificate);
    }

    public function formatName(?string $name): ?string
    {
        if (empty($name)) {
            return null;
        }

        return $this->convertStringToAlpha($name);
    }

    public function formatBirthDate(?string $birthDate): ?string
    {
        if (empty($birthDate)) {
            return null;
        }

        return (new \DateTime($birthDate))->format('d/m/Y');
    }
}
