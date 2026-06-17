<?php

use App\Menu;
use App\Models\LegacyUserType;
use App\Process;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    private function attachMenuIfNotExists($userTypes, Menu $menu): void
    {
        $userTypes->each(static function (LegacyUserType $userType) use ($menu): void {
            $exists = $userType->menus()->where('menu_id', $menu->getKey())->exists();

            if (! $exists) {
                $userType->menus()->attach($menu, [
                    'visualiza' => 1,
                    'cadastra' => 1,
                    'exclui' => 1,
                ]);
            }
        });
    }

    private function userTypesWithMenuAccess(int $process): \Illuminate\Support\Collection
    {
        $referenceMenu = Menu::query()->where('process', $process)->first();

        if ($referenceMenu === null) {
            return LegacyUserType::all();
        }

        return LegacyUserType::query()
            ->whereHas('menus', static fn ($query) => $query->where('menu_id', $referenceMenu->getKey()))
            ->get();
    }

    public function up(): void
    {
        $parentId = Menu::query()
            ->where('old', Process::EDUCACENSO_IMPORTACOES)
            ->value('id');

        if ($parentId === null) {
            return;
        }

        $menu = Menu::updateOrCreate([
            'process' => Process::EDUCACENSO_IMPORT_IDENTIFICATION,
        ], [
            'parent_id' => $parentId,
            'parent_old' => Process::EDUCACENSO_IMPORTACOES,
            'title' => 'Importação de Identificação',
            'description' => 'Importação do retorno do arquivo de identificação do educacenso',
            'link' => '/educacenso/importacao/identificacao',
            'order' => 2,
            'type' => 3,
            'old' => Process::EDUCACENSO_IMPORT_IDENTIFICATION,
            'active' => true,
        ]);

        $userTypes = $this->userTypesWithMenuAccess(Process::EDUCACENSO_IMPORT_INEP);

        if ($userTypes->isEmpty()) {
            $userTypes = LegacyUserType::all();
        }

        $this->attachMenuIfNotExists($userTypes, $menu);
    }

    public function down(): void
    {
    }
};
