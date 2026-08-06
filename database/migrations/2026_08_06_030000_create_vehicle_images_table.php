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
        Schema::create('vehicle_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->boolean('is_cover')->default(false);
            $table->timestamps();
        });

        // "Exactly one cover image per vehicle" cannot be expressed with a
        // fluent unique index: a plain unique on `vehicle_id` would forbid
        // more than one image per vehicle at all, and Laravel's schema
        // builder has no method for a *partial* unique index. A raw
        // statement is the only way to scope the uniqueness to rows where
        // `is_cover` is true, letting any number of non-cover images share
        // a `vehicle_id`.
        DB::statement('create unique index vehicle_images_one_cover_per_vehicle on vehicle_images (vehicle_id) where (is_cover = true)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_images');
    }
};
