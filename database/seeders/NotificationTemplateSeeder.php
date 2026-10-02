<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use App\Models\NotificationTemplateLang;
use App\Models\UserNotificationTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $supportedLanguages = ['en', 'es', 'ar', 'da', 'de', 'fr', 'he', 'it', 'ja', 'nl', 'pl', 'pt', 'pt-BR', 'ru', 'tr', 'zh'];
        $langCodes = $supportedLanguages;

        $templates = [
            [
                'name' => 'New Task',
                'type' => 'slack',
                'translations' => [
                    'en' => [
                        'title' => 'New Task Assigned: {task_title}',
                        'content' => 'You have been assigned a new task "{task_title}". Priority: {priority}. Due date: {due_date}. Assigned by: {assigned_by}.'
                    ],
                    'es' => [
                        'title' => 'Nueva tarea asignada: {task_title}',
                        'content' => 'Se te ha asignado una nueva tarea "{task_title}". Prioridad: {priority}. Fecha de vencimiento: {due_date}. Asignado por: {assigned_by}.'
                    ],
                    'ar' => [
                        'title' => 'تم تعيين مهمة جديدة: {task_title}',
                        'content' => 'تم تعيين مهمة جديدة لك "{task_title}". الأولوية: {priority}. تاريخ الاستحقاق: {due_date}. تم التعيين بواسطة: {assigned_by}.'
                    ],
                    'da' => [
                        'title' => 'Ny opgave tildelt: {task_title}',
                        'content' => 'Du er blevet tildelt en ny opgave "{task_title}". Prioritet: {priority}. Forfaldsdato: {due_date}. Tildelt af: {assigned_by}.'
                    ],
                    'de' => [
                        'title' => 'Neue Aufgabe zugewiesen: {task_title}',
                        'content' => 'Ihnen wurde eine neue Aufgabe "{task_title}" zugewiesen. Priorität: {priority}. Fälligkeitsdatum: {due_date}. Zugewiesen von: {assigned_by}.'
                    ],
                    'fr' => [
                        'title' => 'Nouvelle tâche attribuée : {task_title}',
                        'content' => 'Une nouvelle tâche "{task_title}" vous a été attribuée. Priorité : {priority}. Date d\'échéance : {due_date}. Assigné par : {assigned_by}.'
                    ],
                    'he' => [
                        'title' => 'הוקצתה משימה חדשה: {task_title}',
                        'content' => 'הוקצתה לך משימה חדשה "{task_title}". עדיפות: {priority}. תאריך יעד: {due_date}. הוקצה על ידי: {assigned_by}.'
                    ],
                    'it' => [
                        'title' => 'Nuovo compito assegnato: {task_title}',
                        'content' => 'Ti è stato assegnato un nuovo compito "{task_title}". Priorità: {priority}. Scadenza: {due_date}. Assegnato da: {assigned_by}.'
                    ],
                    'ja' => [
                        'title' => '新しいタスクが割り当てられました: {task_title}',
                        'content' => 'あなたに新しいタスク「{task_title}」が割り当てられました。優先度: {priority}。期限: {due_date}。担当者: {assigned_by}。'
                    ],
                    'nl' => [
                        'title' => 'Nieuwe taak toegewezen: {task_title}',
                        'content' => 'Je hebt een nieuwe taak "{task_title}" toegewezen gekregen. Prioriteit: {priority}. Vervaldatum: {due_date}. Toegewezen door: {assigned_by}.'
                    ],
                    'pl' => [
                        'title' => 'Nowe zadanie przydzielone: {task_title}',
                        'content' => 'Przydzielono Ci nowe zadanie "{task_title}". Priorytet: {priority}. Termin: {due_date}. Przydzielone przez: {assigned_by}.'
                    ],
                    'pt' => [
                        'title' => 'Nova tarefa atribuída: {task_title}',
                        'content' => 'Você recebeu uma nova tarefa "{task_title}". Prioridade: {priority}. Data de vencimento: {due_date}. Atribuído por: {assigned_by}.'
                    ],
                    'pt-br' => [
                        'title' => 'Nova tarefa atribuída: {task_title}',
                        'content' => 'Você recebeu uma nova tarefa "{task_title}". Prioridade: {priority}. Data de vencimento: {due_date}. Atribuído por: {assigned_by}.'
                    ],
                    'ru' => [
                        'title' => 'Назначена новая задача: {task_title}',
                        'content' => 'Вам назначена новая задача "{task_title}". Приоритет: {priority}. Срок: {due_date}. Назначено: {assigned_by}.'
                    ],
                    'tr' => [
                        'title' => 'Yeni Görev Atandı: {task_title}',
                        'content' => 'Size yeni bir görev "{task_title}" atandı. Öncelik: {priority}. Bitiş tarihi: {due_date}. Atayan: {assigned_by}.'
                    ],
                    'zh' => [
                        'title' => '已分配新任务: {task_title}',
                        'content' => '您已被分配新任务"{task_title}"。优先级: {priority}。截止日期: {due_date}。分配人: {assigned_by}。'
                    ],
                ]
            ],



            [
                'name' => 'New Milestone',
                'type' => 'slack',
                'translations' => [
                    'en' => [
                        'title' => 'New Milestone: {milestone_title}',
                        'content' => 'Milestone "{milestone_title}" created in {project_name}. Due: {due_date}.'
                    ],
                    'es' => [
                        'title' => 'Nuevo hito: {milestone_title}',
                        'content' => 'Hito "{milestone_title}" creado en {project_name}. Vence: {due_date}.'
                    ],
                    'ar' => [
                        'title' => 'معلم جديد: {milestone_title}',
                        'content' => 'معلم "{milestone_title}" أُنشئ في {project_name}. الاستحقاق: {due_date}.'
                    ],
                    'da' => [
                        'title' => 'Ny milepæl: {milestone_title}',
                        'content' => 'Milepæl "{milestone_title}" oprettet i {project_name}. Forfald: {due_date}.'
                    ],
                    'de' => [
                        'title' => 'Neuer Meilenstein: {milestone_title}',
                        'content' => 'Meilenstein "{milestone_title}" in {project_name} erstellt. Fällig: {due_date}.'
                    ],
                    'fr' => [
                        'title' => 'Nouveau jalon : {milestone_title}',
                        'content' => 'Jalon "{milestone_title}" créé dans {project_name}. Échéance : {due_date}.'
                    ],
                    'he' => [
                        'title' => 'אבן דרך חדשה: {milestone_title}',
                        'content' => 'אבן דרך "{milestone_title}" נוצרה ב-{project_name}. יעד: {due_date}.'
                    ],
                    'it' => [
                        'title' => 'Nuova pietra miliare: {milestone_title}',
                        'content' => 'Pietra miliare "{milestone_title}" creata in {project_name}. Scadenza: {due_date}.'
                    ],
                    'ja' => [
                        'title' => '新マイルストーン: {milestone_title}',
                        'content' => 'マイルストーン「{milestone_title}」が {project_name} で作成。期限: {due_date}。'
                    ],
                    'nl' => [
                        'title' => 'Nieuwe mijlpaal: {milestone_title}',
                        'content' => 'Mijlpaal "{milestone_title}" aangemaakt in {project_name}. Vervalt: {due_date}.'
                    ],
                    'pl' => [
                        'title' => 'Nowy kamień milowy: {milestone_title}',
                        'content' => 'Kamień milowy "{milestone_title}" utworzony w {project_name}. Termin: {due_date}.'
                    ],
                    'pt' => [
                        'title' => 'Novo marco: {milestone_title}',
                        'content' => 'Marco "{milestone_title}" criado em {project_name}. Vencimento: {due_date}.'
                    ],
                    'pt-br' => [
                        'title' => 'Novo marco: {milestone_title}',
                        'content' => 'Marco "{milestone_title}" criado em {project_name}. Vencimento: {due_date}.'
                    ],
                    'ru' => [
                        'title' => 'Новый этап: {milestone_title}',
                        'content' => 'Этап "{milestone_title}" создан в {project_name}. Срок: {due_date}.'
                    ],
                    'tr' => [
                        'title' => 'Yeni Kilometre Taşı: {milestone_title}',
                        'content' => 'Kilometre taşı "{milestone_title}", {project_name} projesinde oluşturuldu. Son tarih: {due_date}.'
                    ],
                    'zh' => [
                        'title' => '新里程碑: {milestone_title}',
                        'content' => '里程碑"{milestone_title}"已在 {project_name} 中创建。截止: {due_date}。'
                    ],
                ]
            ],

            [
                'name' => 'New Task Comment',
                'type' => 'slack',
                'translations' => [
                    'en' => [
                        'title' => 'New Comment: {task_title}',
                        'content' => '{commenter_name} commented on "{task_title}": {comment_text}'
                    ],
                    'es' => [
                        'title' => 'Nuevo comentario: {task_title}',
                        'content' => '{commenter_name} comentó en "{task_title}": {comment_text}'
                    ],
                    'ar' => [
                        'title' => 'تعليق جديد: {task_title}',
                        'content' => '{commenter_name} علّق على "{task_title}": {comment_text}'
                    ],
                    'da' => [
                        'title' => 'Ny kommentar: {task_title}',
                        'content' => '{commenter_name} kommenterede på "{task_title}": {comment_text}'
                    ],
                    'de' => [
                        'title' => 'Neuer Kommentar: {task_title}',
                        'content' => '{commenter_name} kommentierte zu "{task_title}": {comment_text}'
                    ],
                    'fr' => [
                        'title' => 'Nouveau commentaire : {task_title}',
                        'content' => '{commenter_name} a commenté sur "{task_title}" : {comment_text}'
                    ],
                    'he' => [
                        'title' => 'תגובה חדשה: {task_title}',
                        'content' => '{commenter_name} הגיב על "{task_title}": {comment_text}'
                    ],
                    'it' => [
                        'title' => 'Nuovo commento: {task_title}',
                        'content' => '{commenter_name} ha commentato su "{task_title}": {comment_text}'
                    ],
                    'ja' => [
                        'title' => '新しいコメント: {task_title}',
                        'content' => '{commenter_name} が「{task_title}」にコメント: {comment_text}'
                    ],
                    'nl' => [
                        'title' => 'Nieuwe opmerking: {task_title}',
                        'content' => '{commenter_name} reageerde op "{task_title}": {comment_text}'
                    ],
                    'pl' => [
                        'title' => 'Nowy komentarz: {task_title}',
                        'content' => '{commenter_name} skomentował "{task_title}": {comment_text}'
                    ],
                    'pt' => [
                        'title' => 'Novo comentário: {task_title}',
                        'content' => '{commenter_name} comentou em "{task_title}": {comment_text}'
                    ],
                    'pt-br' => [
                        'title' => 'Novo comentário: {task_title}',
                        'content' => '{commenter_name} comentou em "{task_title}": {comment_text}'
                    ],
                    'ru' => [
                        'title' => 'Новый комментарий: {task_title}',
                        'content' => '{commenter_name} прокомментировал "{task_title}": {comment_text}'
                    ],
                    'tr' => [
                        'title' => 'Yeni Yorum: {task_title}',
                        'content' => '{commenter_name}, "{task_title}" görevine yorum yaptı: {comment_text}'
                    ],
                    'zh' => [
                        'title' => '新评论: {task_title}',
                        'content' => '{commenter_name} 对"{task_title}"发表评论: {comment_text}'
                    ],
                ]
            ],

            // Telegram templates with same names but different content
            [
                'name' => 'New Task',
                'type' => 'telegram',
                'translations' => [
                    'en' => [
                        'title' => 'New Task: {task_title}',
                        'content' => 'You have a new task "{task_title}". Priority: {priority}. Due: {due_date}.'
                    ],
                    'es' => [
                        'title' => 'Nueva tarea: {task_title}',
                        'content' => 'Tienes una nueva tarea "{task_title}". Prioridad: {priority}. Vence: {due_date}.'
                    ],
                    'ar' => [
                        'title' => 'مهمة جديدة: {task_title}',
                        'content' => 'لديك مهمة جديدة "{task_title}". الأولوية: {priority}. الاستحقاق: {due_date}.'
                    ],
                    'da' => [
                        'title' => 'Ny opgave: {task_title}',
                        'content' => 'Du har en ny opgave "{task_title}". Prioritet: {priority}. Forfald: {due_date}.'
                    ],
                    'de' => [
                        'title' => 'Neue Aufgabe: {task_title}',
                        'content' => 'Sie haben eine neue Aufgabe "{task_title}". Priorität: {priority}. Fällig: {due_date}.'
                    ],
                    'fr' => [
                        'title' => 'Nouvelle tâche : {task_title}',
                        'content' => 'Vous avez une nouvelle tâche "{task_title}". Priorité : {priority}. Échéance : {due_date}.'
                    ],
                    'he' => [
                        'title' => 'משימה חדשה: {task_title}',
                        'content' => 'יש לך משימה חדשה "{task_title}". עדיפות: {priority}. יעד: {due_date}.'
                    ],
                    'it' => [
                        'title' => 'Nuovo compito: {task_title}',
                        'content' => 'Hai un nuovo compito "{task_title}". Priorità: {priority}. Scadenza: {due_date}.'
                    ],
                    'ja' => [
                        'title' => '新しいタスク: {task_title}',
                        'content' => '新しいタスク「{task_title}」があります。優先度: {priority}。期限: {due_date}。'
                    ],
                    'nl' => [
                        'title' => 'Nieuwe taak: {task_title}',
                        'content' => 'Je hebt een nieuwe taak "{task_title}". Prioriteit: {priority}. Vervalt: {due_date}.'
                    ],
                    'pl' => [
                        'title' => 'Nowe zadanie: {task_title}',
                        'content' => 'Masz nowe zadanie "{task_title}". Priorytet: {priority}. Termin: {due_date}.'
                    ],
                    'pt' => [
                        'title' => 'Nova tarefa: {task_title}',
                        'content' => 'Você tem uma nova tarefa "{task_title}". Prioridade: {priority}. Vencimento: {due_date}.'
                    ],
                    'pt-br' => [
                        'title' => 'Nova tarefa: {task_title}',
                        'content' => 'Você tem uma nova tarefa "{task_title}". Prioridade: {priority}. Vencimento: {due_date}.'
                    ],
                    'ru' => [
                        'title' => 'Новая задача: {task_title}',
                        'content' => 'У вас новая задача "{task_title}". Приоритет: {priority}. Срок: {due_date}.'
                    ],
                    'tr' => [
                        'title' => 'Yeni Görev: {task_title}',
                        'content' => 'Yeni bir göreviniz var "{task_title}". Öncelik: {priority}. Bitiş: {due_date}.'
                    ],
                    'zh' => [
                        'title' => '新任务: {task_title}',
                        'content' => '您有新任务"{task_title}"。优先级: {priority}。截止: {due_date}。'
                    ],
                ]
            ],



            [
                'name' => 'New Milestone',
                'type' => 'telegram',
                'translations' => [
                    'en' => [
                        'title' => 'Milestone Created: {milestone_title}',
                        'content' => 'New milestone "{milestone_title}" in {project_name}. Due: {due_date}.'
                    ],
                    'es' => [
                        'title' => 'Hito creado: {milestone_title}',
                        'content' => 'Nuevo hito "{milestone_title}" en {project_name}. Vence: {due_date}.'
                    ],
                    'ar' => [
                        'title' => 'تم إنشاء معلم: {milestone_title}',
                        'content' => 'معلم جديد "{milestone_title}" في {project_name}. الاستحقاق: {due_date}.'
                    ],
                    'da' => [
                        'title' => 'Milepæl oprettet: {milestone_title}',
                        'content' => 'Ny milepæl "{milestone_title}" i {project_name}. Forfald: {due_date}.'
                    ],
                    'de' => [
                        'title' => 'Meilenstein erstellt: {milestone_title}',
                        'content' => 'Neuer Meilenstein "{milestone_title}" in {project_name}. Fällig: {due_date}.'
                    ],
                    'fr' => [
                        'title' => 'Jalon créé : {milestone_title}',
                        'content' => 'Nouveau jalon "{milestone_title}" dans {project_name}. Échéance : {due_date}.'
                    ],
                    'he' => [
                        'title' => 'אבן דרך נוצרה: {milestone_title}',
                        'content' => 'אבן דרך חדשה "{milestone_title}" ב-{project_name}. יעד: {due_date}.'
                    ],
                    'it' => [
                        'title' => 'Pietra miliare creata: {milestone_title}',
                        'content' => 'Nuova pietra miliare "{milestone_title}" in {project_name}. Scadenza: {due_date}.'
                    ],
                    'ja' => [
                        'title' => 'マイルストーン作成: {milestone_title}',
                        'content' => '{project_name} に新しいマイルストーン「{milestone_title}」。期限: {due_date}。'
                    ],
                    'nl' => [
                        'title' => 'Mijlpaal aangemaakt: {milestone_title}',
                        'content' => 'Nieuwe mijlpaal "{milestone_title}" in {project_name}. Vervalt: {due_date}.'
                    ],
                    'pl' => [
                        'title' => 'Kamień milowy utworzony: {milestone_title}',
                        'content' => 'Nowy kamień milowy "{milestone_title}" w {project_name}. Termin: {due_date}.'
                    ],
                    'pt' => [
                        'title' => 'Marco criado: {milestone_title}',
                        'content' => 'Novo marco "{milestone_title}" em {project_name}. Vencimento: {due_date}.'
                    ],
                    'pt-br' => [
                        'title' => 'Marco criado: {milestone_title}',
                        'content' => 'Novo marco "{milestone_title}" em {project_name}. Vencimento: {due_date}.'
                    ],
                    'ru' => [
                        'title' => 'Этап создан: {milestone_title}',
                        'content' => 'Новый этап "{milestone_title}" в {project_name}. Срок: {due_date}.'
                    ],
                    'tr' => [
                        'title' => 'Kilometre Taşı Oluşturuldu: {milestone_title}',
                        'content' => '{project_name} projesinde yeni kilometre taşı "{milestone_title}". Son tarih: {due_date}.'
                    ],
                    'zh' => [
                        'title' => '里程碑已创建: {milestone_title}',
                        'content' => '{project_name} 中的新里程碑"{milestone_title}"。截止: {due_date}。'
                    ],
                ]
            ],

            [
                'name' => 'New Task Comment',
                'type' => 'telegram',
                'translations' => [
                    'en' => [
                        'title' => 'Comment Added: {task_title}',
                        'content' => '{commenter_name} added a comment to "{task_title}": {comment_text}'
                    ],
                    'es' => [
                        'title' => 'Comentario agregado: {task_title}',
                        'content' => '{commenter_name} agregó un comentario a "{task_title}": {comment_text}'
                    ],
                    'ar' => [
                        'title' => 'تم إضافة تعليق: {task_title}',
                        'content' => '{commenter_name} أضاف تعليقاً على "{task_title}": {comment_text}'
                    ],
                    'da' => [
                        'title' => 'Kommentar tilføjet: {task_title}',
                        'content' => '{commenter_name} tilføjede en kommentar til "{task_title}": {comment_text}'
                    ],
                    'de' => [
                        'title' => 'Kommentar hinzugefügt: {task_title}',
                        'content' => '{commenter_name} fügte einen Kommentar zu "{task_title}" hinzu: {comment_text}'
                    ],
                    'fr' => [
                        'title' => 'Commentaire ajouté : {task_title}',
                        'content' => '{commenter_name} a ajouté un commentaire à "{task_title}" : {comment_text}'
                    ],
                    'he' => [
                        'title' => 'תגובה נוספה: {task_title}',
                        'content' => '{commenter_name} הוסיף תגובה ל-"{task_title}": {comment_text}'
                    ],
                    'it' => [
                        'title' => 'Commento aggiunto: {task_title}',
                        'content' => '{commenter_name} ha aggiunto un commento a "{task_title}": {comment_text}'
                    ],
                    'ja' => [
                        'title' => 'コメント追加: {task_title}',
                        'content' => '{commenter_name} が「{task_title}」にコメントを追加: {comment_text}'
                    ],
                    'nl' => [
                        'title' => 'Opmerking toegevoegd: {task_title}',
                        'content' => '{commenter_name} voegde een opmerking toe aan "{task_title}": {comment_text}'
                    ],
                    'pl' => [
                        'title' => 'Komentarz dodany: {task_title}',
                        'content' => '{commenter_name} dodał komentarz do "{task_title}": {comment_text}'
                    ],
                    'pt' => [
                        'title' => 'Comentário adicionado: {task_title}',
                        'content' => '{commenter_name} adicionou um comentário a "{task_title}": {comment_text}'
                    ],
                    'pt-br' => [
                        'title' => 'Comentário adicionado: {task_title}',
                        'content' => '{commenter_name} adicionou um comentário a "{task_title}": {comment_text}'
                    ],
                    'ru' => [
                        'title' => 'Комментарий добавлен: {task_title}',
                        'content' => '{commenter_name} добавил комментарий к "{task_title}": {comment_text}'
                    ],
                    'tr' => [
                        'title' => 'Yorum Eklendi: {task_title}',
                        'content' => '{commenter_name}, "{task_title}" görevine yorum ekledi: {comment_text}'
                    ],
                    'zh' => [
                        'title' => '评论已添加: {task_title}',
                        'content' => '{commenter_name} 为"{task_title}"添加了评论: {comment_text}'
                    ],
                ]
            ],

            // Add Milestone Status Updated for both Slack and Telegram
            [
                'name' => 'Milestone Status Updated',
                'type' => 'slack',
                'translations' => [
                    'en' => [
                        'title' => 'Milestone Updated: {milestone_title}',
                        'content' => 'Milestone "{milestone_title}" status changed to {status} by {updated_by}.'
                    ],
                    'es' => [
                        'title' => 'Hito actualizado: {milestone_title}',
                        'content' => 'Estado del hito "{milestone_title}" cambiado a {status} por {updated_by}.'
                    ],
                    'ar' => [
                        'title' => 'تحديث المعلم: {milestone_title}',
                        'content' => 'حالة المعلم "{milestone_title}" تغيرت إلى {status} بواسطة {updated_by}.'
                    ],
                    'da' => [
                        'title' => 'Milepæl opdateret: {milestone_title}',
                        'content' => 'Milepæl "{milestone_title}" status ændret til {status} af {updated_by}.'
                    ],
                    'de' => [
                        'title' => 'Meilenstein aktualisiert: {milestone_title}',
                        'content' => 'Meilenstein "{milestone_title}" Status geändert zu {status} von {updated_by}.'
                    ],
                    'fr' => [
                        'title' => 'Jalon mis à jour : {milestone_title}',
                        'content' => 'Statut du jalon "{milestone_title}" changé en {status} par {updated_by}.'
                    ],
                    'he' => [
                        'title' => 'אבן דרך עודכנה: {milestone_title}',
                        'content' => 'סטטוס אבן הדרך "{milestone_title}" שונה ל-{status} על ידי {updated_by}.'
                    ],
                    'it' => [
                        'title' => 'Pietra miliare aggiornata: {milestone_title}',
                        'content' => 'Stato della pietra miliare "{milestone_title}" cambiato in {status} da {updated_by}.'
                    ],
                    'ja' => [
                        'title' => 'マイルストーン更新: {milestone_title}',
                        'content' => 'マイルストーン「{milestone_title}」のステータスが {updated_by} により {status} に変更。'
                    ],
                    'nl' => [
                        'title' => 'Mijlpaal bijgewerkt: {milestone_title}',
                        'content' => 'Mijlpaal "{milestone_title}" status gewijzigd naar {status} door {updated_by}.'
                    ],
                    'pl' => [
                        'title' => 'Kamień milowy zaktualizowany: {milestone_title}',
                        'content' => 'Status kamienia milowego "{milestone_title}" zmieniony na {status} przez {updated_by}.'
                    ],
                    'pt' => [
                        'title' => 'Marco atualizado: {milestone_title}',
                        'content' => 'Status do marco "{milestone_title}" alterado para {status} por {updated_by}.'
                    ],
                    'pt-br' => [
                        'title' => 'Marco atualizado: {milestone_title}',
                        'content' => 'Status do marco "{milestone_title}" alterado para {status} por {updated_by}.'
                    ],
                    'ru' => [
                        'title' => 'Этап обновлен: {milestone_title}',
                        'content' => 'Статус этапа "{milestone_title}" изменен на {status} пользователем {updated_by}.'
                    ],
                    'tr' => [
                        'title' => 'Kilometre Taşı Güncellendi: {milestone_title}',
                        'content' => 'Kilometre taşı "{milestone_title}" durumu {updated_by} tarafından {status} olarak değiştirildi.'
                    ],
                    'zh' => [
                        'title' => '里程碑更新: {milestone_title}',
                        'content' => '里程碑"{milestone_title}"状态由 {updated_by} 更改为 {status}。'
                    ],
                ]
            ],

            [
                'name' => 'Milestone Status Updated',
                'type' => 'telegram',
                'translations' => [
                    'en' => [
                        'title' => 'Milestone Update: {milestone_title}',
                        'content' => 'Milestone "{milestone_title}" status changed to {status}.'
                    ],
                    'es' => [
                        'title' => 'Actualización de hito: {milestone_title}',
                        'content' => 'Estado del hito "{milestone_title}" cambiado a {status}.'
                    ],
                    'ar' => [
                        'title' => 'تحديث المعلم: {milestone_title}',
                        'content' => 'حالة المعلم "{milestone_title}" تغيرت إلى {status}.'
                    ],
                    'da' => [
                        'title' => 'Milepæl opdatering: {milestone_title}',
                        'content' => 'Milepæl "{milestone_title}" status ændret til {status}.'
                    ],
                    'de' => [
                        'title' => 'Meilenstein-Update: {milestone_title}',
                        'content' => 'Meilenstein "{milestone_title}" Status geändert zu {status}.'
                    ],
                    'fr' => [
                        'title' => 'Mise à jour de jalon : {milestone_title}',
                        'content' => 'Statut du jalon "{milestone_title}" changé en {status}.'
                    ],
                    'he' => [
                        'title' => 'עדכון אבן דרך: {milestone_title}',
                        'content' => 'סטטוס אבן הדרך "{milestone_title}" שונה ל-{status}.'
                    ],
                    'it' => [
                        'title' => 'Aggiornamento pietra miliare: {milestone_title}',
                        'content' => 'Stato della pietra miliare "{milestone_title}" cambiato in {status}.'
                    ],
                    'ja' => [
                        'title' => 'マイルストーン更新: {milestone_title}',
                        'content' => 'マイルストーン「{milestone_title}」のステータスが {status} に変更。'
                    ],
                    'nl' => [
                        'title' => 'Mijlpaal update: {milestone_title}',
                        'content' => 'Mijlpaal "{milestone_title}" status gewijzigd naar {status}.'
                    ],
                    'pl' => [
                        'title' => 'Aktualizacja kamienia milowego: {milestone_title}',
                        'content' => 'Status kamienia milowego "{milestone_title}" zmieniony na {status}.'
                    ],
                    'pt' => [
                        'title' => 'Atualização de marco: {milestone_title}',
                        'content' => 'Status do marco "{milestone_title}" alterado para {status}.'
                    ],
                    'pt-br' => [
                        'title' => 'Atualização de marco: {milestone_title}',
                        'content' => 'Status do marco "{milestone_title}" alterado para {status}.'
                    ],
                    'ru' => [
                        'title' => 'Обновление этапа: {milestone_title}',
                        'content' => 'Статус этапа "{milestone_title}" изменен на {status}.'
                    ],
                    'tr' => [
                        'title' => 'Kilometre Taşı Güncellemesi: {milestone_title}',
                        'content' => 'Kilometre taşı "{milestone_title}" durumu {status} olarak değiştirildi.'
                    ],
                    'zh' => [
                        'title' => '里程碑更新: {milestone_title}',
                        'content' => '里程碑"{milestone_title}"状态更改为 {status}。'
                    ],
                ]
            ],

            // Add missing templates for both Slack and Telegram
            [
                'name' => 'New Project',
                'type' => 'slack',
                'translations' => [
                    'en' => [
                        'title' => 'New Project Created: {project_name}',
                        'content' => 'A new project "{project_name}" has been created by {created_by}. Start Date: {start_date}. End Date: {end_date}.'
                    ],
                    'es' => [
                        'title' => 'Nuevo proyecto creado: {project_name}',
                        'content' => 'Se ha creado un nuevo proyecto "{project_name}" por {created_by}. Fecha de inicio: {start_date}. Fecha de fin: {end_date}.'
                    ],
                    'ar' => [
                        'title' => 'تم إنشاء مشروع جديد: {project_name}',
                        'content' => 'تم إنشاء مشروع جديد "{project_name}" بواسطة {created_by}. تاريخ البداية: {start_date}. تاريخ النهاية: {end_date}.'
                    ],
                    'da' => [
                        'title' => 'Nyt projekt oprettet: {project_name}',
                        'content' => 'Et nyt projekt "{project_name}" er blevet oprettet af {created_by}. Startdato: {start_date}. Slutdato: {end_date}.'
                    ],
                    'de' => [
                        'title' => 'Neues Projekt erstellt: {project_name}',
                        'content' => 'Ein neues Projekt "{project_name}" wurde von {created_by} erstellt. Startdatum: {start_date}. Enddatum: {end_date}.'
                    ],
                    'fr' => [
                        'title' => 'Nouveau projet créé : {project_name}',
                        'content' => 'Un nouveau projet "{project_name}" a été créé par {created_by}. Date de début : {start_date}. Date de fin : {end_date}.'
                    ],
                    'he' => [
                        'title' => 'פרויקט חדש נוצר: {project_name}',
                        'content' => 'פרויקט חדש "{project_name}" נוצר על ידי {created_by}. תאריך התחלה: {start_date}. תאריך סיום: {end_date}.'
                    ],
                    'it' => [
                        'title' => 'Nuovo progetto creato: {project_name}',
                        'content' => 'È stato creato un nuovo progetto "{project_name}" da {created_by}. Data di inizio: {start_date}. Data di fine: {end_date}.'
                    ],
                    'ja' => [
                        'title' => '新しいプロジェクトが作成されました: {project_name}',
                        'content' => '{created_by} により新しいプロジェクト「{project_name}」が作成されました。開始日: {start_date}。終了日: {end_date}。'
                    ],
                    'nl' => [
                        'title' => 'Nieuw project aangemaakt: {project_name}',
                        'content' => 'Een nieuw project "{project_name}" is aangemaakt door {created_by}. Startdatum: {start_date}. Einddatum: {end_date}.'
                    ],
                    'pl' => [
                        'title' => 'Nowy projekt utworzony: {project_name}',
                        'content' => 'Nowy projekt "{project_name}" został utworzony przez {created_by}. Data rozpoczęcia: {start_date}. Data zakończenia: {end_date}.'
                    ],
                    'pt' => [
                        'title' => 'Novo projeto criado: {project_name}',
                        'content' => 'Um novo projeto "{project_name}" foi criado por {created_by}. Data de início: {start_date}. Data de fim: {end_date}.'
                    ],
                    'pt-br' => [
                        'title' => 'Novo projeto criado: {project_name}',
                        'content' => 'Um novo projeto "{project_name}" foi criado por {created_by}. Data de início: {start_date}. Data de fim: {end_date}.'
                    ],
                    'ru' => [
                        'title' => 'Создан новый проект: {project_name}',
                        'content' => 'Новый проект "{project_name}" создан пользователем {created_by}. Дата начала: {start_date}. Дата окончания: {end_date}.'
                    ],
                    'tr' => [
                        'title' => 'Yeni Proje Oluşturuldu: {project_name}',
                        'content' => '"{project_name}" adlı yeni proje {created_by} tarafından oluşturuldu. Başlangıç tarihi: {start_date}. Bitiş tarihi: {end_date}.'
                    ],
                    'zh' => [
                        'title' => '已创建新项目: {project_name}',
                        'content' => '新项目"{project_name}"已由 {created_by} 创建。开始日期: {start_date}。结束日期: {end_date}。'
                    ],
                ]
            ],

            [
                'name' => 'New Project',
                'type' => 'telegram',
                'translations' => [
                    'en' => [
                        'title' => 'New Project: {project_name}',
                        'content' => 'Project "{project_name}" created by {created_by}. Start: {start_date}. End: {end_date}.'
                    ],
                    'es' => [
                        'title' => 'Nuevo proyecto: {project_name}',
                        'content' => 'Proyecto "{project_name}" creado por {created_by}. Inicio: {start_date}. Fin: {end_date}.'
                    ],
                    'ar' => [
                        'title' => 'مشروع جديد: {project_name}',
                        'content' => 'مشروع "{project_name}" تم إنشاؤه بواسطة {created_by}. البداية: {start_date}. النهاية: {end_date}.'
                    ],
                    'da' => [
                        'title' => 'Nyt projekt: {project_name}',
                        'content' => 'Projekt "{project_name}" oprettet af {created_by}. Start: {start_date}. Slut: {end_date}.'
                    ],
                    'de' => [
                        'title' => 'Neues Projekt: {project_name}',
                        'content' => 'Projekt "{project_name}" erstellt von {created_by}. Start: {start_date}. Ende: {end_date}.'
                    ],
                    'fr' => [
                        'title' => 'Nouveau projet : {project_name}',
                        'content' => 'Projet "{project_name}" créé par {created_by}. Début : {start_date}. Fin : {end_date}.'
                    ],
                    'he' => [
                        'title' => 'פרויקט חדש: {project_name}',
                        'content' => 'פרויקט "{project_name}" נוצר על ידי {created_by}. התחלה: {start_date}. סיום: {end_date}.'
                    ],
                    'it' => [
                        'title' => 'Nuovo progetto: {project_name}',
                        'content' => 'Progetto "{project_name}" creato da {created_by}. Inizio: {start_date}. Fine: {end_date}.'
                    ],
                    'ja' => [
                        'title' => '新プロジェクト: {project_name}',
                        'content' => 'プロジェクト「{project_name}」が {created_by} により作成。開始: {start_date}。終了: {end_date}。'
                    ],
                    'nl' => [
                        'title' => 'Nieuw project: {project_name}',
                        'content' => 'Project "{project_name}" aangemaakt door {created_by}. Start: {start_date}. Einde: {end_date}.'
                    ],
                    'pl' => [
                        'title' => 'Nowy projekt: {project_name}',
                        'content' => 'Projekt "{project_name}" utworzony przez {created_by}. Start: {start_date}. Koniec: {end_date}.'
                    ],
                    'pt' => [
                        'title' => 'Novo projeto: {project_name}',
                        'content' => 'Projeto "{project_name}" criado por {created_by}. Início: {start_date}. Fim: {end_date}.'
                    ],
                    'pt-br' => [
                        'title' => 'Novo projeto: {project_name}',
                        'content' => 'Projeto "{project_name}" criado por {created_by}. Início: {start_date}. Fim: {end_date}.'
                    ],
                    'ru' => [
                        'title' => 'Новый проект: {project_name}',
                        'content' => 'Проект "{project_name}" создан {created_by}. Начало: {start_date}. Конец: {end_date}.'
                    ],
                    'tr' => [
                        'title' => 'Yeni Proje: {project_name}',
                        'content' => 'Proje "{project_name}" {created_by} tarafından oluşturuldu. Başlangıç: {start_date}. Bitiş: {end_date}.'
                    ],
                    'zh' => [
                        'title' => '新项目: {project_name}',
                        'content' => '项目"{project_name}"由 {created_by} 创建。开始: {start_date}。结束: {end_date}。'
                    ],
                ]
            ],

            [
                'name' => 'Task Stage Updated',
                'type' => 'slack',
                'translations' => [
                    'en' => [
                        'title' => 'Task Stage Updated: {task_title}',
                        'content' => 'Task "{task_title}" moved from {old_stage} to {new_stage} by {updated_by}.'
                    ],
                    'es' => [
                        'title' => 'Etapa de tarea actualizada: {task_title}',
                        'content' => 'Tarea "{task_title}" movida de {old_stage} a {new_stage} por {updated_by}.'
                    ],
                    'ar' => [
                        'title' => 'تم تحديث مرحلة المهمة: {task_title}',
                        'content' => 'تم نقل المهمة "{task_title}" من {old_stage} إلى {new_stage} بواسطة {updated_by}.'
                    ],
                    'da' => [
                        'title' => 'Opgave fase opdateret: {task_title}',
                        'content' => 'Opgave "{task_title}" flyttet fra {old_stage} til {new_stage} af {updated_by}.'
                    ],
                    'de' => [
                        'title' => 'Aufgabenstufe aktualisiert: {task_title}',
                        'content' => 'Aufgabe "{task_title}" von {old_stage} zu {new_stage} verschoben von {updated_by}.'
                    ],
                    'fr' => [
                        'title' => 'Étape de tâche mise à jour : {task_title}',
                        'content' => 'Tâche "{task_title}" déplacée de {old_stage} vers {new_stage} par {updated_by}.'
                    ],
                    'he' => [
                        'title' => 'שלב המשימה עודכן: {task_title}',
                        'content' => 'המשימה "{task_title}" הועברה מ-{old_stage} ל-{new_stage} על ידי {updated_by}.'
                    ],
                    'it' => [
                        'title' => 'Fase del compito aggiornata: {task_title}',
                        'content' => 'Compito "{task_title}" spostato da {old_stage} a {new_stage} da {updated_by}.'
                    ],
                    'ja' => [
                        'title' => 'タスクステージ更新: {task_title}',
                        'content' => 'タスク「{task_title}」が {updated_by} により {old_stage} から {new_stage} に移動。'
                    ],
                    'nl' => [
                        'title' => 'Taak fase bijgewerkt: {task_title}',
                        'content' => 'Taak "{task_title}" verplaatst van {old_stage} naar {new_stage} door {updated_by}.'
                    ],
                    'pl' => [
                        'title' => 'Etap zadania zaktualizowany: {task_title}',
                        'content' => 'Zadanie "{task_title}" przeniesione z {old_stage} do {new_stage} przez {updated_by}.'
                    ],
                    'pt' => [
                        'title' => 'Etapa da tarefa atualizada: {task_title}',
                        'content' => 'Tarefa "{task_title}" movida de {old_stage} para {new_stage} por {updated_by}.'
                    ],
                    'pt-br' => [
                        'title' => 'Etapa da tarefa atualizada: {task_title}',
                        'content' => 'Tarefa "{task_title}" movida de {old_stage} para {new_stage} por {updated_by}.'
                    ],
                    'ru' => [
                        'title' => 'Этап задачи обновлен: {task_title}',
                        'content' => 'Задача "{task_title}" перемещена с {old_stage} на {new_stage} пользователем {updated_by}.'
                    ],
                    'tr' => [
                        'title' => 'Görev Aşaması Güncellendi: {task_title}',
                        'content' => 'Görev "{task_title}", {updated_by} tarafından {old_stage} aşamasından {new_stage} aşamasına taşındı.'
                    ],
                    'zh' => [
                        'title' => '任务阶段更新: {task_title}',
                        'content' => '任务"{task_title}"由 {updated_by} 从 {old_stage} 移至 {new_stage}。'
                    ],
                ]
            ],

            [
                'name' => 'Task Stage Updated',
                'type' => 'telegram',
                'translations' => [
                    'en' => [
                        'title' => 'Task Stage Update: {task_title}',
                        'content' => 'Task "{task_title}" moved from {old_stage} to {new_stage}.'
                    ],
                    'es' => [
                        'title' => 'Actualización de etapa: {task_title}',
                        'content' => 'Tarea "{task_title}" movida de {old_stage} a {new_stage}.'
                    ],
                    'ar' => [
                        'title' => 'تحديث مرحلة المهمة: {task_title}',
                        'content' => 'تم نقل المهمة "{task_title}" من {old_stage} إلى {new_stage}.'
                    ],
                    'da' => [
                        'title' => 'Opgave fase opdatering: {task_title}',
                        'content' => 'Opgave "{task_title}" flyttet fra {old_stage} til {new_stage}.'
                    ],
                    'de' => [
                        'title' => 'Aufgabenstufe-Update: {task_title}',
                        'content' => 'Aufgabe "{task_title}" von {old_stage} zu {new_stage} verschoben.'
                    ],
                    'fr' => [
                        'title' => 'Mise à jour étape : {task_title}',
                        'content' => 'Tâche "{task_title}" déplacée de {old_stage} vers {new_stage}.'
                    ],
                    'he' => [
                        'title' => 'עדכון שלב משימה: {task_title}',
                        'content' => 'המשימה "{task_title}" הועברה מ-{old_stage} ל-{new_stage}.'
                    ],
                    'it' => [
                        'title' => 'Aggiornamento fase: {task_title}',
                        'content' => 'Compito "{task_title}" spostato da {old_stage} a {new_stage}.'
                    ],
                    'ja' => [
                        'title' => 'タスクステージ更新: {task_title}',
                        'content' => 'タスク「{task_title}」が {old_stage} から {new_stage} に移動。'
                    ],
                    'nl' => [
                        'title' => 'Taak fase update: {task_title}',
                        'content' => 'Taak "{task_title}" verplaatst van {old_stage} naar {new_stage}.'
                    ],
                    'pl' => [
                        'title' => 'Aktualizacja etapu: {task_title}',
                        'content' => 'Zadanie "{task_title}" przeniesione z {old_stage} do {new_stage}.'
                    ],
                    'pt' => [
                        'title' => 'Atualização de etapa: {task_title}',
                        'content' => 'Tarefa "{task_title}" movida de {old_stage} para {new_stage}.'
                    ],
                    'pt-br' => [
                        'title' => 'Atualização de etapa: {task_title}',
                        'content' => 'Tarefa "{task_title}" movida de {old_stage} para {new_stage}.'
                    ],
                    'ru' => [
                        'title' => 'Обновление этапа: {task_title}',
                        'content' => 'Задача "{task_title}" перемещена с {old_stage} на {new_stage}.'
                    ],
                    'tr' => [
                        'title' => 'Aşama Güncellemesi: {task_title}',
                        'content' => 'Görev "{task_title}" {old_stage} aşamasından {new_stage} aşamasına taşındı.'
                    ],
                    'zh' => [
                        'title' => '阶段更新: {task_title}',
                        'content' => '任务"{task_title}"从 {old_stage} 移至 {new_stage}。'
                    ],
                ]
            ],

            // New Invoice templates
            [
                'name' => 'New Invoice',
                'type' => 'slack',
                'translations' => [
                    'en' => [
                        'title' => 'New Invoice Created: {invoice_number}',
                        'content' => 'Invoice {invoice_number} created for {client_name}. Amount: {amount}. Due: {due_date}.'
                    ],
                    'es' => [
                        'title' => 'Nueva factura creada: {invoice_number}',
                        'content' => 'Factura {invoice_number} creada para {client_name}. Importe: {amount}. Vence: {due_date}.'
                    ],
                    'ar' => [
                        'title' => 'تم إنشاء فاتورة جديدة: {invoice_number}',
                        'content' => 'تم إنشاء الفاتورة {invoice_number} للعميل {client_name}. المبلغ: {amount}. الاستحقاق: {due_date}.'
                    ],
                    'da' => [
                        'title' => 'Ny faktura oprettet: {invoice_number}',
                        'content' => 'Faktura {invoice_number} oprettet for {client_name}. Beløb: {amount}. Forfald: {due_date}.'
                    ],
                    'de' => [
                        'title' => 'Neue Rechnung erstellt: {invoice_number}',
                        'content' => 'Rechnung {invoice_number} für {client_name} erstellt. Betrag: {amount}. Fällig: {due_date}.'
                    ],
                    'fr' => [
                        'title' => 'Nouvelle facture créée : {invoice_number}',
                        'content' => 'Facture {invoice_number} créée pour {client_name}. Montant : {amount}. Échéance : {due_date}.'
                    ],
                    'he' => [
                        'title' => 'חשבונית חדשה נוצרה: {invoice_number}',
                        'content' => 'חשבונית {invoice_number} נוצרה עבור {client_name}. סכום: {amount}. יעד: {due_date}.'
                    ],
                    'it' => [
                        'title' => 'Nuova fattura creata: {invoice_number}',
                        'content' => 'Fattura {invoice_number} creata per {client_name}. Importo: {amount}. Scadenza: {due_date}.'
                    ],
                    'ja' => [
                        'title' => '新しい請求書作成: {invoice_number}',
                        'content' => '{client_name} の請求書 {invoice_number} が作成されました。金額: {amount}。期限: {due_date}。'
                    ],
                    'nl' => [
                        'title' => 'Nieuwe factuur aangemaakt: {invoice_number}',
                        'content' => 'Factuur {invoice_number} aangemaakt voor {client_name}. Bedrag: {amount}. Vervalt: {due_date}.'
                    ],
                    'pl' => [
                        'title' => 'Nowa faktura utworzona: {invoice_number}',
                        'content' => 'Faktura {invoice_number} utworzona dla {client_name}. Kwota: {amount}. Termin: {due_date}.'
                    ],
                    'pt' => [
                        'title' => 'Nova fatura criada: {invoice_number}',
                        'content' => 'Fatura {invoice_number} criada para {client_name}. Valor: {amount}. Vencimento: {due_date}.'
                    ],
                    'pt-br' => [
                        'title' => 'Nova fatura criada: {invoice_number}',
                        'content' => 'Fatura {invoice_number} criada para {client_name}. Valor: {amount}. Vencimento: {due_date}.'
                    ],
                    'ru' => [
                        'title' => 'Создан новый счет: {invoice_number}',
                        'content' => 'Счет {invoice_number} создан для {client_name}. Сумма: {amount}. Срок: {due_date}.'
                    ],
                    'tr' => [
                        'title' => 'Yeni Fatura Oluşturuldu: {invoice_number}',
                        'content' => '{client_name} için {invoice_number} numaralı fatura oluşturuldu. Tutar: {amount}. Vade: {due_date}.'
                    ],
                    'zh' => [
                        'title' => '已创建新发票: {invoice_number}',
                        'content' => '为 {client_name} 创建发票 {invoice_number}。金额: {amount}。到期: {due_date}。'
                    ],
                ]
            ],

            [
                'name' => 'New Invoice',
                'type' => 'telegram',
                'translations' => [
                    'en' => [
                        'title' => 'New Invoice: {invoice_number}',
                        'content' => 'Invoice {invoice_number} for {client_name}. Amount: {amount}. Due: {due_date}.'
                    ],
                    'es' => [
                        'title' => 'Nueva factura: {invoice_number}',
                        'content' => 'Factura {invoice_number} para {client_name}. Importe: {amount}. Vence: {due_date}.'
                    ],
                    'ar' => [
                        'title' => 'فاتورة جديدة: {invoice_number}',
                        'content' => 'فاتورة {invoice_number} للعميل {client_name}. المبلغ: {amount}. الاستحقاق: {due_date}.'
                    ],
                    'da' => [
                        'title' => 'Ny faktura: {invoice_number}',
                        'content' => 'Faktura {invoice_number} for {client_name}. Beløb: {amount}. Forfald: {due_date}.'
                    ],
                    'de' => [
                        'title' => 'Neue Rechnung: {invoice_number}',
                        'content' => 'Rechnung {invoice_number} für {client_name}. Betrag: {amount}. Fällig: {due_date}.'
                    ],
                    'fr' => [
                        'title' => 'Nouvelle facture : {invoice_number}',
                        'content' => 'Facture {invoice_number} pour {client_name}. Montant : {amount}. Échéance : {due_date}.'
                    ],
                    'he' => [
                        'title' => 'חשבונית חדשה: {invoice_number}',
                        'content' => 'חשבונית {invoice_number} עבור {client_name}. סכום: {amount}. יעד: {due_date}.'
                    ],
                    'it' => [
                        'title' => 'Nuova fattura: {invoice_number}',
                        'content' => 'Fattura {invoice_number} per {client_name}. Importo: {amount}. Scadenza: {due_date}.'
                    ],
                    'ja' => [
                        'title' => '新請求書: {invoice_number}',
                        'content' => '{client_name} の請求書 {invoice_number}。金額: {amount}。期限: {due_date}。'
                    ],
                    'nl' => [
                        'title' => 'Nieuwe factuur: {invoice_number}',
                        'content' => 'Factuur {invoice_number} voor {client_name}. Bedrag: {amount}. Vervalt: {due_date}.'
                    ],
                    'pl' => [
                        'title' => 'Nowa faktura: {invoice_number}',
                        'content' => 'Faktura {invoice_number} dla {client_name}. Kwota: {amount}. Termin: {due_date}.'
                    ],
                    'pt' => [
                        'title' => 'Nova fatura: {invoice_number}',
                        'content' => 'Fatura {invoice_number} para {client_name}. Valor: {amount}. Vencimento: {due_date}.'
                    ],
                    'pt-br' => [
                        'title' => 'Nova fatura: {invoice_number}',
                        'content' => 'Fatura {invoice_number} para {client_name}. Valor: {amount}. Vencimento: {due_date}.'
                    ],
                    'ru' => [
                        'title' => 'Новый счет: {invoice_number}',
                        'content' => 'Счет {invoice_number} для {client_name}. Сумма: {amount}. Срок: {due_date}.'
                    ],
                    'tr' => [
                        'title' => 'Yeni Fatura: {invoice_number}',
                        'content' => '{client_name} için fatura {invoice_number}. Tutar: {amount}. Vade: {due_date}.'
                    ],
                    'zh' => [
                        'title' => '新发票: {invoice_number}',
                        'content' => '{client_name} 的发票 {invoice_number}。金额: {amount}。到期: {due_date}。'
                    ],
                ]
            ],

            // Invoice Status Updated templates
            [
                'name' => 'Invoice Status Updated',
                'type' => 'slack',
                'translations' => [
                    'en' => [
                        'title' => 'Invoice Status Updated: {invoice_number}',
                        'content' => 'Invoice {invoice_number} status changed to {status} by {updated_by}.'
                    ],
                    'es' => [
                        'title' => 'Estado de factura actualizado: {invoice_number}',
                        'content' => 'Estado de la factura {invoice_number} cambiado a {status} por {updated_by}.'
                    ],
                    'ar' => [
                        'title' => 'تم تحديث حالة الفاتورة: {invoice_number}',
                        'content' => 'تغيرت حالة الفاتورة {invoice_number} إلى {status} بواسطة {updated_by}.'
                    ],
                    'da' => [
                        'title' => 'Faktura status opdateret: {invoice_number}',
                        'content' => 'Faktura {invoice_number} status ændret til {status} af {updated_by}.'
                    ],
                    'de' => [
                        'title' => 'Rechnungsstatus aktualisiert: {invoice_number}',
                        'content' => 'Rechnung {invoice_number} Status geändert zu {status} von {updated_by}.'
                    ],
                    'fr' => [
                        'title' => 'Statut de facture mis à jour : {invoice_number}',
                        'content' => 'Statut de la facture {invoice_number} changé en {status} par {updated_by}.'
                    ],
                    'he' => [
                        'title' => 'סטטוס חשבונית עודכן: {invoice_number}',
                        'content' => 'סטטוס חשבונית {invoice_number} שונה ל-{status} על ידי {updated_by}.'
                    ],
                    'it' => [
                        'title' => 'Stato fattura aggiornato: {invoice_number}',
                        'content' => 'Stato della fattura {invoice_number} cambiato in {status} da {updated_by}.'
                    ],
                    'ja' => [
                        'title' => '請求書ステータス更新: {invoice_number}',
                        'content' => '請求書 {invoice_number} のステータスが {updated_by} により {status} に変更。'
                    ],
                    'nl' => [
                        'title' => 'Factuur status bijgewerkt: {invoice_number}',
                        'content' => 'Factuur {invoice_number} status gewijzigd naar {status} door {updated_by}.'
                    ],
                    'pl' => [
                        'title' => 'Status faktury zaktualizowany: {invoice_number}',
                        'content' => 'Status faktury {invoice_number} zmieniony na {status} przez {updated_by}.'
                    ],
                    'pt' => [
                        'title' => 'Status da fatura atualizado: {invoice_number}',
                        'content' => 'Status da fatura {invoice_number} alterado para {status} por {updated_by}.'
                    ],
                    'pt-br' => [
                        'title' => 'Status da fatura atualizado: {invoice_number}',
                        'content' => 'Status da fatura {invoice_number} alterado para {status} por {updated_by}.'
                    ],
                    'ru' => [
                        'title' => 'Статус счета обновлен: {invoice_number}',
                        'content' => 'Статус счета {invoice_number} изменен на {status} пользователем {updated_by}.'
                    ],
                    'tr' => [
                        'title' => 'Fatura Durumu Güncellendi: {invoice_number}',
                        'content' => 'Fatura {invoice_number} durumu {updated_by} tarafından {status} olarak değiştirildi.'
                    ],
                    'zh' => [
                        'title' => '发票状态更新: {invoice_number}',
                        'content' => '发票 {invoice_number} 状态由 {updated_by} 更改为 {status}。'
                    ],
                ]
            ],

            [
                'name' => 'Invoice Status Updated',
                'type' => 'telegram',
                'translations' => [
                    'en' => [
                        'title' => 'Invoice Update: {invoice_number}',
                        'content' => 'Invoice {invoice_number} status changed to {status}.'
                    ],
                    'es' => [
                        'title' => 'Actualización de factura: {invoice_number}',
                        'content' => 'Estado de factura {invoice_number} cambiado a {status}.'
                    ],
                    'ar' => [
                        'title' => 'تحديث الفاتورة: {invoice_number}',
                        'content' => 'حالة الفاتورة {invoice_number} تغيرت إلى {status}.'
                    ],
                    'da' => [
                        'title' => 'Faktura opdatering: {invoice_number}',
                        'content' => 'Faktura {invoice_number} status ændret til {status}.'
                    ],
                    'de' => [
                        'title' => 'Rechnungs-Update: {invoice_number}',
                        'content' => 'Rechnung {invoice_number} Status geändert zu {status}.'
                    ],
                    'fr' => [
                        'title' => 'Mise à jour facture : {invoice_number}',
                        'content' => 'Statut facture {invoice_number} changé en {status}.'
                    ],
                    'he' => [
                        'title' => 'עדכון חשבונית: {invoice_number}',
                        'content' => 'סטטוס חשבונית {invoice_number} שונה ל-{status}.'
                    ],
                    'it' => [
                        'title' => 'Aggiornamento fattura: {invoice_number}',
                        'content' => 'Stato fattura {invoice_number} cambiato in {status}.'
                    ],
                    'ja' => [
                        'title' => '請求書更新: {invoice_number}',
                        'content' => '請求書 {invoice_number} のステータスが {status} に変更。'
                    ],
                    'nl' => [
                        'title' => 'Factuur update: {invoice_number}',
                        'content' => 'Factuur {invoice_number} status gewijzigd naar {status}.'
                    ],
                    'pl' => [
                        'title' => 'Aktualizacja faktury: {invoice_number}',
                        'content' => 'Status faktury {invoice_number} zmieniony na {status}.'
                    ],
                    'pt' => [
                        'title' => 'Atualização fatura: {invoice_number}',
                        'content' => 'Status fatura {invoice_number} alterado para {status}.'
                    ],
                    'pt-br' => [
                        'title' => 'Atualização fatura: {invoice_number}',
                        'content' => 'Status fatura {invoice_number} alterado para {status}.'
                    ],
                    'ru' => [
                        'title' => 'Обновление счета: {invoice_number}',
                        'content' => 'Статус счета {invoice_number} изменен на {status}.'
                    ],
                    'tr' => [
                        'title' => 'Fatura Güncellemesi: {invoice_number}',
                        'content' => 'Fatura {invoice_number} durumu {status} olarak değiştirildi.'
                    ],
                    'zh' => [
                        'title' => '发票更新: {invoice_number}',
                        'content' => '发票 {invoice_number} 状态更改为 {status}。'
                    ],
                ]
            ],

            // Expense Approval templates
            [
                'name' => 'Expense Approval',
                'type' => 'slack',
                'translations' => [
                    'en' => [
                        'title' => 'Expense Approval Required: {expense_title}',
                        'content' => 'Expense "{expense_title}" submitted by {submitted_by} requires approval. Amount: {expense_amount}. Project: {project_name}.'
                    ],
                    'es' => [
                        'title' => 'Aprobación de gasto requerida: {expense_title}',
                        'content' => 'Gasto "{expense_title}" enviado por {submitted_by} requiere aprobación. Importe: {expense_amount}. Proyecto: {project_name}.'
                    ],
                    'ar' => [
                        'title' => 'مطلوب موافقة على المصروف: {expense_title}',
                        'content' => 'المصروف "{expense_title}" المقدم من {submitted_by} يتطلب الموافقة. المبلغ: {expense_amount}. المشروع: {project_name}.'
                    ],
                    'da' => [
                        'title' => 'Udgiftsgodkendelse påkrævet: {expense_title}',
                        'content' => 'Udgift "{expense_title}" indsendt af {submitted_by} kræver godkendelse. Beløb: {expense_amount}. Projekt: {project_name}.'
                    ],
                    'de' => [
                        'title' => 'Ausgabengenehmigung erforderlich: {expense_title}',
                        'content' => 'Ausgabe "{expense_title}" eingereicht von {submitted_by} benötigt Genehmigung. Betrag: {expense_amount}. Projekt: {project_name}.'
                    ],
                    'fr' => [
                        'title' => 'Approbation de dépense requise : {expense_title}',
                        'content' => 'Dépense "{expense_title}" soumise par {submitted_by} nécessite une approbation. Montant : {expense_amount}. Projet : {project_name}.'
                    ],
                    'he' => [
                        'title' => 'נדרשת אישור הוצאה: {expense_title}',
                        'content' => 'הוצאה "{expense_title}" שהוגשה על ידי {submitted_by} דורשת אישור. סכום: {expense_amount}. פרויקט: {project_name}.'
                    ],
                    'it' => [
                        'title' => 'Approvazione spesa richiesta: {expense_title}',
                        'content' => 'Spesa "{expense_title}" inviata da {submitted_by} richiede approvazione. Importo: {expense_amount}. Progetto: {project_name}.'
                    ],
                    'ja' => [
                        'title' => '経費承認が必要: {expense_title}',
                        'content' => '{submitted_by} が提出した経費「{expense_title}」の承認が必要です。金額: {expense_amount}。プロジェクト: {project_name}。'
                    ],
                    'nl' => [
                        'title' => 'Uitgaven goedkeuring vereist: {expense_title}',
                        'content' => 'Uitgave "{expense_title}" ingediend door {submitted_by} vereist goedkeuring. Bedrag: {expense_amount}. Project: {project_name}.'
                    ],
                    'pl' => [
                        'title' => 'Wymagana akceptacja wydatku: {expense_title}',
                        'content' => 'Wydatek "{expense_title}" przesłany przez {submitted_by} wymaga akceptacji. Kwota: {expense_amount}. Projekt: {project_name}.'
                    ],
                    'pt' => [
                        'title' => 'Aprovação de despesa necessária: {expense_title}',
                        'content' => 'Despesa "{expense_title}" enviada por {submitted_by} requer aprovação. Valor: {expense_amount}. Projeto: {project_name}.'
                    ],
                    'pt-br' => [
                        'title' => 'Aprovação de despesa necessária: {expense_title}',
                        'content' => 'Despesa "{expense_title}" enviada por {submitted_by} requer aprovação. Valor: {expense_amount}. Projeto: {project_name}.'
                    ],
                    'ru' => [
                        'title' => 'Требуется одобрение расхода: {expense_title}',
                        'content' => 'Расход "{expense_title}" отправленный {submitted_by} требует одобрения. Сумма: {expense_amount}. Проект: {project_name}.'
                    ],
                    'tr' => [
                        'title' => 'Gider Onayı Gerekli: {expense_title}',
                        'content' => '{submitted_by} tarafından gönderilen "{expense_title}" gideri onay gerektiriyor. Tutar: {expense_amount}. Proje: {project_name}.'
                    ],
                    'zh' => [
                        'title' => '需要费用审批: {expense_title}',
                        'content' => '{submitted_by} 提交的费用"{expense_title}"需要审批。金额: {expense_amount}。项目: {project_name}。'
                    ],
                ]
            ],

            [
                'name' => 'Expense Approval',
                'type' => 'telegram',
                'translations' => [
                    'en' => [
                        'title' => 'Expense Approval: {expense_title}',
                        'content' => 'Expense "{expense_title}" by {submitted_by} needs approval. Amount: {expense_amount}.'
                    ],
                    'es' => [
                        'title' => 'Aprobación de gasto: {expense_title}',
                        'content' => 'Gasto "{expense_title}" de {submitted_by} necesita aprobación. Importe: {expense_amount}.'
                    ],
                    'ar' => [
                        'title' => 'موافقة المصروف: {expense_title}',
                        'content' => 'المصروف "{expense_title}" من {submitted_by} يحتاج موافقة. المبلغ: {expense_amount}.'
                    ],
                    'da' => [
                        'title' => 'Udgiftsgodkendelse: {expense_title}',
                        'content' => 'Udgift "{expense_title}" fra {submitted_by} skal godkendes. Beløb: {expense_amount}.'
                    ],
                    'de' => [
                        'title' => 'Ausgabengenehmigung: {expense_title}',
                        'content' => 'Ausgabe "{expense_title}" von {submitted_by} benötigt Genehmigung. Betrag: {expense_amount}.'
                    ],
                    'fr' => [
                        'title' => 'Approbation dépense : {expense_title}',
                        'content' => 'Dépense "{expense_title}" de {submitted_by} nécessite approbation. Montant : {expense_amount}.'
                    ],
                    'he' => [
                        'title' => 'אישור הוצאה: {expense_title}',
                        'content' => 'הוצאה "{expense_title}" של {submitted_by} דורשת אישור. סכום: {expense_amount}.'
                    ],
                    'it' => [
                        'title' => 'Approvazione spesa: {expense_title}',
                        'content' => 'Spesa "{expense_title}" di {submitted_by} richiede approvazione. Importo: {expense_amount}.'
                    ],
                    'ja' => [
                        'title' => '経費承認: {expense_title}',
                        'content' => '{submitted_by} の経費「{expense_title}」の承認が必要。金額: {expense_amount}。'
                    ],
                    'nl' => [
                        'title' => 'Uitgaven goedkeuring: {expense_title}',
                        'content' => 'Uitgave "{expense_title}" van {submitted_by} vereist goedkeuring. Bedrag: {expense_amount}.'
                    ],
                    'pl' => [
                        'title' => 'Akceptacja wydatku: {expense_title}',
                        'content' => 'Wydatek "{expense_title}" od {submitted_by} wymaga akceptacji. Kwota: {expense_amount}.'
                    ],
                    'pt' => [
                        'title' => 'Aprovação despesa: {expense_title}',
                        'content' => 'Despesa "{expense_title}" de {submitted_by} requer aprovação. Valor: {expense_amount}.'
                    ],
                    'pt-br' => [
                        'title' => 'Aprovação despesa: {expense_title}',
                        'content' => 'Despesa "{expense_title}" de {submitted_by} requer aprovação. Valor: {expense_amount}.'
                    ],
                    'ru' => [
                        'title' => 'Одобрение расхода: {expense_title}',
                        'content' => 'Расход "{expense_title}" от {submitted_by} требует одобрения. Сумма: {expense_amount}.'
                    ],
                    'tr' => [
                        'title' => 'Gider Onayı: {expense_title}',
                        'content' => '{submitted_by} tarafından "{expense_title}" gideri onay gerektiriyor. Tutar: {expense_amount}.'
                    ],
                    'zh' => [
                        'title' => '费用审批: {expense_title}',
                        'content' => '{submitted_by} 的费用"{expense_title}"需要审批。金额: {expense_amount}。'
                    ],
                ]
            ],

            // New Budget templates
            [
                'name' => 'New Budget',
                'type' => 'slack',
                'translations' => [
                    'en' => [
                        'title' => 'New Budget Created: {project_name}',
                        'content' => 'A new budget has been created for project "{project_name}". Total Budget: {total_budget}. Period: {period_type}.'
                    ],
                    'es' => [
                        'title' => 'Nuevo presupuesto creado: {project_name}',
                        'content' => 'Se ha creado un nuevo presupuesto para el proyecto "{project_name}". Presupuesto total: {total_budget}. Período: {period_type}.'
                    ],
                    'ar' => [
                        'title' => 'تم إنشاء ميزانية جديدة: {project_name}',
                        'content' => 'تم إنشاء ميزانية جديدة للمشروع "{project_name}". إجمالي الميزانية: {total_budget}. الفترة: {period_type}.'
                    ],
                    'da' => [
                        'title' => 'Nyt budget oprettet: {project_name}',
                        'content' => 'Et nyt budget er blevet oprettet for projekt "{project_name}". Samlet budget: {total_budget}. Periode: {period_type}.'
                    ],
                    'de' => [
                        'title' => 'Neues Budget erstellt: {project_name}',
                        'content' => 'Ein neues Budget wurde für Projekt "{project_name}" erstellt. Gesamtbudget: {total_budget}. Zeitraum: {period_type}.'
                    ],
                    'fr' => [
                        'title' => 'Nouveau budget créé : {project_name}',
                        'content' => 'Un nouveau budget a été créé pour le projet "{project_name}". Budget total : {total_budget}. Période : {period_type}.'
                    ],
                    'he' => [
                        'title' => 'תקציב חדש נוצר: {project_name}',
                        'content' => 'תקציב חדש נוצר עבור פרויקט "{project_name}". תקציב כולל: {total_budget}. תקופה: {period_type}.'
                    ],
                    'it' => [
                        'title' => 'Nuovo budget creato: {project_name}',
                        'content' => 'È stato creato un nuovo budget per il progetto "{project_name}". Budget totale: {total_budget}. Periodo: {period_type}.'
                    ],
                    'ja' => [
                        'title' => '新しい予算が作成されました: {project_name}',
                        'content' => 'プロジェクト「{project_name}」の新しい予算が作成されました。総予算: {total_budget}。期間: {period_type}。'
                    ],
                    'nl' => [
                        'title' => 'Nieuw budget aangemaakt: {project_name}',
                        'content' => 'Een nieuw budget is aangemaakt voor project "{project_name}". Totaal budget: {total_budget}. Periode: {period_type}.'
                    ],
                    'pl' => [
                        'title' => 'Nowy budżet utworzony: {project_name}',
                        'content' => 'Nowy budżet został utworzony dla projektu "{project_name}". Całkowity budżet: {total_budget}. Okres: {period_type}.'
                    ],
                    'pt' => [
                        'title' => 'Novo orçamento criado: {project_name}',
                        'content' => 'Um novo orçamento foi criado para o projeto "{project_name}". Orçamento total: {total_budget}. Período: {period_type}.'
                    ],
                    'pt-br' => [
                        'title' => 'Novo orçamento criado: {project_name}',
                        'content' => 'Um novo orçamento foi criado para o projeto "{project_name}". Orçamento total: {total_budget}. Período: {period_type}.'
                    ],
                    'ru' => [
                        'title' => 'Создан новый бюджет: {project_name}',
                        'content' => 'Новый бюджет создан для проекта "{project_name}". Общий бюджет: {total_budget}. Период: {period_type}.'
                    ],
                    'tr' => [
                        'title' => 'Yeni Bütçe Oluşturuldu: {project_name}',
                        'content' => '"{project_name}" projesi için yeni bir bütçe oluşturuldu. Toplam Bütçe: {total_budget}. Dönem: {period_type}.'
                    ],
                    'zh' => [
                        'title' => '已创建新预算: {project_name}',
                        'content' => '为项目"{project_name}"创建了新预算。总预算: {total_budget}。期间: {period_type}。'
                    ],
                ]
            ],

            [
                'name' => 'New Budget',
                'type' => 'telegram',
                'translations' => [
                    'en' => [
                        'title' => 'New Budget: {project_name}',
                        'content' => 'Budget created for "{project_name}". Total: {total_budget}. Period: {period_type}.'
                    ],
                    'es' => [
                        'title' => 'Nuevo presupuesto: {project_name}',
                        'content' => 'Presupuesto creado para "{project_name}". Total: {total_budget}. Período: {period_type}.'
                    ],
                    'ar' => [
                        'title' => 'ميزانية جديدة: {project_name}',
                        'content' => 'تم إنشاء ميزانية لـ "{project_name}". الإجمالي: {total_budget}. الفترة: {period_type}.'
                    ],
                    'da' => [
                        'title' => 'Nyt budget: {project_name}',
                        'content' => 'Budget oprettet for "{project_name}". Total: {total_budget}. Periode: {period_type}.'
                    ],
                    'de' => [
                        'title' => 'Neues Budget: {project_name}',
                        'content' => 'Budget für "{project_name}" erstellt. Gesamt: {total_budget}. Zeitraum: {period_type}.'
                    ],
                    'fr' => [
                        'title' => 'Nouveau budget : {project_name}',
                        'content' => 'Budget créé pour "{project_name}". Total : {total_budget}. Période : {period_type}.'
                    ],
                    'he' => [
                        'title' => 'תקציב חדש: {project_name}',
                        'content' => 'תקציב נוצר עבור "{project_name}". סה"כ: {total_budget}. תקופה: {period_type}.'
                    ],
                    'it' => [
                        'title' => 'Nuovo budget: {project_name}',
                        'content' => 'Budget creato per "{project_name}". Totale: {total_budget}. Periodo: {period_type}.'
                    ],
                    'ja' => [
                        'title' => '新予算: {project_name}',
                        'content' => '「{project_name}」の予算が作成されました。合計: {total_budget}。期間: {period_type}。'
                    ],
                    'nl' => [
                        'title' => 'Nieuw budget: {project_name}',
                        'content' => 'Budget aangemaakt voor "{project_name}". Totaal: {total_budget}. Periode: {period_type}.'
                    ],
                    'pl' => [
                        'title' => 'Nowy budżet: {project_name}',
                        'content' => 'Budżet utworzony dla "{project_name}". Łącznie: {total_budget}. Okres: {period_type}.'
                    ],
                    'pt' => [
                        'title' => 'Novo orçamento: {project_name}',
                        'content' => 'Orçamento criado para "{project_name}". Total: {total_budget}. Período: {period_type}.'
                    ],
                    'pt-br' => [
                        'title' => 'Novo orçamento: {project_name}',
                        'content' => 'Orçamento criado para "{project_name}". Total: {total_budget}. Período: {period_type}.'
                    ],
                    'ru' => [
                        'title' => 'Новый бюджет: {project_name}',
                        'content' => 'Бюджет создан для "{project_name}". Итого: {total_budget}. Период: {period_type}.'
                    ],
                    'tr' => [
                        'title' => 'Yeni Bütçe: {project_name}',
                        'content' => '"{project_name}" için bütçe oluşturuldu. Toplam: {total_budget}. Dönem: {period_type}.'
                    ],
                    'zh' => [
                        'title' => '新预算: {project_name}',
                        'content' => '为"{project_name}"创建预算。总计: {total_budget}。期间: {period_type}。'
                    ],
                ]
            ],
        ];

        $companyUsers = User::where('type', 'company')->get();

        foreach ($templates as $templateData) {
            // FIXED: Check both name AND type to prevent duplicates
            $template = NotificationTemplate::updateOrCreate(
                [
                    'name' => $templateData['name'],
                    'type' => $templateData['type']
                ],
                [
                    'name' => $templateData['name'],
                    'type' => $templateData['type']
                ]
            );

            // Create content for each company
            foreach ($companyUsers as $company) {
                foreach ($langCodes as $langCode) {
                    $existingContent = NotificationTemplateLang::where('parent_id', $template->id)
                        ->where('lang', $langCode)
                        ->where('created_by', $company->id)
                        ->first();

                    if ($existingContent) {
                        continue;
                    }

                    $translation = $templateData['translations'][$langCode] ?? $templateData['translations']['en'];

                    NotificationTemplateLang::updateOrCreate([
                        'parent_id' => $template->id,
                        'lang' => $langCode,
                        'created_by' => $company->id
                    ], [
                        'title' => $translation['title'],
                        'content' => $translation['content']
                    ]);
                }
            }

            // Create content for global template
            foreach ($langCodes as $langCode) {
                $existingContent = NotificationTemplateLang::where('parent_id', $template->id)
                    ->where('lang', $langCode)
                    ->where('created_by', 1)
                    ->first();

                if ($existingContent) {
                    continue;
                }

                $translation = $templateData['translations'][$langCode] ?? $templateData['translations']['en'];

                NotificationTemplateLang::create([
                    'parent_id' => $template->id,
                    'lang' => $langCode,
                    'title' => $translation['title'],
                    'content' => $translation['content'],
                    'created_by' => 1
                ]);
            }
        }
    }
}