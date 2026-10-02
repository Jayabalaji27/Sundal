<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('google_meetings')) {
            Schema::create('google_meetings', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->datetime('start_time');
                $table->datetime('end_time');
                $table->integer('duration'); // in minutes
                $table->text('join_url')->nullable();
                $table->text('start_url')->nullable();
                $table->enum('status', ['scheduled', 'started', 'ended', 'cancelled'])->default('scheduled');
                $table->enum('type', ['instant', 'scheduled', 'recurring'])->default('scheduled');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('project_id')->nullable();

                $table->string('google_calendar_event_id')->nullable();
                
                $table->timestamps();
                
                $table->index(['user_id', 'start_time']);
                $table->index(['workspace_id', 'start_time']);
                $table->index(['project_id', 'start_time']);
                $table->index('status');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('google_meetings');
    }
};