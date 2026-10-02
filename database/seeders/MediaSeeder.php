<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\WorkspaceMember;

class MediaSeeder extends Seeder
{
    public function run(): void
    {
        // UPDATE 'media'
        // SET column_name = 'new_value'
        // WHERE user_id = 2
        // AND file_name NOT IN ('q-taskly-saas-pic.png', 'r-taskly-saas-pic.png', 's-taskly-saas-pic.png', 'ab-taskly-saas-pic.png', 'ag-taskly-saas-pic.png', 'w-taskly-saas-pic.png');

        $media = Media::where('user_id', '2')->get();
        $companyUsers = User::whereNot('type',  'company')->get();
        $companyUsers = WorkspaceMember::whereNot('role',  'owner')->whereNotIn('user_id', [1, 2])->get();

        foreach ($companyUsers as $companyUser) {
            foreach ($media as $mediaItem) {
                $duplicatedMedia = $mediaItem->replicate();
                $duplicatedMedia->user_id = $companyUser->user_id;
                $duplicatedMedia->uuid = \Illuminate\Support\Str::uuid();
                $duplicatedMedia->save();
            }
        }

        $this->command->info('Media duplicated for ' . $companyUsers->count() . ' company users.');
    }
}
