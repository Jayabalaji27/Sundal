<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\NotificationTemplate;
use App\Models\NotificationTemplateLang;

class SyncNotificationTemplates extends Command
{
    protected $signature = 'notifications:sync-templates';
    protected $description = 'Sync notification templates for all existing companies';

    public function handle()
    {
        $companies = User::where('type', 'company')->get();
        $templates = NotificationTemplate::all();
        $languages = json_decode(file_get_contents(resource_path('lang/language.json')), true);
        $langCodes = collect($languages)->pluck('code')->toArray();

        foreach ($companies as $company) {
            foreach ($templates as $template) {
                foreach ($langCodes as $lang) {
                    $existingLang = NotificationTemplateLang::where('parent_id', $template->id)
                        ->where('lang', $lang)
                        ->where('created_by', $company->id)
                        ->first();

                    if (!$existingLang) {
                        $superAdminLang = NotificationTemplateLang::where('parent_id', $template->id)
                            ->where('lang', $lang)
                            ->where('created_by', 1)
                            ->first();

                        if ($superAdminLang) {
                            NotificationTemplateLang::create([
                                'parent_id' => $template->id,
                                'lang' => $lang,
                                'title' => $superAdminLang->title,
                                'content' => $superAdminLang->content,
                                'created_by' => $company->id
                            ]);
                        }
                    }
                }
            }
        }

        $this->info('Notification templates synced for all companies.');
    }
}