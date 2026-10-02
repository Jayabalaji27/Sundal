<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('todo_attachments')) {
            Schema::create('todo_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('todo_id')->constrained('todos')->onDelete('cascade');
                $table->string('file');
                $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();

                $table->index(['todo_id', 'created_at']);
                $table->index('uploaded_by');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_attachments');
    }
};
