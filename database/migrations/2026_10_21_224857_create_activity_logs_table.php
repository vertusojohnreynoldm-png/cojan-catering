<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // No FK constraint — a user (including the actor) can be hard
            // deleted (User has no SoftDeletes), and the log must survive
            // that, so the actor's name/role are snapshotted at log time
            // rather than resolved live via a relation.
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name');
            $table->string('actor_role')->nullable();

            $table->string('action');
            $table->string('category'); // auth | orders | admin | stock
            $table->text('description');

            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();

            $table->json('changes')->nullable();

            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable(); // summarized, e.g. "Chrome on Windows"

            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index('actor_id');
            $table->index('action');
            $table->index('category');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
