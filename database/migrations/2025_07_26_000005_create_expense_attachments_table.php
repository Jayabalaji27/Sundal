<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('expense_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_expense_id');
            $table->unsignedBigInteger('media_item_id');
            $table->unsignedBigInteger('uploaded_by');
            $table->string('attachment_type')->default('receipt'); // receipt, invoice, document
            $table->timestamps();

            $table->index('project_expense_id');
            
            $table->foreign('project_expense_id')->references('id')->on('project_expenses')->onDelete('cascade');
            $table->foreign('media_item_id')->references('id')->on('media_items')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('expense_attachments');
    }
};