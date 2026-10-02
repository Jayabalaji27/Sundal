<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('budget_revision_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_revision_id');
            $table->unsignedBigInteger('approver_id');
            $table->enum('status', ['pending', 'approved', 'rejected']);
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['budget_revision_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('budget_revision_approvals');
    }
};