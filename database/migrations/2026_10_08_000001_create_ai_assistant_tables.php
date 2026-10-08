<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Assistant (BYOA): provider settings per workspace, chat history,
 * an audit row per tool call, and token usage for the monthly cap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->onDelete('cascade');
            $table->string('provider', 30);
            $table->string('model', 100);
            // Encrypted with the `encrypted` cast; never sent to the browser.
            $table->text('api_key');
            $table->string('api_key_last4', 4)->nullable();
            $table->string('azure_endpoint')->nullable();
            $table->string('azure_deployment', 100)->nullable();
            $table->string('organization', 100)->nullable();
            $table->unsignedBigInteger('monthly_token_cap')->nullable();
            $table->boolean('managers_enabled')->default(true);
            $table->unsignedSmallInteger('retention_days')->default(90);
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_passed')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'user_id', 'last_message_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['user', 'assistant']);
            $table->longText('content');
            $table->timestamps();
        });

        // The audit log. Rows are kept for the life of the workspace; when a
        // chat is deleted or expires, ai_conversation_id is nulled and the
        // prompt text is removed, but who/what/inputs/result stay.
        Schema::create('ai_tool_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained();
            $table->foreignId('ai_conversation_id')->nullable()->constrained()->nullOnDelete();
            // The assistant reply the card belongs under on the AI Assistant page.
            $table->foreignId('ai_message_id')->nullable()->constrained()->nullOnDelete();
            $table->text('prompt')->nullable();
            $table->string('tool', 80);
            $table->json('input')->nullable();
            $table->json('payload')->nullable();
            $table->text('summary')->nullable();
            $table->enum('status', ['pending', 'done', 'failed', 'cancelled', 'expired', 'undone']);
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('model', 100)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('ai_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30);
            $table->string('model', 100);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('ai_tool_calls');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_provider_settings');
    }
};
