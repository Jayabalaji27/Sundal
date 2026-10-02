<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Contact;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $contacts = [];
        
        $names = ['John Smith', 'Emma Johnson', 'David Wilson', 'Lisa Brown', 'Robert Davis', 'Jennifer Garcia', 'William Martinez', 'Ashley Rodriguez', 'Christopher Lee', 'Amanda White', 'Matthew Taylor', 'Jessica Anderson', 'Daniel Thomas', 'Michelle Jackson', 'Andrew Harris', 'Stephanie Martin', 'Joshua Thompson', 'Nicole Clark', 'Ryan Lewis', 'Samantha Walker', 'Kevin Hall', 'Rachel Allen', 'Brandon Young', 'Lauren King', 'Justin Wright', 'Megan Scott', 'Tyler Green', 'Kimberly Adams', 'Aaron Baker', 'Crystal Nelson', 'Jonathan Hill', 'Vanessa Carter', 'Nathan Mitchell', 'Tiffany Perez', 'Zachary Roberts', 'Brittany Turner', 'Jeremy Phillips', 'Danielle Campbell', 'Sean Parker', 'Courtney Evans', 'Marcus Edwards', 'Jasmine Collins', 'Trevor Stewart', 'Alexis Sanchez', 'Corey Morris', 'Melanie Rogers', 'Dustin Reed', 'Sabrina Cook', 'Lucas Bailey', 'Erica Cooper'];
        $companies = ['techcorp', 'innovatetech', 'startup', 'digitalsolutions', 'agencyplus', 'enterprise', 'consulting', 'creativestudio', 'retailstore', 'nonprofit', 'designagency', 'saascompany', 'fastcompany', 'globaltech', 'smartsolutions', 'webagency', 'datatech', 'cloudcompany', 'aitech', 'fintech'];
        $subjects = ['Demo request', 'Pricing inquiry', 'Feature request', 'Integration help', 'Training session', 'Partnership inquiry', 'Success story', 'Upgrade request', 'Support appreciation', 'Referral program', 'Custom solution', 'Migration assistance', 'Performance feedback', 'API documentation', 'Billing question'];
        $messages = ['We are interested in your platform and would like to schedule a demo.', 'Your solution looks perfect for our team. Can you provide pricing details?', 'We love your platform! Could you add this feature to make it even better?', 'We need help integrating your platform with our existing systems.', 'Our team would benefit from a training session on advanced features.', 'We would like to explore partnership opportunities with your company.', 'Just wanted to share how much your platform has helped our business grow.', 'We are ready to upgrade our plan. What options do you recommend?', 'Your support team is amazing! Thank you for the excellent service.', 'Do you have a referral program? We have companies interested in your platform.', 'We need a custom solution for our specific workflow requirements.', 'We are migrating from another platform and need assistance with the transition.', 'The performance improvements in the latest update are impressive!', 'Could you provide more detailed API documentation for our developers?', 'We have a question about our recent billing statement.'];
        $statuses = ['new', 'read', 'replied', 'closed'];
        
        for ($i = 0; $i < 50; $i++) {
            $name = $names[array_rand($names)];
            $company = $companies[array_rand($companies)];
            $subject = $subjects[array_rand($subjects)];
            $message = $messages[array_rand($messages)];
            $status = $statuses[array_rand($statuses)];
            $daysAgo = rand(1, 30);
            
            $contact = [
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)) . '@' . $company . '.com',
                'subject' => $subject,
                'message' => $message,
                'status' => $status,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo)
            ];
            
            if ($status !== 'new') {
                $contact['read_at'] = now()->subDays($daysAgo - 1);
                $contact['admin_notes'] = 'Customer inquiry processed.';
            }
            
            if ($status === 'replied' || $status === 'closed') {
                $contact['replied_at'] = now()->subDays($daysAgo - 2);
            }
            
            $contacts[] = $contact;
        }

        foreach ($contacts as $contact) {
            Contact::updateOrCreate(['email' => $contact['email'], 'subject' => $contact['subject']], $contact);
        }
    }
}