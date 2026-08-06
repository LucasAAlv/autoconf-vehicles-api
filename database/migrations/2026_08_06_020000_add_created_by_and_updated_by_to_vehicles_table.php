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
            // Nullable and `restrictOnDelete()`, unlike `user_id`'s
            // `cascadeOnDelete()`: `user_id` is ownership, so deleting the
            // owner should delete their vehicles. `created_by`/`updated_by`
            // only record which user touched a vehicle they may not own
            // (e.g. an admin editing someone else's listing) — but there is
            // no user-delete feature in this app at all (users are only ever
            // deactivated, never removed, per product intent), so a user who
            // has ever created/edited a vehicle should block deletion rather
            // than silently null out the audit trail if that ever becomes
            // reachable in the future.
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
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
