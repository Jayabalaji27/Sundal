<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'workspace_id', 'key']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
            $table->index(['user_id', 'workspace_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};