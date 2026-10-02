<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('zoom_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('zoom_meeting_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->datetime('start_time');
            $table->datetime('end_time');
            $table->string('timezone')->default('UTC');
            $table->integer('duration'); // in minutes
            $table->text('join_url')->nullable();
            $table->text('start_url')->nullable();
            $table->string('password')->nullable();
            $table->json('attendees')->nullable(); // email list
            $table->enum('status', ['scheduled', 'started', 'ended', 'cancelled'])->default('scheduled');
            $table->enum('type', ['instant', 'scheduled', 'recurring'])->default('scheduled');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->json('zoom_settings')->nullable(); // additional zoom settings
            
            // Google Calendar Sync Fields
            // $table->boolean('sync_google_calendar')->default(false);
            
            $table->timestamps();
            
            $table->index(['user_id', 'start_time']);
            $table->index(['workspace_id', 'start_time']);
            $table->index(['project_id', 'start_time']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('zoom_meetings');
    }
};