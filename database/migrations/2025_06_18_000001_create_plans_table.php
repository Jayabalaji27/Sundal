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
        if (isSaasMode()) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('name',100)->unique();
                $table->float('price',30, 2)->default(0);
                $table->float('yearly_price',30, 2)->nullable();
                $table->string('duration',100);
                $table->text('description')->nullable();
                $table->integer('max_users_per_workspace')->default(10);
                $table->integer('max_clients_per_workspace')->default(5);
                $table->integer('max_managers_per_workspace')->default(2);
                $table->integer('max_projects_per_workspace')->default(10);
                $table->integer('workspace_limit')->default(1);
                $table->float('storage_limit',15, 2)->default('0.00');
                $table->string('enable_chatgpt',255)->default('on');
                $table->string('is_trial')->nullable();
                $table->integer('trial_day')->default(0);
                $table->string('is_plan_enable')->default('on');
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (isSaasMode()) {
            Schema::dropIfExists('plans');
        }
    }
};