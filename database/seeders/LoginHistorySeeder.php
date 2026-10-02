<?php

namespace Database\Seeders;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class LoginHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing users based on SaaS mode
        if (isSaasMode()) {
            $users = User::whereIn('type', ['superadmin', 'company'])->get();
            $staffUsers = User::whereIn('type', ['manager', 'member', 'client'])->get();
        } else {
            $users = User::whereIn('type', ['company'])->get();
            $staffUsers = User::whereIn('type', ['manager', 'member', 'client'])->get();
        }

        if ($users->isEmpty() && $staffUsers->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        $allUsers = $users->merge($staffUsers);

        // Sample IP addresses
        $ipAddresses = [
            '192.168.1.100',
            '10.0.0.50',
            '172.16.0.25',
            '203.0.113.45',
            '198.51.100.78',
            '127.0.0.1',
            '192.168.0.15',
            '10.1.1.200'
        ];

        // Sample browser and device data
        $browserData = [
            [
                'browser_name' => 'Chrome',
                'os_name' => 'Windows',
                'device_type' => 'desktop',
                'browser_language' => 'en'
            ],
            [
                'browser_name' => 'Firefox',
                'os_name' => 'Linux',
                'device_type' => 'desktop',
                'browser_language' => 'en'
            ],
            [
                'browser_name' => 'Safari',
                'os_name' => 'macOS',
                'device_type' => 'desktop',
                'browser_language' => 'en'
            ],
            [
                'browser_name' => 'Chrome',
                'os_name' => 'Android',
                'device_type' => 'mobile',
                'browser_language' => 'en'
            ],
            [
                'browser_name' => 'Safari',
                'os_name' => 'iOS',
                'device_type' => 'mobile',
                'browser_language' => 'en'
            ]
        ];

        // Sample location data
        $locationData = [
            [
                'country' => 'India',
                'countryCode' => 'IN',
                'region' => 'GJ',
                'regionName' => 'Gujarat',
                'city' => 'Surat',
                'zip' => '395007',
                'lat' => 21.1981,
                'lon' => 72.8298,
                'timezone' => 'Asia/Kolkata',
                'isp' => 'Reliance Jio Infocomm Limited',
                'org' => 'Reliance Jio Infocomm Limited',
                'as' => 'AS55836 Reliance Jio Infocomm Limited'
            ],
            [
                'country' => 'United States',
                'countryCode' => 'US',
                'region' => 'CA',
                'regionName' => 'California',
                'city' => 'San Francisco',
                'zip' => '94102',
                'lat' => 37.7749,
                'lon' => -122.4194,
                'timezone' => 'America/Los_Angeles',
                'isp' => 'Comcast Cable',
                'org' => 'Comcast Cable Communications',
                'as' => 'AS7922 Comcast Cable Communications'
            ],
            [
                'country' => 'United Kingdom',
                'countryCode' => 'GB',
                'region' => 'ENG',
                'regionName' => 'England',
                'city' => 'London',
                'zip' => 'SW1A',
                'lat' => 51.5074,
                'lon' => -0.1278,
                'timezone' => 'Europe/London',
                'isp' => 'British Telecom',
                'org' => 'BT Group',
                'as' => 'AS2856 British Telecommunications PLC'
            ],
            [
                'country' => 'Canada',
                'countryCode' => 'CA',
                'region' => 'ON',
                'regionName' => 'Ontario',
                'city' => 'Toronto',
                'zip' => 'M5H',
                'lat' => 43.6532,
                'lon' => -79.3832,
                'timezone' => 'America/Toronto',
                'isp' => 'Rogers Communications',
                'org' => 'Rogers Cable Communications',
                'as' => 'AS812 Rogers Communications Canada Inc.'
            ]
        ];

        $recordsCreated = 0;
        $targetRecords = 45;

        if (isSaasMode()) {
            // SaaS Mode: Create records for superadmin and company users
            $superadminUsers = User::where('type', 'superadmin')->get();
            $companyUsers = User::where('type', 'company')->get();
            
            // Create 10 superadmin login records
            if ($superadminUsers->isNotEmpty()) {
                for ($i = 0; $i < 10 && $recordsCreated < $targetRecords; $i++) {
                    $user = $superadminUsers->random();
                    $this->createLoginRecord($user, $browserData, $locationData, $ipAddresses, $user->id);
                    $recordsCreated++;
                }
            }

            // Create 10 company login records
            if ($companyUsers->isNotEmpty()) {
                for ($i = 0; $i < 10 && $recordsCreated < $targetRecords; $i++) {
                    $user = $companyUsers->random();
                    $superadmin = $superadminUsers->first();
                    $createdBy = $superadmin ? $superadmin->id : $user->id;
                    $this->createLoginRecord($user, $browserData, $locationData, $ipAddresses, $createdBy);
                    $recordsCreated++;
                }
            }
        } else {
            // Non-SaaS Mode: Create records for company users only
            $companyUsers = User::where('type', 'company')->get();
            
            // Create 10 company login records
            if ($companyUsers->isNotEmpty()) {
                for ($i = 0; $i < 10 && $recordsCreated < $targetRecords; $i++) {
                    $user = $companyUsers->random();
                    $this->createLoginRecord($user, $browserData, $locationData, $ipAddresses, $user->id);
                    $recordsCreated++;
                }
            }
        }

        // Create staff user login records (manager, member, client)
        $managerUsers = User::where('type', 'manager')->get();
        $memberUsers = User::where('type', 'member')->get();
        $clientUsers = User::where('type', 'client')->get();

        // Create manager records
        if ($managerUsers->isNotEmpty()) {
            for ($i = 0; $i < 5 && $recordsCreated < $targetRecords; $i++) {
                $user = $managerUsers->random();
                $createdBy = $this->getCreatedBy($user);
                $this->createLoginRecord($user, $browserData, $locationData, $ipAddresses, $createdBy);
                $recordsCreated++;
            }
        }

        // Create member records
        if ($memberUsers->isNotEmpty()) {
            for ($i = 0; $i < 8 && $recordsCreated < $targetRecords; $i++) {
                $user = $memberUsers->random();
                $createdBy = $this->getCreatedBy($user);
                $this->createLoginRecord($user, $browserData, $locationData, $ipAddresses, $createdBy);
                $recordsCreated++;
            }
        }

        // Create client records
        if ($clientUsers->isNotEmpty()) {
            for ($i = 0; $i < 7 && $recordsCreated < $targetRecords; $i++) {
                $user = $clientUsers->random();
                $createdBy = $this->getCreatedBy($user);
                $this->createLoginRecord($user, $browserData, $locationData, $ipAddresses, $createdBy);
                $recordsCreated++;
            }
        }

        $mode = isSaasMode() ? 'SaaS' : 'Non-SaaS';
        $this->command->info("{$recordsCreated} login history records created successfully in {$mode} mode.");
        $this->command->info('Distribution: Company, Manager, Member, and Client records.');
    }

    private function createLoginRecord($user, $browserData, $locationData, $ipAddresses, $createdBy)
    {
        $browser = $browserData[array_rand($browserData)];
        $location = $locationData[array_rand($locationData)];
        $ip = $ipAddresses[array_rand($ipAddresses)];

        // Combine all details
        $details = array_merge($browser, $location, [
            'status' => 'success',
            'query' => $ip,
            'referrer_host' => fake()->randomElement(['localhost', 'example.com', 'app.domain.com', null]),
            'referrer_path' => fake()->randomElement(['/login', '/dashboard', '/home', null])
        ]);

        // Get proper role name
        $roleType = $user->getRoleNames()->first() ?? $user->type;

        LoginHistory::create([
            'user_id' => $user->id,
            'ip' => $ip,
            'date' => Carbon::now()->subDays(rand(0, 30))->toDateString(),
            'details' => $details,
            'type' => $roleType,
            'created_by' => $createdBy,
            'created_at' => Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59)),
            'updated_at' => Carbon::now()
        ]);
    }

    private function getCreatedBy($user)
    {
        if (in_array($user->type, ['superadmin', 'company'])) {
            return $user->id;
        } else {
            // For staff users, use their created_by or find a company user
            if ($user->created_by) {
                return $user->created_by;
            }
            
            $companyUser = User::where('type', 'company')->first();
            return $companyUser ? $companyUser->id : $user->id;
        }
    }
}
