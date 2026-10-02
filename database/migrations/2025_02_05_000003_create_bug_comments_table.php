<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bug_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bug_id')->constrained('bugs')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('comment');
            $table->json('mentions')->nullable();
            $table->timestamps();

            $table->index(['bug_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_comments');
    }
};