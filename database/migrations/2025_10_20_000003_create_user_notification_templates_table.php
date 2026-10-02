<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_notification_templates')) {

            Schema::create('user_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_active')->default(0);
            $table->string('type')->default('slack'); // slack, telegram
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('notification_templates')->onDelete('cascade');
            $table->unique(['user_id', 'template_id', 'type']);
        });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_templates');
    }
};