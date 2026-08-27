<?php

namespace iEducar\Packages\Educacenso\Providers;

use App\Process;
use iEducar\Packages\Educacenso\Console\InstallImportIdentificationMenuCommand;
use iEducar\Packages\Educacenso\Http\Controllers\ExportIdentificationController;
use iEducar\Packages\Educacenso\Http\Controllers\ExportSituationController;
use iEducar\Packages\Educacenso\Http\Controllers\ImportIdentificationController;
use iEducar\Packages\Educacenso\Http\Controllers\ImportInepController;
use iEducar\Packages\Educacenso\Http\Controllers\ImportRegistrationController;
use iEducar\Packages\Educacenso\Http\Controllers\ImportSituationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EducacensoProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/../../config/educacenso.php',
            key: 'educacenso'
        );

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(
                paths: __DIR__ . '/../../database/migrations'
            );

            if (env('LEGACY_SEED_DATA', true)) {
                $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations/data');
            }
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallImportIdentificationMenuCommand::class,
            ]);
        }

        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'educacenso');
    }

    public function boot(): void
    {
        Route::group(['middleware' => ['web', 'ieducar.navigation', 'ieducar.footer', 'ieducar.suspended', 'auth', 'ieducar.checkresetpassword']], function (): void {
            Route::get('educacenso/export-situation', [ExportSituationController::class, 'create'])
                ->name('educacenso-export-situation');
            Route::post('/educacenso/export-situation', [ExportSituationController::class, 'store']);

            Route::get('educacenso/export-identification', [ExportIdentificationController::class, 'create'])
                ->name('educacenso-export-identification');
            Route::post('/educacenso/export-identification', [ExportIdentificationController::class, 'store']);

            Route::view('/impediments', 'educacenso::export.impediments')->name('export.impediments');
            Route::view('/impediments-identification', 'educacenso::export.identification-impediments')
                ->name('export.identification.impediments');

            Route::resource('educacenso/import-registrations', ImportRegistrationController::class)
                ->only(['index', 'create', 'store'])
                ->names('educacenso-import-registrations')
                ->middleware('can:view:' . Process::EDUCACENSO_IMPORT_HISTORY);

            Route::prefix('educacenso/importacao/inep')->middleware('can:modify:' . Process::EDUCACENSO_IMPORT_INEP)->group(function (): void {
                Route::get('create', [ImportInepController::class, 'create'])->name('educacenso.import.inep.create');
                Route::post('/', [ImportInepController::class, 'store'])->name('educacenso.import.inep.store');
                Route::get('/', [ImportInepController::class, 'index'])->name('educacenso.import.inep.index');
            });

            Route::prefix('educacenso/importacao/situacao')->middleware('can:modify:' . Process::EDUCACENSO_IMPORT_SITUATION)->group(function (): void {
                Route::get('create', [ImportSituationController::class, 'create'])->name('educacenso.import.situation.create');
                Route::post('/', [ImportSituationController::class, 'store'])->name('educacenso.import.situation.store');
                Route::get('/', [ImportSituationController::class, 'index'])->name('educacenso.import.situation.index');
            });

            Route::prefix('educacenso/importacao/identificacao')->middleware('can:modify:' . Process::EDUCACENSO_IMPORT_IDENTIFICATION)->group(function (): void {
                Route::get('create', [ImportIdentificationController::class, 'create'])->name('educacenso.import.identification.create');
                Route::post('/', [ImportIdentificationController::class, 'store'])->name('educacenso.import.identification.store');
                Route::get('/', [ImportIdentificationController::class, 'index'])->name('educacenso.import.identification.index');
                Route::get('{import}', [ImportIdentificationController::class, 'show'])->name('educacenso.import.identification.show');
            });
        });
    }
}
