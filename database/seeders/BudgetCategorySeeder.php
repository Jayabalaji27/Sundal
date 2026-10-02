<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BudgetCategorySeeder extends Seeder
{
    public function run()
    {
        // Default budget category templates
        $defaultCategories = [
            [
                'name' => 'Development',
                'color' => '#3B82F6',
                'description' => 'Software development costs including coding, testing, and deployment',
                'sort_order' => 1
            ],
            [
                'name' => 'Design',
                'color' => '#8B5CF6',
                'description' => 'UI/UX design, graphics, and creative assets',
                'sort_order' => 2
            ],
            [
                'name' => 'Marketing',
                'color' => '#EF4444',
                'description' => 'Marketing campaigns, advertising, and promotional activities',
                'sort_order' => 3
            ],
            [
                'name' => 'Travel',
                'color' => '#10B981',
                'description' => 'Business travel, accommodation, and transportation',
                'sort_order' => 4
            ],
            [
                'name' => 'Software',
                'color' => '#F59E0B',
                'description' => 'Software licenses, subscriptions, and tools',
                'sort_order' => 5
            ],
            [
                'name' => 'Hardware',
                'color' => '#6B7280',
                'description' => 'Equipment, devices, and hardware purchases',
                'sort_order' => 6
            ],
            [
                'name' => 'Miscellaneous',
                'color' => '#84CC16',
                'description' => 'Other project-related expenses',
                'sort_order' => 7
            ]
        ];

        // Store in config or cache for use when creating budgets
        config(['budget.default_categories' => $defaultCategories]);
    }

    public static function getDefaultCategories(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Development',
                'color' => '#3B82F6',
                'description' => 'Software development costs including coding, testing, and deployment',
                'sort_order' => 1
            ],
            [
                'id' => 2,
                'name' => 'Design',
                'color' => '#8B5CF6',
                'description' => 'UI/UX design, graphics, and creative assets',
                'sort_order' => 2
            ],
            [
                'id' => 3,
                'name' => 'Marketing',
                'color' => '#EF4444',
                'description' => 'Marketing campaigns, advertising, and promotional activities',
                'sort_order' => 3
            ],
            [
                'id' => 4,
                'name' => 'Travel',
                'color' => '#10B981',
                'description' => 'Business travel, accommodation, and transportation',
                'sort_order' => 4
            ],
            [
                'id' => 5,
                'name' => 'Software',
                'color' => '#F59E0B',
                'description' => 'Software licenses, subscriptions, and tools',
                'sort_order' => 5
            ],
            [
                'id' => 6,
                'name' => 'Hardware',
                'color' => '#6B7280',
                'description' => 'Equipment, devices, and hardware purchases',
                'sort_order' => 6
            ],
            [
                'id' => 7,
                'name' => 'Miscellaneous',
                'color' => '#84CC16',
                'description' => 'Other project-related expenses',
                'sort_order' => 7
            ]
        ];
    }
}