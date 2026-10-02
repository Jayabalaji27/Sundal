<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Newsletter;

class NewsletterSeeder extends Seeder
{
    public function run(): void
    {
        $newsletters = [];
        
        $companies = ['techcorp', 'innovatetech', 'startup', 'digitalsolutions', 'agencyplus', 'enterprise', 'consulting', 'creativestudio', 'retailstore', 'nonprofit', 'designagency', 'saascompany', 'fastcompany', 'globaltech', 'smartsolutions', 'webagency', 'datatech', 'cloudcompany', 'aitech', 'fintech'];
        $domains = ['com', 'io', 'co', 'net', 'org'];
        $roles = ['ceo', 'cto', 'manager', 'director', 'lead', 'admin', 'team', 'info', 'contact', 'hello', 'support', 'sales', 'marketing', 'growth', 'founder'];
        
        for ($i = 0; $i < 60; $i++) {
            $company = $companies[array_rand($companies)];
            $domain = $domains[array_rand($domains)];
            $role = $roles[array_rand($roles)];
            $email = $role . '@' . $company . '.' . $domain;
            
            $status = ($i % 8 == 0) ? 'unsubscribed' : 'subscribed';
            $source = ($i % 3 == 0) ? 'admin' : 'landing_page';
            $daysAgo = rand(1, 60);
            
            $newsletter = [
                'email' => $email,
                'status' => $status,
                'source' => $source,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo)
            ];
            
            if ($status === 'subscribed') {
                $newsletter['subscribed_at'] = now()->subDays($daysAgo);
            } else {
                $newsletter['subscribed_at'] = now()->subDays($daysAgo + 10);
                $newsletter['unsubscribed_at'] = now()->subDays(rand(1, $daysAgo));
            }
            
            $newsletters[] = $newsletter;
        }

        foreach ($newsletters as $newsletter) {
            Newsletter::updateOrCreate(['email' => $newsletter['email']], $newsletter);
        }
    }
}