<?php

namespace iEducar\Packages\Educacenso\Console;

use App\Menu;
use App\Models\LegacyUserType;
use App\Process;
use App\Services\MenuCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallImportIdentificationMenuCommand extends Command
{
    protected $signature = 'educacenso:install-import-identification-menu';

    protected $description = 'Cria o menu Importação de Identificação e concede permissão aos tipos de usuário';

    public function handle(): int
    {
        $parentId = Menu::query()
            ->where('old', Process::EDUCACENSO_IMPORTACOES)
            ->value('id');

        if ($parentId === null) {
            $this->error('Menu pai "Importações" (processo ' . Process::EDUCACENSO_IMPORTACOES . ') não encontrado.');

            return self::FAILURE;
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

        $referenceMenu = Menu::query()->where('process', Process::EDUCACENSO_IMPORT_INEP)->first();
        $userTypes = $referenceMenu
            ? LegacyUserType::query()
                ->whereHas('menus', static fn ($query) => $query->where('menu_id', $referenceMenu->getKey()))
                ->get()
            : collect();

        if ($userTypes->isEmpty()) {
            $userTypes = LegacyUserType::all();
        }

        $attached = 0;

        $userTypes->each(static function (LegacyUserType $userType) use ($menu, &$attached): void {
            $exists = $userType->menus()->where('menu_id', $menu->getKey())->exists();

            if ($exists) {
                return;
            }

            $userType->menus()->attach($menu, [
                'visualiza' => 1,
                'cadastra' => 1,
                'exclui' => 1,
            ]);

            $attached++;
        });

        LegacyUserType::all()->each(static function (LegacyUserType $userType): void {
            app(MenuCacheService::class)->flushMenuTag($userType->getKey());
        });

        Artisan::call('cache:clear');

        $this->info("Menu criado/atualizado (id {$menu->getKey()}).");
        $this->info("Permissões concedidas a {$attached} tipo(s) de usuário.");
        $this->info('Cache de menus limpo. Faça logout e login novamente.');

        return self::SUCCESS;
    }
}
