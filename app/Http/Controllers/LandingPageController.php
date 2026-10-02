<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Plan;
use App\Models\LandingPageSetting;
use App\Models\LandingPageCustomPage;
use App\Models\Contact;
use App\Models\Newsletter;

class LandingPageController extends Controller
{
    public function show(Request $request)
    {
        $host = $request->getHost();
        $hostParts = explode('.', $host);
        
        // Check if landing page is enabled in settings
        if (!isLandingPageEnabled()) {
            return redirect()->route('login');
        }
        
        $landingSettings = LandingPageSetting::getSettings();
        
        // Get logo from appropriate user based on mode
        $logoUser = null;
        if (config('app.is_saas')) {
            // In SaaS mode, get logo from superadmin
            $logoUser = \App\Models\User::where('type', 'superadmin')->first();
        } else {
            // In non-SaaS mode, get logo from company user with owner role in workspace_member
            $ownerMember = \App\Models\WorkspaceMember::where('role', 'owner')
                ->with('user')
                ->first();
            if ($ownerMember) {
                $logoUser = $ownerMember->user;
            }
        }
        
        // Get logo settings from the appropriate user
        $logoLight = null;
        $logoDark = null;
        if ($logoUser) {
            $workspaceId = config('app.is_saas') ? null : $logoUser->current_workspace_id;
            $logoLightPath = getSetting('logoLight', null, $logoUser->id, $workspaceId);
            $logoDarkPath = getSetting('logoDark', null, $logoUser->id, $workspaceId);
            
            // Convert paths to full URLs
            $logoLight = $logoLightPath ? getFile($logoLightPath) : null;
            $logoDark = $logoDarkPath ? getFile($logoDarkPath) : null;
        }
        
        // Only load plans if SaaS mode is enabled
        $plans = collect([]);
        if (config('app.is_saas')) {
            $plans = Plan::where('is_plan_enable', 'on')->get()->map(function ($plan) {
            $features = [];
            if ($plan->enable_chatgpt === 'on') $features[] = 'AI Integration';
            
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->price,
                'yearly_price' => $plan->yearly_price,
                'duration' => $plan->duration,
                'description' => $plan->description,
                'features' => $features,
                'stats' => [
                    'workspaces' => $plan->workspace_limit ?: 'Unlimited',
                    'users' => $plan->max_users_per_workspace ?: 'Unlimited',
                    'storage' => $plan->storage_limit ? $plan->storage_limit . ' GB' : 'Unlimited',
                    'projects' => $plan->max_projects_per_workspace ?: 'Unlimited'
                ],
                'is_plan_enable' => $plan->is_plan_enable,
                'is_popular' => false // Will be set based on subscriber count
            ];
            });
        }
        
        // Mark most subscribed plan as popular (only if SaaS mode is enabled)
        if (config('app.is_saas') && $plans->isNotEmpty()) {
            $planSubscriberCounts = Plan::withCount('users')->get()->pluck('users_count', 'id');
            if ($planSubscriberCounts->isNotEmpty()) {
                $mostSubscribedPlanId = $planSubscriberCounts->keys()->sortByDesc(function($planId) use ($planSubscriberCounts) {
                    return $planSubscriberCounts[$planId];
                })->first();
                
                $plans = $plans->map(function($plan) use ($mostSubscribedPlanId) {
                    if ($plan['id'] == $mostSubscribedPlanId && $plan['price'] != '0') {
                        $plan['is_popular'] = true;
                    }
                    return $plan;
                });
            }
        }
        
        // Extract testimonials and FAQs from settings
        $testimonialsSection = collect($landingSettings->config_sections['sections'] ?? [])
            ->firstWhere('key', 'testimonials');
        $testimonials = collect($testimonialsSection['testimonials'] ?? [])->map(function($testimonial, $index) {
            return array_merge($testimonial, ['id' => $index + 1]);
        });
        
        $faqSection = collect($landingSettings->config_sections['sections'] ?? [])
            ->firstWhere('key', 'faq');
        $faqs = collect($faqSection['faqs'] ?? [])->map(function($faq, $index) {
            return array_merge($faq, ['id' => $index + 1]);
        });
        
        return Inertia::render('landing-page/index', [
            'plans' => $plans,
            'testimonials' => $testimonials,
            'faqs' => $faqs,
            'customPages' => LandingPageCustomPage::active()->ordered()->get() ?? [],
            'settings' => $landingSettings,
            'isSaas' => config('app.is_saas'),
            'logoLight' => $logoLight,
            'logoDark' => $logoDark,
        ]);
    }

    public function submitContact(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string'
        ]);

        Contact::create([
            'name' => $request->name,
            'email' => $request->email,
            'subject' => $request->subject,
            'message' => $request->message,
            'status' => 'new'
        ]);

        return back()->with('success', __('Thank you for your message. We will get back to you soon!'));
    }

    public function subscribe(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255'
        ]);

        // Check if email already exists
        $existing = Newsletter::where('email', $request->email)->first();
        if ($existing) {
            if ($existing->status === 'unsubscribed') {
                $existing->subscribe();
                return back()->with('success', __('Welcome back! You have been resubscribed to our newsletter.'));
            } else {
                return back()->with('success', __('You are already subscribed to our newsletter.'));
            }
        }

        Newsletter::create([
            'email' => $request->email,
            'status' => 'subscribed',
            'source' => 'landing_page',
            'subscribed_at' => now()
        ]);

        return back()->with('success', __('Thank you for subscribing to our newsletter!'));
    }

    public function settings()
    {
        $landingSettings = LandingPageSetting::getSettings();
        
        return Inertia::render('landing-page/settings', [
            'settings' => $landingSettings,
            'isSaas' => config('app.is_saas')
        ]);
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:255',
            'contact_address' => 'required|string|max:255',
            'config_sections' => 'required|array'
        ]);
        $landingSettings = LandingPageSetting::getSettings();
        $landingSettings->update($request->all());

        return back()->with('success', __('Landing page settings updated successfully!'));
    }
}