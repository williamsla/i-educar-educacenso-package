<?php

use App\Menu;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        $educacensoMenu = Menu::query()
            ->where('title', 'Educacenso')
            ->first();

        $menuExportacao = Menu::query()
            ->where('title', 'Exportações')
            ->where('parent_id', $educacensoMenu?->getKey())
            ->first();

        if ($menuExportacao) {
            Menu::updateOrCreate([
                'process' => 9998846,
            ], [
                'parent_id' => $menuExportacao->getKey(),
                'title' => 'Identificação',
                'description' => 'Exportação do arquivo de identificação do educacenso',
                'link' => '/educacenso/export-identification',
                'order' => 3,
                'type' => 3,
                'parent_old' => 999932,
                'old' => 9998846,
                'active' => true,
            ]);
        }
    }

    public function down(): void
    {
        Menu::where('process', 9998846)
            ->delete();
    }
};
