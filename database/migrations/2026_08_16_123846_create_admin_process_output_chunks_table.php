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
        Schema::create('admin_process_output_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_process_step_id')->constrained()->cascadeOnDelete();
            $table->string('stream', 10);
            $table->longText('output');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['admin_process_step_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_process_output_chunks');
    }
};
