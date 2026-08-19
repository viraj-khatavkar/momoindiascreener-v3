<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin_process_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_process_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('key', 100);
            $table->string('name');
            $table->text('description');
            $table->string('command', 150);
            $table->json('arguments');
            $table->text('command_line');
            $table->boolean('is_preview')->default(false);
            $table->string('status')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->integer('exit_code')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['admin_process_run_id', 'position']);
            $table->unique(['admin_process_run_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_process_steps');
    }
};
