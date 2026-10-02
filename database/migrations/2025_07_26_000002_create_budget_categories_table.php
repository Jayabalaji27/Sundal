<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('budget_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_budget_id');
            $table->string('name');
            $table->decimal('allocated_amount', 15, 2);
            $table->string('color', 7)->default('#3B82F6');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_budget_id', 'sort_order']);
            
            $table->foreign('project_budget_id')->references('id')->on('project_budgets')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('budget_categories');
    }
};