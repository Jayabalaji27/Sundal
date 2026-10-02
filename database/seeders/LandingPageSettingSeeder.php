<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LandingPageSetting;

class LandingPageSettingSeeder extends Seeder
{
    public function run(): void
    {
        // This will create the default settings if none exist
        LandingPageSetting::getSettings();
    }
}