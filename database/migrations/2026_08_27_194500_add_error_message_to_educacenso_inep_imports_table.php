<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('educacenso_inep_imports', function (Blueprint $table): void {
            $table->text('error_message')->nullable()->after('status_id');
        });
    }

    public function down(): void
    {
        Schema::table('educacenso_inep_imports', function (Blueprint $table): void {
            $table->dropColumn('error_message');
        });
    }
};
