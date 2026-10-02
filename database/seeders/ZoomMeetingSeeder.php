<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ZoomMeeting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ZoomMeetingSeeder extends Seeder
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
                'timezone' => 'Asia/Calcutta',
                'password' => '1234',
                'project_id' => 1,
                'join_url' => 'https://us05web.zoom.us/j/81234567890?pwd=abc123def456',
                'start_url' => 'https://us05web.zoom.us/s/81234567890?zak=xyz789uvw012',
                'members' => [$managerMemberId, $memberId] // manager and member
            ],
            [
                'title' => 'Project Planning Session',
                'description' => 'Planning session for upcoming project milestones',
                'start_time' => Carbon::now()->addDays(2)->setTime(14, 0),
                'duration' => 60,
                'timezone' => 'Asia/Calcutta',
                'password' => '5678',
                'project_id' => 2,
                'join_url' => 'https://us05web.zoom.us/j/82345678901?pwd=def456ghi789',
                'start_url' => 'https://us05web.zoom.us/s/82345678901?zak=uvw012rst345',
                'members' => [$managerMemberId, $memberId, $clientId] // manager, member, and client
            ],
            [
                'title' => 'Client Review Meeting',
                'description' => 'Review meeting with client for project deliverables',
                'start_time' => Carbon::now()->addDays(3)->setTime(11, 0),
                'duration' => 45,
                'timezone' => 'Asia/Calcutta',
                'password' => '9999',
                'project_id' => 1,
                'join_url' => 'https://us05web.zoom.us/j/83456789012?pwd=ghi789jkl012',
                'start_url' => 'https://us05web.zoom.us/s/83456789012?zak=rst345mno678',
                'members' => [$clientId] // only client
            ],
            [
                'title' => 'Sprint Retrospective',
                'description' => 'Sprint retrospective to discuss what went well and improvements',
                'start_time' => Carbon::now()->addDays(4)->setTime(16, 0),
                'duration' => 60,
                'timezone' => 'Asia/Calcutta',
                'password' => '2468',
                'project_id' => 2,
                'join_url' => 'https://us05web.zoom.us/j/84567890123?pwd=jkl012mno345',
                'start_url' => 'https://us05web.zoom.us/s/84567890123?zak=mno678pqr901',
                'members' => [$managerMemberId, $memberId] // manager and member
            ],
            [
                'title' => 'Technical Discussion',
                'description' => 'Technical discussion about architecture and implementation',
                'start_time' => Carbon::now()->addDays(5)->setTime(10, 30),
                'duration' => 90,
                'timezone' => 'Asia/Calcutta',
                'password' => '1357',
                'project_id' => 3,
                'join_url' => 'https://us05web.zoom.us/j/85678901234?pwd=mno345pqr678',
                'start_url' => 'https://us05web.zoom.us/s/85678901234?zak=pqr901stu234',
                'members' => [$memberId] // only member
            ],
            [
                'title' => 'Monthly Business Review',
                'description' => 'Monthly business review with stakeholders',
                'start_time' => Carbon::now()->addDays(7)->setTime(15, 0),
                'duration' => 120,
                'timezone' => 'Asia/Calcutta',
                'password' => '7890',
                'project_id' => 2,
                'join_url' => 'https://us05web.zoom.us/j/86789012345?pwd=pqr678stu901',
                'start_url' => 'https://us05web.zoom.us/s/86789012345?zak=stu234vwx567',
                'members' => [$managerMemberId, $clientId] // manager and client
            ],
            [
                'title' => 'Training Session',
                'description' => 'Training session on new tools and technologies',
                'start_time' => Carbon::now()->addDays(8)->setTime(13, 0),
                'duration' => 75,
                'timezone' => 'Asia/Calcutta',
                'password' => '4321',
                'project_id' => 1,
                'join_url' => 'https://us05web.zoom.us/j/87890123456?pwd=stu901vwx234',
                'start_url' => 'https://us05web.zoom.us/s/87890123456?zak=vwx567yza890',
                'members' => [$managerMemberId, $memberId] // manager and member
            ],
            [
                'title' => 'Product Demo',
                'description' => 'Product demonstration for client feedback',
                'start_time' => Carbon::now()->addDays(10)->setTime(12, 0),
                'duration' => 45,
                'timezone' => 'Asia/Calcutta',
                'password' => '6789',
                'project_id' => 2,
                'join_url' => 'https://us05web.zoom.us/j/88901234567?pwd=vwx234yza567',
                'start_url' => 'https://us05web.zoom.us/s/88901234567?zak=yza890bcd123',
                'members' => [$managerMemberId, $memberId, $clientId] // all three
            ],
            [
                'title' => 'One-on-One Meeting',
                'description' => 'One-on-one meeting for performance discussion',
                'start_time' => Carbon::now()->addDays(12)->setTime(17, 0),
                'duration' => 30,
                'timezone' => 'Asia/Calcutta',
                'password' => '1111',
                'project_id' => 4,
                'join_url' => 'https://us05web.zoom.us/j/89012345678?pwd=yza567bcd890',
                'start_url' => 'https://us05web.zoom.us/s/89012345678?zak=bcd123efg456',
                'members' => [$memberId] // only member
            ],
            [
                'title' => 'Quarterly Planning',
                'description' => 'Quarterly planning meeting for next quarter goals',
                'start_time' => Carbon::now()->addDays(14)->setTime(9, 30),
                'duration' => 180,
                'timezone' => 'Asia/Calcutta',
                'password' => '0000',
                'project_id' => 3,
                'join_url' => 'https://us05web.zoom.us/j/90123456789?pwd=bcd890efg123',
                'start_url' => 'https://us05web.zoom.us/s/90123456789?zak=efg456hij789',
                'members' => [$managerMemberId, $clientId] // manager and client
            ]
        ];

        foreach ([1, 2] as $workspaceId) {
            foreach ($meetings as $meetingData) {
                $members = $meetingData['members'];
                unset($meetingData['members']);

                $endTime = Carbon::parse($meetingData['start_time'])->addMinutes($meetingData['duration']);

                $meeting = ZoomMeeting::updateOrCreate(
                    [
                        'user_id' => 2,
                        'title' => $meetingData['title'],
                    ],
                    [
                        'description' => $meetingData['description'],
                        'workspace_id' => $workspaceId,
                        'zoom_meeting_id' => 'zoom_' . time() . rand(1000, 9999),
                        'project_id' => $meetingData['project_id'],
                        'start_time' => $meetingData['start_time'],
                        'end_time' => $endTime,
                        'duration' => $meetingData['duration'],
                        'timezone' => $meetingData['timezone'],
                        'start_url' => $meetingData['start_url'],
                        'password' => $meetingData['password'],
                        'join_url' => $meetingData['join_url'],
                        'status' => 'scheduled',
                    ]
                );

                // Add members to the meeting
                foreach ($members as $memberId) {
                    DB::table('zoom_meeting_members')->updateOrInsert(
                        [
                            'zoom_meeting_id' => $meeting->id,
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
}