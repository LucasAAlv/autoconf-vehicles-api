<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Nullable and `nullOnDelete()`, unlike `user_id`'s
            // `cascadeOnDelete()`: `user_id` is ownership, so deleting the
            // owner should delete their vehicles. `created_by`/`updated_by`
            // only record which user touched a vehicle they may not own
            // (e.g. an admin editing someone else's listing); deleting that
            // user must not cascade-delete every vehicle they ever edited,
            // so the audit trail is severed (set to null) instead.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
