<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Models\ProjectMember;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class ProjectInvitationController extends Controller
{
    public function inviteClient(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email'
        ]);

        // Create client user
        $client = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(Str::random(12)),
            'type' => 'client',
            'created_by' => auth()->id()
        ]);

        // Assign client to project
        $project->update(['client_id' => $client->id]);

        $project->logActivity('client_invited', "Client '{$client->name}' was invited to project");

        return back();
    }

    public function inviteMember(Request $request, Project $project)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:manager,member,client'
        ]);

        ProjectMember::updateOrCreate(
            ['project_id' => $project->id, 'user_id' => $validated['user_id']],
            ['role' => $validated['role'], 'assigned_by' => auth()->id()]
        );

        $user = User::find($validated['user_id']);
        $project->logActivity('member_invited', "Member '{$user->name}' was invited to project");

        return back();
    }
}