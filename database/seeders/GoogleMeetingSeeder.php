<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GoogleMeeting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GoogleMeetingSeeder extends Seeder
{
    public function run(): void
    {
        // Use different member IDs based on SaaS mode
        $isSaas = config('app.is_saas', false);
        $managerMemberId = $isSaas ? 3 : 2;
        $memberId = $isSaas ? 6 : 5;
        $clientId = $isSaas ? 21 : 20;

        $meetings = [
            [
                'title' => 'Weekly Team Standup',
                'description' => 'Weekly team standup meeting to discuss progress and blockers',
                'start_time' => Carbon::now()->addDays(1)->setTime(9, 0),
                'duration' => 30,
                'project_id' => 1,
                'join_url' => 'https://meet.google.com/abc-defg-hij',
                'start_url' => 'https://meet.google.com/abc-defg-hij',
                'members' => [$managerMemberId, $memberId] // manager and member
            ],
            [
                'title' => 'Project Planning Session',
                'description' => 'Planning session for upcoming project milestones',
                'start_time' => Carbon::now()->addDays(2)->setTime(14, 0),
                'duration' => 60,
                'project_id' => 2,
                'join_url' => 'https://meet.google.com/klm-nopq-rst',
                'start_url' => 'https://meet.google.com/klm-nopq-rst',
                'members' => [$managerMemberId, $memberId, $clientId] // manager, member, and client
            ],
            [
                'title' => 'Client Review Meeting',
                'description' => 'Review meeting with client for project deliverables',
                'start_time' => Carbon::now()->addDays(3)->setTime(11, 0),
                'duration' => 45,
                'project_id' => 1,
                'join_url' => 'https://meet.google.com/uvw-xyza-bcd',
                'start_url' => 'https://meet.google.com/uvw-xyza-bcd',
                'members' => [$clientId] // only client
            ],
            [
                'title' => 'Sprint Retrospective',
                'description' => 'Sprint retrospective to discuss what went well and improvements',
                'start_time' => Carbon::now()->addDays(4)->setTime(16, 0),
                'duration' => 60,
                'project_id' => 2,
                'join_url' => 'https://meet.google.com/efg-hijk-lmn',
                'start_url' => 'https://meet.google.com/efg-hijk-lmn',
                'members' => [$managerMemberId, $memberId] // manager and member
            ],
            [
                'title' => 'Technical Discussion',
                'description' => 'Technical discussion about architecture and implementation',
                'start_time' => Carbon::now()->addDays(5)->setTime(10, 30),
                'duration' => 90,
                'project_id' => 3,
                'join_url' => 'https://meet.google.com/opq-rstu-vwx',
                'start_url' => 'https://meet.google.com/opq-rstu-vwx',
                'members' => [$memberId] // only member
            ],
            [
                'title' => 'Monthly Business Review',
                'description' => 'Monthly business review with stakeholders',
                'start_time' => Carbon::now()->addDays(7)->setTime(15, 0),
                'duration' => 120,
                'project_id' => 2,
                'join_url' => 'https://meet.google.com/yza-bcde-fgh',
                'start_url' => 'https://meet.google.com/yza-bcde-fgh',
                'members' => [$managerMemberId, $clientId] // manager and client
            ],
            [
                'title' => 'Training Session',
                'description' => 'Training session on new tools and technologies',
                'start_time' => Carbon::now()->addDays(8)->setTime(13, 0),
                'duration' => 75,
                'project_id' => 1,
                'join_url' => 'https://meet.google.com/ijk-lmno-pqr',
                'start_url' => 'https://meet.google.com/ijk-lmno-pqr',
                'members' => [$managerMemberId, $memberId] // manager and member
            ],
            [
                'title' => 'Product Demo',
                'description' => 'Product demonstration for client feedback',
                'start_time' => Carbon::now()->addDays(10)->setTime(12, 0),
                'duration' => 45,
                'project_id' => 2,
                'join_url' => 'https://meet.google.com/stu-vwxy-zab',
                'start_url' => 'https://meet.google.com/stu-vwxy-zab',
                'members' => [$managerMemberId, $memberId, $clientId] // all three
            ],
            [
                'title' => 'One-on-One Meeting',
                'description' => 'One-on-one meeting for performance discussion',
                'start_time' => Carbon::now()->addDays(12)->setTime(17, 0),
                'duration' => 30,
                'project_id' => 4,
                'join_url' => 'https://meet.google.com/cde-fghi-jkl',
                'start_url' => 'https://meet.google.com/cde-fghi-jkl',
                'members' => [$memberId] // only member
            ],
            [
                'title' => 'Quarterly Planning',
                'description' => 'Quarterly planning meeting for next quarter goals',
                'start_time' => Carbon::now()->addDays(14)->setTime(9, 30),
                'duration' => 180,
                'project_id' => 3,
                'join_url' => 'https://meet.google.com/mno-pqrs-tuv',
                'start_url' => 'https://meet.google.com/mno-pqrs-tuv',
                'members' => [$managerMemberId, $clientId] // manager and client
            ]
        ];

        foreach ($meetings as $meetingData) {
            $members = $meetingData['members'];
            unset($meetingData['members']);

            $endTime = Carbon::parse($meetingData['start_time'])->addMinutes($meetingData['duration']);

            $meeting = GoogleMeeting::updateOrCreate(
                [
                    'user_id' => 2,
                    'title' => $meetingData['title'],
                ],
                [
                    'description' => $meetingData['description'],
                    'workspace_id' => 1,
                    'project_id' => $meetingData['project_id'],
                    'start_time' => $meetingData['start_time'],
                    'end_time' => $endTime,
                    'duration' => $meetingData['duration'],
                    'join_url' => $meetingData['join_url'],
                    'start_url' => $meetingData['start_url'],
                    'status' => 'scheduled',
                    'type' => 'scheduled',
                ]
            );

            // Add members to the meeting
            foreach ($members as $memberId) {
                DB::table('google_meeting_members')->updateOrInsert(
                    [
                        'google_meeting_id' => $meeting->id,
                        'user_id' => $memberId
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
            }
        }
    }
}