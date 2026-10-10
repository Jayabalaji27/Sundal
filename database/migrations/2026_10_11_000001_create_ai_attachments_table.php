<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files given to the AI Assistant (a QA bug sheet, a BRD…). The file is
 * kept on the private disk; what Sundal read from it (text, sheets, rows)
 * is stored here so the assistant never re-reads the file. Deleted with
 * the chat, under the chat retention setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('ai_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_message_id')->nullable()->constrained()->nullOnDelete();
            // upload, sundal (a file already in Sundal) or google_drive
            $table->string('source', 20)->default('upload');
            $table->string('original_name');
            $table->string('extension', 10);
            $table->string('mime', 150)->nullable();
            $table->unsignedBigInteger('size');
            $table->string('disk', 30);
            $table->string('path');
            // spreadsheet, document or text
            $table->string('kind', 20);
            // ready, failed, or needs_ocr (a scanned PDF with no text)
            $table->string('status', 20);
            $table->text('error')->nullable();
            $table->longText('extracted_text')->nullable();
            // Sheets (headers, rows) or sections (title, offsets), pages, token estimate.
            $table->json('structure')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'user_id', 'ai_conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_attachments');
    }
};
