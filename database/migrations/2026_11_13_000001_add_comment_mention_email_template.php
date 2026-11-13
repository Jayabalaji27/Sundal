<?php

use App\Support\EmailTemplates\CommentMentionTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the "Comment Mention" email (sent when someone @mentions a teammate in a
 * task comment) to existing installs, in every language the other emails have.
 * It is on by default (EmailTemplate::DEFAULT_ON), so no per-company switch rows
 * are needed; owners can turn it off in Settings → Email Notifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        $languages = DB::table('email_template_langs')->distinct()->pluck('lang')->all()
            ?: ['en', 'es', 'ar', 'da', 'de', 'fr', 'he', 'it', 'ja', 'nl', 'pl', 'pt', 'pt-BR', 'ru', 'tr', 'zh'];

        $owner = DB::table('users')->where('type', 'superadmin')->value('id')
            ?? DB::table('email_templates')->value('user_id');

        CommentMentionTemplate::install($languages, $owner ? (int) $owner : null);
    }

    public function down(): void
    {
        DB::table('email_templates')->where('name', CommentMentionTemplate::NAME)->delete();
    }
};
