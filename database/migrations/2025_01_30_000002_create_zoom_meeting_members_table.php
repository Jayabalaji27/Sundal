<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('zoom_meeting_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zoom_meeting_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['zoom_meeting_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('zoom_meeting_members');
    }
};