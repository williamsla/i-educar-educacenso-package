<?php

namespace iEducar\Packages\Educacenso\Database\Factories;

use Database\Factories\LegacyUserFactory;
use iEducar\Packages\Educacenso\Models\EducacensoIdentificationImport;
use Illuminate\Database\Eloquent\Factories\Factory;

class EducacensoIdentificationImportFactory extends Factory
{
    protected $model = EducacensoIdentificationImport::class;

    public function definition(): array
    {
        return [
            'year' => 2026,
            'file_name' => 'ident_1_2026.txt',
            'user_id' => fn () => LegacyUserFactory::new()->current(),
        ];
    }
}
