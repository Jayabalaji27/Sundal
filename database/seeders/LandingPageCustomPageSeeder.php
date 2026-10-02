<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LandingPageCustomPage;

class LandingPageCustomPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            [
                'title' => 'About Us',
                'slug' => 'about-us',
                'content' => "About Our Project Management Platform: Empowering teams to <b>work smarter, collaborate better, and deliver faster</b>.<br>We are dedicated to helping organizations streamline project workflows, optimize team productivity, and achieve project success with ease.<br>Our Taskly platform centralizes project data, automates repetitive tasks, and provides actionable insights to drive team performance.<br>Whether you're a startup or an enterprise, our platform adapts to your project needs—from planning to delivery—ensuring transparency, collaboration, and measurable results.<br><b>Stats:</b> &bull; 4+ Years Industry Experience &bull; 10K+ Active Teams &bull; 50+ Countries Served<br><b>Our Mission:</b> Transform the way teams work by providing scalable, intelligent, and user-friendly project management solutions.<br><b>Our Values:</b> Innovation, transparency, and team success are at the heart of everything we build.<br><b>Our Commitment:</b> Deliver secure, scalable, and reliable project solutions with world-class support.<br><b>Our Vision:</b> A future where every team maximizes its potential through automation, data-driven decisions, and seamless collaboration.",
                'meta_title' => 'About Taskly - Project Management & Team Collaboration Platform',
                'meta_description' => 'Learn about Taskly, the comprehensive project management platform with task management, time tracking, budgeting, and team collaboration features.',
                'is_active' => true,
                'sort_order' => 1
            ],
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'content' => "Privacy Policy for Taskly Project Management Platform: We are committed to <b>protecting your privacy and securing your data</b>.<br>This policy explains how we collect, use, and safeguard your information when using our project management services.<br>We collect only necessary data to provide excellent project management experiences and never sell your personal information.<br><b>Information We Collect:</b> &bull; Account details (name, email, company) &bull; Project data (tasks, files, timesheets) &bull; Usage analytics &bull; Communication within workspaces<br><b>How We Use Data:</b> &bull; Provide project management services &bull; Process payments and subscriptions &bull; Send project notifications &bull; Improve platform functionality<br><b>Data Security:</b> Enterprise-grade encryption, secure backups, and strict access controls protect your information.<br><b>Data Sharing:</b> We never sell your data. Information is only shared within your team and approved integrations.<br><b>Your Rights:</b> Access, update, or delete your data anytime. Contact privacy@taskly.com for assistance.<br><b>Contact:</b> For privacy questions, reach us at privacy@taskly.com or through our support center.",
                'meta_title' => 'Privacy Policy - Taskly',
                'meta_description' => 'Read our privacy policy to understand how Taskly collects, uses, and protects your personal information.',
                'is_active' => true,
                'sort_order' => 2
            ],
            [
                'title' => 'Help & Support',
                'slug' => 'help-support',
                'content' => "Help & Support for Taskly: Get the most out of our platform with <b>comprehensive support resources and expert guidance</b>.<br>We provide extensive documentation, tutorials, and 24/7 support to ensure your team's success with project management.<br>Our support team is dedicated to helping you maximize productivity and achieve your project goals efficiently.<br><b>Quick Start Guide:</b> &bull; Create workspace and invite team members &bull; Set up your first project with milestones &bull; Create and assign tasks &bull; Start time tracking and budget management &bull; Generate progress reports<br><b>Key Features Help:</b> &bull; Projects: Create, organize, and track progress &bull; Tasks: Break down work with deadlines &bull; Timesheets: Track time spent on activities &bull; Budgets: Manage project finances &bull; Reports: Monitor team performance<br><b>Support Channels:</b> &bull; Live chat support (24/7) &bull; Email support: support@taskly.com &bull; Knowledge base and tutorials &bull; Video training sessions &bull; Community forums<br><b>Common Questions:</b> How to invite team members, set up integrations, manage permissions, export data, and customize workflows.<br><b>Training Resources:</b> Free onboarding sessions, webinars, and certification programs available for all users.",
                'meta_title' => 'Help & Support - Taskly Project Management',
                'meta_description' => 'Get help with Taskly project management features. Find guides, documentation, and contact support.',
                'is_active' => true,
                'sort_order' => 4
            ],
        ];

        foreach ($pages as $page) {
            LandingPageCustomPage::updateOrCreate(
                ['slug' => $page['slug']],
                $page
            );
        }
    }
}