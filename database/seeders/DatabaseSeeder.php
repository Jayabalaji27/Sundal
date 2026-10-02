<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {


        $seeders = [
            PermissionSeeder::class,
            RoleSeeder::class,
            PlanSeeder::class,
            UserSeeder::class,
            CurrencySeeder::class,
            LandingPageSettingSeeder::class,
            LandingPageCustomPageSeeder::class,
            EmailTemplateSeeder::class,
            NotificationTemplateSeeder::class,
        ];

        // Demo data seeders
        if (config('app.is_demo')) {

            $seeders[] = WorkspaceSeeder::class;

            if (config('app.is_saas')) {
                $seeders[] = CouponSeeder::class;
                $seeders[] = PlanOrderSeeder::class;
                $seeders[] = PlanRequestSeeder::class;
                $seeders[] = ReferralSeeder::class;
            }

            $seeders = array_merge($seeders, [
                NewsletterSeeder::class,
                ContactSeeder::class,
                TaskStageSeeder::class,
                BugStatusSeeder::class,
                ProjectSeeder::class,
                BudgetCategorySeeder::class,
                BudgetSeeder::class,
                TaskSeeder::class,
                BugSeeder::class,
                SubtaskSeeder::class,
                ActivitySeeder::class,
                TimesheetSeeder::class,
                ExpenseSeeder::class,
                InvoiceSeeder::class,
                LoginHistorySeeder::class,
                WebhookSeeder::class,
                ZoomMeetingSeeder::class,
                EmailTemplateSeeder::class,
                NotificationTemplateSeeder::class,
                ContractSeeder::class,
                GoogleMeetingSeeder::class,
                ToDoSeeder::class,
                NoteSeeder::class,
            ]);
        }

        $this->call($seeders);
    }
}
