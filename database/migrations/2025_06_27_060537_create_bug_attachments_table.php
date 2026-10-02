<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bug_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bug_id')->constrained('bugs')->onDelete('cascade');
            $table->foreignId('media_item_id')->constrained('media_items')->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['bug_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_attachments');
    }
};