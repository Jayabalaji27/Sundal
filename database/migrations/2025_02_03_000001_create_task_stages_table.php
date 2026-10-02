<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('color')->default('#3b82f6');
            $table->integer('order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['workspace_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_stages');
    }
};