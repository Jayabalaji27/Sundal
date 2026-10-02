<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarService;
use App\Models\Setting;
use Illuminate\Http\Request;

class GoogleCalendarController extends Controller
{
    protected $calendarService;

    public function __construct(GoogleCalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    public function getEvents(Request $request)
    {
        try {
            $user = auth()->user();
            $workspaceId = $user->type === 'company' ? $user->current_workspace_id : null;
            
            $events = $this->calendarService->getEvents($user->id, $request->get('maxResults', 50), $workspaceId);
            
            return response()->json([
                'success' => true,
                'events' => $events
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'events' => []
            ]);
        }
    }

    public function syncEvents(Request $request)
    {
        try {
            $user = auth()->user();
            $workspaceId = $user->type === 'company' ? $user->current_workspace_id : null;
            
            $isEnabled = $this->calendarService->isEnabled($user->id, $workspaceId);
            $isAuthorized = $this->calendarService->isAuthorized($user->id, $workspaceId);
            
            if (!$isEnabled || !$isAuthorized) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google Calendar not configured. Please configure Google Calendar JSON credentials in settings.',
                    'needsConfig' => true,
                ]);
            }

            $events = $this->calendarService->getEvents($user->id, 100, $workspaceId);
            
            return response()->json([
                'success' => true,
                'message' => 'Calendar events synchronized successfully',
                'events' => $events
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync calendar events: ' . $e->getMessage(),
            ]);
        }
    }
}
