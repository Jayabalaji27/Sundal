<?php

namespace App\Support\EmailTemplates;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateLang;

/**
 * "Comment Mention": the email sent when someone @mentions a teammate in a task
 * comment. Shared by EmailTemplateSeeder (new installs) and the migration that
 * adds it to existing installs. On by default (EmailTemplate::DEFAULT_ON).
 */
class CommentMentionTemplate
{
    public const NAME = 'Comment Mention';

    /** Placeholders shown to the superadmin in the template editor. */
    public const VARIABLES = [
        '{mentioned_user_name}' => 'Name of the person mentioned',
        '{sender_name}' => 'Name of the person who wrote the comment',
        '{task_title}' => 'Title of the task',
        '{project_name}' => 'Name of the project',
        '{comment}' => 'The comment text',
        '{task_url}' => 'Link that opens the task',
        '{company_name}' => 'Company name',
    ];

    public static function definition(): array
    {
        $body = fn (string $hello, string $line, string $button, string $footer) =>
            "<p>{$hello} <strong>{mentioned_user_name}</strong>,</p>"
            . "<p>{$line}</p>"
            . '<blockquote style="margin:16px 0;padding:12px 16px;border-left:3px solid #6366f1;background:#f8fafc;color:#334155;">{comment}</blockquote>'
            . '<p><a href="{task_url}" style="display:inline-block;padding:10px 18px;border-radius:8px;background:#4f46e5;color:#ffffff;text-decoration:none;font-weight:600;">' . $button . '</a></p>'
            . "<p style=\"color:#64748b;font-size:13px;\">{$footer}</p>";

        return [
            'name' => self::NAME,
            'from' => '{company_name}',
            'translations' => [
                'en' => [
                    'subject' => '{sender_name} mentioned you on "{task_title}"',
                    'content' => $body('Hello', '<strong>{sender_name}</strong> mentioned you in a comment on the task <strong>{task_title}</strong> in <strong>{project_name}</strong>:', 'Open the task', 'Reply on the task so the whole team sees the answer.'),
                ],
                'es' => [
                    'subject' => '{sender_name} te mencionó en "{task_title}"',
                    'content' => $body('Hola', '<strong>{sender_name}</strong> te mencionó en un comentario de la tarea <strong>{task_title}</strong> en <strong>{project_name}</strong>:', 'Abrir la tarea', 'Responde en la tarea para que todo el equipo vea la respuesta.'),
                ],
                'ar' => [
                    'subject' => 'أشار إليك {sender_name} في "{task_title}"',
                    'content' => $body('مرحباً', 'أشار إليك <strong>{sender_name}</strong> في تعليق على المهمة <strong>{task_title}</strong> في <strong>{project_name}</strong>:', 'فتح المهمة', 'رُد على المهمة ليرى الفريق بأكمله الإجابة.'),
                ],
                'da' => [
                    'subject' => '{sender_name} nævnte dig i "{task_title}"',
                    'content' => $body('Hej', '<strong>{sender_name}</strong> nævnte dig i en kommentar til opgaven <strong>{task_title}</strong> i <strong>{project_name}</strong>:', 'Åbn opgaven', 'Svar på opgaven, så hele teamet ser svaret.'),
                ],
                'de' => [
                    'subject' => '{sender_name} hat Sie in "{task_title}" erwähnt',
                    'content' => $body('Hallo', '<strong>{sender_name}</strong> hat Sie in einem Kommentar zur Aufgabe <strong>{task_title}</strong> in <strong>{project_name}</strong> erwähnt:', 'Aufgabe öffnen', 'Antworten Sie in der Aufgabe, damit das ganze Team die Antwort sieht.'),
                ],
                'fr' => [
                    'subject' => '{sender_name} vous a mentionné dans « {task_title} »',
                    'content' => $body('Bonjour', '<strong>{sender_name}</strong> vous a mentionné dans un commentaire sur la tâche <strong>{task_title}</strong> du projet <strong>{project_name}</strong> :', 'Ouvrir la tâche', 'Répondez dans la tâche pour que toute l’équipe voie la réponse.'),
                ],
                'he' => [
                    'subject' => '{sender_name} הזכיר/ה אותך ב-"{task_title}"',
                    'content' => $body('שלום', '<strong>{sender_name}</strong> הזכיר/ה אותך בתגובה למשימה <strong>{task_title}</strong> בפרויקט <strong>{project_name}</strong>:', 'פתיחת המשימה', 'השב/י במשימה כדי שכל הצוות יראה את התשובה.'),
                ],
                'it' => [
                    'subject' => '{sender_name} ti ha menzionato in "{task_title}"',
                    'content' => $body('Ciao', '<strong>{sender_name}</strong> ti ha menzionato in un commento sull’attività <strong>{task_title}</strong> in <strong>{project_name}</strong>:', 'Apri l’attività', 'Rispondi nell’attività così tutto il team vede la risposta.'),
                ],
                'ja' => [
                    'subject' => '{sender_name} さんが「{task_title}」であなたをメンションしました',
                    'content' => $body('こんにちは', '<strong>{sender_name}</strong> さんが <strong>{project_name}</strong> のタスク <strong>{task_title}</strong> のコメントであなたをメンションしました：', 'タスクを開く', 'チーム全員が回答を見られるよう、タスク上で返信してください。'),
                ],
                'nl' => [
                    'subject' => '{sender_name} heeft je genoemd in "{task_title}"',
                    'content' => $body('Hallo', '<strong>{sender_name}</strong> heeft je genoemd in een opmerking bij de taak <strong>{task_title}</strong> in <strong>{project_name}</strong>:', 'Taak openen', 'Antwoord bij de taak, zodat het hele team het antwoord ziet.'),
                ],
                'pl' => [
                    'subject' => '{sender_name} wspomniał(a) o Tobie w „{task_title}”',
                    'content' => $body('Cześć', '<strong>{sender_name}</strong> wspomniał(a) o Tobie w komentarzu do zadania <strong>{task_title}</strong> w projekcie <strong>{project_name}</strong>:', 'Otwórz zadanie', 'Odpowiedz w zadaniu, aby cały zespół widział odpowiedź.'),
                ],
                'pt' => [
                    'subject' => '{sender_name} mencionou-o em "{task_title}"',
                    'content' => $body('Olá', '<strong>{sender_name}</strong> mencionou-o num comentário na tarefa <strong>{task_title}</strong> em <strong>{project_name}</strong>:', 'Abrir a tarefa', 'Responda na tarefa para que toda a equipa veja a resposta.'),
                ],
                'pt-BR' => [
                    'subject' => '{sender_name} mencionou você em "{task_title}"',
                    'content' => $body('Olá', '<strong>{sender_name}</strong> mencionou você em um comentário na tarefa <strong>{task_title}</strong> em <strong>{project_name}</strong>:', 'Abrir a tarefa', 'Responda na tarefa para que toda a equipe veja a resposta.'),
                ],
                'ru' => [
                    'subject' => '{sender_name} упомянул(а) вас в «{task_title}»',
                    'content' => $body('Здравствуйте,', '<strong>{sender_name}</strong> упомянул(а) вас в комментарии к задаче <strong>{task_title}</strong> в проекте <strong>{project_name}</strong>:', 'Открыть задачу', 'Ответьте в задаче, чтобы ответ увидела вся команда.'),
                ],
                'tr' => [
                    'subject' => '{sender_name} sizi "{task_title}" görevinde andı',
                    'content' => $body('Merhaba', '<strong>{sender_name}</strong>, <strong>{project_name}</strong> projesindeki <strong>{task_title}</strong> görevine yaptığı bir yorumda sizi andı:', 'Görevi aç', 'Tüm ekibin cevabı görmesi için görev üzerinde yanıtlayın.'),
                ],
                'zh' => [
                    'subject' => '{sender_name} 在“{task_title}”中提到了你',
                    'content' => $body('你好', '<strong>{sender_name}</strong> 在 <strong>{project_name}</strong> 的任务 <strong>{task_title}</strong> 的评论中提到了你：', '打开任务', '请在任务中回复，让整个团队都能看到答案。'),
                ],
            ],
        ];
    }

    /**
     * Adds the template and its translations when missing; leaves an existing
     * one (perhaps edited by the superadmin) untouched.
     */
    public static function install(array $languages, ?int $ownerId = null): void
    {
        $definition = self::definition();

        $template = EmailTemplate::firstOrCreate(
            ['name' => $definition['name']],
            ['from' => $definition['from'], 'user_id' => $ownerId ?? 1],
        );

        foreach ($languages as $lang) {
            $translation = $definition['translations'][$lang] ?? $definition['translations']['en'];
            EmailTemplateLang::firstOrCreate(
                ['parent_id' => $template->id, 'lang' => $lang],
                ['subject' => $translation['subject'], 'content' => $translation['content']],
            );
        }
    }
}
