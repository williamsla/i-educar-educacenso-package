<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('educacenso_identification_imports', function (Blueprint $table): void {
            $table->id();
            $table->smallInteger('year');
            $table->string('file_name');
            $table->bigInteger('user_id');
            $table->smallInteger('status_id');
            $table->unsignedInteger('imported_count')->nullable();
            $table->unsignedInteger('skipped_count')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('educacenso_identification_imports');
    }
};
