<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('google_meeting_members')) {
            Schema::create('google_meeting_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('google_meeting_id')->constrained('google_meetings')->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->timestamps();

                $table->unique(['google_meeting_id', 'user_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('google_meeting_members');
    }
};