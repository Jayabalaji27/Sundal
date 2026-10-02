<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('todo_members')) {
            Schema::create('todo_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('todo_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();

                $table->index(['todo_id', 'user_id']);
                $table->index('user_id');

                $table->foreign('todo_id')->references('id')->on('todos')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('todo_members');
    }
};
