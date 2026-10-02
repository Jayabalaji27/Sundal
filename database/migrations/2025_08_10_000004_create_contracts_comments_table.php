<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contracts_comments')) {

            Schema::create('contracts_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->text('comment');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('contracts_comments')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            
            $table->index(['contract_id', 'parent_id']);
        });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('contracts_comments');
    }
};