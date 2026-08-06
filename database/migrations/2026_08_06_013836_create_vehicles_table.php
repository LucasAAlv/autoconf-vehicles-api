<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('placa')->unique();
            $table->string('chassi', 17)->unique();
            $table->string('marca')->index();
            $table->string('modelo')->index();
            $table->string('versao');
            $table->decimal('valor_venda', 15, 2)->index();
            $table->string('cor');
            $table->unsignedInteger('km')->index();
            $table->string('cambio');
            $table->string('combustivel');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // Postgres has no native "unsigned" modifier, unlike MySQL, so
        // `unsignedInteger` alone does not stop negative values from being
        // stored. A check constraint is the only way to enforce "km >= 0"
        // at the database level here.
        DB::statement('alter table vehicles add constraint vehicles_km_non_negative check (km >= 0)');

        // `chassi` must be exactly 17 characters (VIN format). `string(17)`
        // only caps the maximum length, so a check constraint enforces the
        // exact length invariant at the database level.
        DB::statement('alter table vehicles add constraint vehicles_chassi_length check (char_length(chassi) = 17)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
