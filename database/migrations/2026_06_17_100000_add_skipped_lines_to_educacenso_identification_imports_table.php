<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('educacenso_identification_imports', function (Blueprint $table): void {
            $table->json('skipped_lines')->nullable()->after('skipped_count');
        });
    }

    public function down(): void
    {
        Schema::table('educacenso_identification_imports', function (Blueprint $table): void {
            $table->dropColumn('skipped_lines');
        });
    }
};
