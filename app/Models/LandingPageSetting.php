<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageSetting extends Model
{
    protected $fillable = [
        'company_name',
        'contact_email',
        'contact_phone',
        'contact_address',
        'config_sections'
    ];

    protected $attributes = [
        'company_name' => '',
        'contact_email' => '',
        'contact_phone' => '',
        'contact_address' => ''
    ];

    protected $casts = [
        'config_sections' => 'array'
    ];

    public static function getSettings()
    {
        $settings = self::first();
        $app_name = isSaasMode() ? 'Taskly SaaS' : 'Taskly';

        if (!$settings) {
            $defaultConfig = isSaasMode() ? self::getSaasConfig($app_name) : self::getNonSaasConfig($app_name);

            $settings = self::create([
                'company_name' => $app_name,
                'contact_email' => 'support@taskly.com',
                'contact_phone' => '+1 (555) 123-4567',
                'contact_address' => 'San Francisco, CA',
                'config_sections' => $defaultConfig
            ]);
        }
        return $settings;
    }

    private static function getSaasConfig($app_name)
    {
        return [
            'sections' => [
                [
                    'key' => 'header',
                    'transparent' => false,
                    'background_color' => '#ffffff',
                    'text_color' => '#1f2937',
                    'button_style' => 'gradient'
                ],
                [
                    'key' => 'hero',
                    'title' => $app_name . ' - Project Management & Team Collaboration',
                    'subtitle' => 'Transform your team productivity with our comprehensive project management platform.',
                    'announcement_text' => '🚀 New: Advanced Analytics Dashboard',
                    'primary_button_text' => 'Start Free Trial',
                    'secondary_button_text' => 'Login',
                    'image' => '',
                    'background_color' => '#f8fafc',
                    'text_color' => '#1f2937',
                    'layout' => 'image-right',
                    'height' => 600,
                    'stats' => [
                        ['value' => '10K+', 'label' => 'Active Users'],
                        ['value' => '50+', 'label' => 'Countries'],
                        ['value' => '99%', 'label' => 'Satisfaction']
                    ],
                    'card' => [
                        'name' => 'John Doe',
                        'title' => 'Senior Developer',
                        'company' => 'Tech Solutions Inc.',
                        'initials' => 'JD'
                    ]
                ],
                [
                    'key' => 'features',
                    'title' => 'Powerful Features for Modern Teams',
                    'description' => 'Everything you need to manage projects, tasks, and team collaboration.',
                    'background_color' => '#ffffff',
                    'dark_background_color' => '#111827',
                    'layout' => 'grid',
                    'columns' => 3,
                    'image' => '',
                    'show_icons' => true,
                    'features_list' => [
                        ['title' => 'Project Management', 'description' => 'Create projects, assign tasks, and track progress with intuitive dashboards.', 'icon' => 'folder'],
                        ['title' => 'Team Collaboration', 'description' => 'Real-time collaboration with comments, file sharing, and notifications.', 'icon' => 'users'],
                        ['title' => 'Time Tracking', 'description' => 'Track time spent on tasks and generate detailed reports.', 'icon' => 'clock'],
                    ]
                ],
                [
                    'key' => 'screenshots',
                    'title' => 'See ' . $app_name . ' in Action',
                    'subtitle' => 'Explore our intuitive interface and powerful features designed to streamline your project management.',
                    'screenshots_list' => [
                        ['src' => '/screenshots/saas/dashboard.png', 'alt' => 'Workspace Management', 'title' => 'Workspace Management', 'description' => '; multiple workspaces with role-based permissions, team member invitations, and customizable workspace settings for different projects and departments'],
                        ['src' => '/screenshots/saas/projects.png', 'alt' => 'Project Dashboard', 'title' => 'Project Dashboard', 'description' => 'Comprehensive project overview with real-time progress tracking, milestone management, team performance metrics, and interactive charts for project analytics'],
                        ['src' => '/screenshots/saas/tasks.png', 'alt' => 'Task Management', 'title' => 'Task Management', 'description' => 'Advanced task management with Kanban boards, priority levels, due dates, task dependencies, file attachments, and collaborative comments for seamless team coordination'],
                        ['src' => '/screenshots/saas/budget.png', 'alt' => 'Budget Management', 'title' => 'Budget Management', 'description' => 'Complete financial oversight with budget allocation, expense tracking, cost analysis, budget vs actual reporting, and automated alerts for budget thresholds'],
                        ['src' => '/screenshots/saas/invoices.png', 'alt' => 'Invoice Management', 'title' => 'Invoice Management', 'description' => 'Professional invoice creation with customizable templates, automated billing cycles, payment tracking, client management, and integration with popular payment gateways'],
                        ['src' => '/screenshots/saas/timesheet.png', 'alt' => 'Time Tracking', 'title' => 'Time Tracking', 'description' => 'Comprehensive time tracking with start/stop timers, manual time entry, project-based time logging, detailed timesheets, and productivity analytics accessible from any device']
                    ]
                ],
                [
                    'key' => 'why_choose_us',
                    'title' => 'Why Choose ' . $app_name . '?',
                    'subtitle' => 'We\'re not just another project management platform.',
                    'reasons' => [
                        ['title' => 'Quick Setup', 'description' => 'Get your team organized and productive in under 5 minutes.', 'icon' => 'clock'],
                        ['title' => 'Team Network', 'description' => 'Join thousands of teams using our platform.', 'icon' => 'users']
                    ],
                    'stats' => [
                        ['value' => '10K+', 'label' => 'Active Users', 'color' => 'blue'],
                        ['value' => '99%', 'label' => 'Satisfaction', 'color' => 'green']
                    ]
                ],
                [
                    'key' => 'about',
                    'title' => 'About ' . $app_name . ',',
                    'description' => 'We are passionate about transforming how teams collaborate and manage projects.',
                    'story_title' => 'Empowering Team Productivity Since 2020',
                    'story_content' => 'Founded by a team of project management enthusiasts and technology experts, ' . $app_name . ' was born from the frustration of scattered tools and inefficient workflows.',
                    'image' => '',
                    'background_color' => '#f9fafb',
                    'layout' => 'image-right',
                    'stats' => [
                        ['value' => '4+ Years', 'label' => 'Experience', 'color' => 'blue'],
                        ['value' => '10K+', 'label' => 'Happy Teams', 'color' => 'green'],
                        ['value' => '50+', 'label' => 'Countries', 'color' => 'purple']
                    ]
                ],
                [
                    'key' => 'team',
                    'title' => 'Meet Our Team',
                    'subtitle' => 'We\'re a diverse team of innovators and problem-solvers.',
                    'cta_title' => 'Want to Join Our Team?',
                    'cta_description' => 'We\'re always looking for talented individuals.',
                    'cta_button_text' => 'View Open Positions',
                    'members' => [
                        ['name' => 'Sarah Johnson', 'role' => 'CEO & Founder', 'bio' => 'Former tech executive with 15+ years experience.', 'image' => '', 'linkedin' => '#', 'email' => 'sarah@taskly.com'],
                        ['name' => 'Michael Chen', 'role' => 'CTO', 'bio' => 'Software architect with expertise in cloud solutions.', 'image' => '', 'linkedin' => '#', 'email' => 'michael@taskly.com'],
                        ['name' => 'Emily Rodriguez', 'role' => 'Head of Product', 'bio' => 'Product strategist focused on user experience.', 'image' => '', 'linkedin' => '#', 'email' => 'emily@taskly.com'],
                        ['name' => 'David Kim', 'role' => 'Lead Developer', 'bio' => 'Full-stack developer specializing in scalable applications.', 'image' => '', 'linkedin' => '#', 'email' => 'david@taskly.com'],
                    ]
                ],
                [
                    'key' => 'testimonials',
                    'title' => 'What Our Clients Say',
                    'subtitle' => 'Don\'t just take our word for it.',
                    'trust_title' => 'Trusted by Teams Worldwide',
                    'trust_stats' => [
                        ['value' => '4.9/5', 'label' => 'Average Rating', 'color' => 'blue'],
                        ['value' => '10K+', 'label' => 'Happy Teams', 'color' => 'green']
                    ],
                    'testimonials' => [
                        ['name' => 'Alex Thompson', 'role' => 'Project Manager', 'company' => 'TechCorp Inc.', 'content' => $app_name . ' has revolutionized how we manage projects and collaborate as a team.', 'rating' => 5],
                        ['name' => 'Sarah Miller', 'role' => 'Product Owner', 'company' => 'Digital Solutions Ltd', 'content' => 'The intuitive interface and powerful features have made project tracking a breeze.', 'rating' => 5],
                        ['name' => 'Michael Chen', 'role' => 'Team Lead', 'company' => 'InnovateTech', 'content' => 'Outstanding collaboration tools that have significantly improved our team productivity.', 'rating' => 5]
                    ]
                ],
                [
                    'key' => 'plans',
                    'title' => 'Choose Your ' . $app_name . ' Plan',
                    'subtitle' => 'Start with our free plan and upgrade as your team grows.',
                    'faq_text' => 'Have questions about our plans? Contact our sales team for guidance.'
                ],
                [
                    'key' => 'faq',
                    'title' => 'Frequently Asked Questions',
                    'subtitle' => 'Got questions? We\'ve got answers.',
                    'cta_text' => 'Still have questions?',
                    'button_text' => 'Contact Support',
                    'faqs' => [
                        ['question' => 'How does ' . $app_name . ' work?', 'answer' => $app_name . ' helps teams organize, track, and complete tasks efficiently. Create projects, assign tasks, set deadlines, and collaborate in real-time.'],
                        ['question' => 'Is my data secure?', 'answer' => 'Yes, we use enterprise-grade security with end-to-end encryption. Your data is stored securely and backed up regularly.'],
                        ['question' => 'Can I customize workflows?', 'answer' => 'Absolutely! Create custom task stages, set up automation rules, and configure workflows that match your team\'s process.'],
                        ['question' => 'Do you offer integrations?', 'answer' => 'Yes, we integrate with popular tools like Slack, Google Workspace, Microsoft Teams, and many more.'],
                        ['question' => 'What support do you provide?', 'answer' => '24/7 customer support via chat and email, plus comprehensive documentation and video tutorials.']
                    ]
                ],
                [
                    'key' => 'newsletter',
                    'title' => 'Stay Updated with ' . $app_name . ',',
                    'subtitle' => 'Get the latest updates and project management tips.',
                    'privacy_text' => 'No spam, unsubscribe at any time.',
                    'benefits' => [
                        ['icon' => '📧', 'title' => 'Product Updates', 'description' => 'Latest features and improvements'],
                        ['icon' => '💡', 'title' => 'Productivity Tips', 'description' => 'Expert advice and best practices'],
                        ['icon' => '🎁', 'title' => 'Exclusive Access', 'description' => 'Early access to new features'],
                    ]
                ],
                [
                    'key' => 'contact',
                    'title' => 'Get in Touch',
                    'subtitle' => 'Have questions about ' . $app_name . '? We\'d love to hear from you.',
                    'form_title' => 'Send us a Message',
                    'info_title' => 'Contact Information',
                    'info_description' => 'We\'re here to help and answer any question you might have.',
                    'layout' => 'split',
                    'background_color' => '#f9fafb'
                ],
                [
                    'key' => 'footer',
                    'description' => 'Streamline project management with powerful collaboration tools.',
                    'newsletter_title' => 'Stay Updated',
                    'newsletter_subtitle' => 'Get project management tips and product updates',
                    'links' => [
                        'product' => [['name' => 'Features', 'href' => '#features'], ['name' => 'Pricing', 'href' => '#pricing']],
                        'company' => [['name' => 'About Us', 'href' => '#about'], ['name' => 'Contact', 'href' => '#contact']],
                        'support' => [['name' => 'Help Center', 'href' => '#help'], ['name' => 'Terms of Service', 'href' => '#terms']],
                        'legal' => [['name' => 'Privacy Policy', 'href' => '#privacy'], ['name' => 'Terms of Service', 'href' => '#terms']],
                    ],
                    'social_links' => [
                        ['name' => 'Facebook', 'icon' => 'Facebook', 'href' => '#'],
                        ['name' => 'Twitter', 'icon' => 'Twitter', 'href' => '#']
                    ],
                    'section_titles' => [
                        'product' => 'Product',
                        'company' => 'Company',
                        'support' => 'Support',
                        'legal' => 'Legal'
                    ]
                ]
            ],
            'theme' => [
                'primary_color' => '#10b981',
                'secondary_color' => '#ffffff',
                'accent_color' => '#f7f7f7',
                'logo_light' => '',
                'logo_dark' => '',
                'favicon' => ''
            ],
            'seo' => [
                'meta_title' => $app_name . ' - Project Management & Team Collaboration',
                'meta_description' => 'Streamline your team productivity with our comprehensive project management platform.',
                'meta_keywords' => 'project management, team collaboration, task management, productivity, workflow'
            ],
            'custom_css' => '',
            'custom_js' => '',
            'section_order' => ['header', 'hero', 'features', 'screenshots', 'why_choose_us', 'about', 'team', 'testimonials', 'plans', 'faq', 'newsletter', 'contact', 'footer'],
            'section_visibility' => [
                'header' => true,
                'hero' => true,
                'features' => true,
                'screenshots' => true,
                'why_choose_us' => true,
                'about' => true,
                'team' => true,
                'testimonials' => true,
                'plans' => true,
                'faq' => true,
                'newsletter' => true,
                'contact' => true,
                'footer' => true
            ]
        ];
    }

    private static function getNonSaasConfig($app_name)
    {
        return [
            'sections' => [
                [
                    'key' => 'header',
                    'transparent' => false,
                    'background_color' => '#ffffff',
                    'text_color' => '#1f2937',
                    'button_style' => 'gradient'
                ],
                [
                    'key' => 'hero',
                    'title' => $app_name . ' - Project Management & Team Collaboration',
                    'subtitle' => 'Transform your team productivity with our comprehensive project management platform.',
                    'announcement_text' => '🚀 New: Advanced Analytics Dashboard',
                    'primary_button_text' => 'Get Started',
                    'secondary_button_text' => 'Login',
                    'image' => '',
                    'background_color' => '#f8fafc',
                    'text_color' => '#1f2937',
                    'layout' => 'image-right',
                    'height' => 600,
                    'stats' => [
                        ['value' => '10K+', 'label' => 'Active Users'],
                        ['value' => '50+', 'label' => 'Countries'],
                        ['value' => '99%', 'label' => 'Satisfaction']
                    ],
                    'card' => [
                        'name' => 'John Doe',
                        'title' => 'Senior Developer',
                        'company' => 'Tech Solutions Inc.',
                        'initials' => 'JD'
                    ]
                ],
                [
                    'key' => 'features',
                    'title' => 'Powerful Features for Modern Teams',
                    'description' => 'Everything you need to manage projects, tasks, and team collaboration.',
                    'background_color' => '#ffffff',
                    'layout' => 'grid',
                    'columns' => 3,
                    'image' => '',
                    'show_icons' => true,
                    'features_list' => [
                        ['title' => 'Project Management', 'description' => 'Create projects, assign tasks, and track progress with intuitive dashboards.', 'icon' => 'folder'],
                        ['title' => 'Team Collaboration', 'description' => 'Real-time collaboration with comments, file sharing, and notifications.', 'icon' => 'users'],
                        ['title' => 'Time Tracking', 'description' => 'Track time spent on tasks and generate detailed reports.', 'icon' => 'clock']
                    ]
                ],
                [
                    'key' => 'screenshots',
                    'title' => 'See ' . $app_name . ' in Action',
                    'subtitle' => 'Explore our intuitive interface and powerful features designed to streamline your project management.',
                    'screenshots_list' => [
                        ['src' => '/screenshots/non-saas/dashboard.png', 'alt' => 'Workspace Management', 'title' => 'Workspace Management', 'description' => '; multiple workspaces with role-based permissions, team member invitations, and customizable workspace settings for different projects and departments'],
                        ['src' => '/screenshots/non-saas/projects.png', 'alt' => 'Project Dashboard', 'title' => 'Project Dashboard', 'description' => 'Comprehensive project overview with real-time progress tracking, milestone management, team performance metrics, and interactive charts for project analytics'],
                        ['src' => '/screenshots/non-saas/tasks.png', 'alt' => 'Task Management', 'title' => 'Task Management', 'description' => 'Advanced task management with Kanban boards, priority levels, due dates, task dependencies, file attachments, and collaborative comments for seamless team coordination'],
                        ['src' => '/screenshots/non-saas/budget.png', 'alt' => 'Budget Management', 'title' => 'Budget Management', 'description' => 'Complete financial oversight with budget allocation, expense tracking, cost analysis, budget vs actual reporting, and automated alerts for budget thresholds'],
                        ['src' => '/screenshots/non-saas/invoices.png', 'alt' => 'Invoice Management', 'title' => 'Invoice Management', 'description' => 'Professional invoice creation with customizable templates, automated billing cycles, payment tracking, client management, and integration with popular payment gateways'],
                        ['src' => '/screenshots/non-saas/timesheet.png', 'alt' => 'Time Tracking', 'title' => 'Time Tracking', 'description' => 'Comprehensive time tracking with start/stop timers, manual time entry, project-based time logging, detailed timesheets, and productivity analytics accessible from any device']
                    ]
                ],
                [
                    'key' => 'why_choose_us',
                    'title' => 'Why Choose ' . $app_name . '?',
                    'subtitle' => 'We\'re not just another project management platform.',
                    'reasons' => [
                        ['title' => 'Quick Setup', 'description' => 'Get your team organized and productive in under 5 minutes.', 'icon' => 'clock'],
                        ['title' => 'Team Network', 'description' => 'Join thousands of teams using our platform.', 'icon' => 'users']
                    ],
                    'stats' => [
                        ['value' => '10K+', 'label' => 'Active Users', 'color' => 'blue'],
                        ['value' => '99%', 'label' => 'Satisfaction', 'color' => 'green']
                    ]
                ],
                [
                    'key' => 'about',
                    'title' => 'About ' . $app_name . ',',
                    'description' => 'We are passionate about transforming how teams collaborate and manage projects.',
                    'story_title' => 'Empowering Team Productivity Since 2020',
                    'story_content' => 'Founded by a team of project management enthusiasts and technology experts, ' . $app_name . ' was born from the frustration of scattered tools and inefficient workflows.',
                    'image' => '',
                    'background_color' => '#f9fafb',
                    'layout' => 'image-right',
                    'stats' => [
                        ['value' => '4+ Years', 'label' => 'Experience', 'color' => 'blue'],
                        ['value' => '10K+', 'label' => 'Happy Teams', 'color' => 'green'],
                        ['value' => '50+', 'label' => 'Countries', 'color' => 'purple']
                    ]
                ],
                [
                    'key' => 'team',
                    'title' => 'Meet Our Team',
                    'subtitle' => 'We\'re a diverse team of innovators and problem-solvers.',
                    'cta_title' => 'Want to Join Our Team?',
                    'cta_description' => 'We\'re always looking for talented individuals.',
                    'cta_button_text' => 'View Open Positions',
                    'members' => [
                        ['name' => 'Sarah Johnson', 'role' => 'CEO & Founder', 'bio' => 'Former tech executive with 15+ years experience.', 'image' => '', 'linkedin' => '#', 'email' => 'sarah@taskly.com'],
                        ['name' => 'Michael Chen', 'role' => 'CTO', 'bio' => 'Software architect with expertise in cloud solutions.', 'image' => '', 'linkedin' => '#', 'email' => 'michael@taskly.com'],
                        ['name' => 'Emily Rodriguez', 'role' => 'Head of Product', 'bio' => 'Product strategist focused on user experience.', 'image' => '', 'linkedin' => '#', 'email' => 'emily@taskly.com'],
                        ['name' => 'David Kim', 'role' => 'Lead Developer', 'bio' => 'Full-stack developer specializing in scalable applications.', 'image' => '', 'linkedin' => '#', 'email' => 'david@taskly.com'],
                    ]
                ],
                [
                    'key' => 'testimonials',
                    'title' => 'What Our Clients Say',
                    'subtitle' => 'Don\'t just take our word for it.',
                    'trust_title' => 'Trusted by Teams Worldwide',
                    'trust_stats' => [
                        ['value' => '4.9/5', 'label' => 'Average Rating', 'color' => 'blue'],
                        ['value' => '10K+', 'label' => 'Happy Teams', 'color' => 'green']
                    ],
                    'testimonials' => [
                        ['name' => 'Alex Thompson', 'role' => 'Project Manager', 'company' => 'TechCorp Inc.', 'content' => $app_name . ' has revolutionized how we manage projects and collaborate as a team.', 'rating' => 5],
                        ['name' => 'Sarah Miller', 'role' => 'Product Owner', 'company' => 'Digital Solutions Ltd', 'content' => 'The intuitive interface and powerful features have made project tracking a breeze.', 'rating' => 5],
                        ['name' => 'Michael Chen', 'role' => 'Team Lead', 'company' => 'InnovateTech', 'content' => 'Outstanding collaboration tools that have significantly improved our team productivity.', 'rating' => 5]
                    ]
                ],
                [
                    'key' => 'faq',
                    'title' => 'Frequently Asked Questions',
                    'subtitle' => 'Got questions? We\'ve got answers.',
                    'cta_text' => 'Still have questions?',
                    'button_text' => 'Contact Support',
                    'faqs' => [
                        ['question' => 'How does ' . $app_name . ' work?', 'answer' => $app_name . ' helps teams organize, track, and complete tasks efficiently. Create projects, assign tasks, set deadlines, and collaborate in real-time.'],
                        ['question' => 'Is my data secure?', 'answer' => 'Yes, we use enterprise-grade security with end-to-end encryption. Your data is stored securely and backed up regularly.'],
                        ['question' => 'Can I customize workflows?', 'answer' => 'Absolutely! Create custom task stages, set up automation rules, and configure workflows that match your team\'s process.'],
                        ['question' => 'Do you offer integrations?', 'answer' => 'Yes, we integrate with popular tools like Slack, Google Workspace, Microsoft Teams, and many more.'],
                        ['question' => 'What support do you provide?', 'answer' => '24/7 customer support via chat and email, plus comprehensive documentation and video tutorials.']
                    ]
                ],
                [
                    'key' => 'newsletter',
                    'title' => 'Stay Updated with ' . $app_name . ',',
                    'subtitle' => 'Get the latest updates and project management tips.',
                    'privacy_text' => 'No spam, unsubscribe at any time.',
                    'benefits' => [
                        ['icon' => '📧', 'title' => 'Product Updates', 'description' => 'Latest features and improvements'],
                        ['icon' => '💡', 'title' => 'Productivity Tips', 'description' => 'Expert advice and best practices'],
                        ['icon' => '🎁', 'title' => 'Exclusive Access', 'description' => 'Early access to new features'],
                    ]
                ],
                [
                    'key' => 'contact',
                    'title' => 'Get in Touch',
                    'subtitle' => 'Have questions about ' . $app_name . '? We\'d love to hear from you.',
                    'form_title' => 'Send us a Message',
                    'info_title' => 'Contact Information',
                    'info_description' => 'We\'re here to help and answer any question you might have.',
                    'layout' => 'split',
                    'background_color' => '#f9fafb'
                ],
                [
                    'key' => 'footer',
                    'description' => 'Transforming team productivity with innovative project management solutions.',
                    'newsletter_title' => 'Stay Updated',
                    'newsletter_subtitle' => 'Join our newsletter for updates',
                    'links' => [
                        'product' => [['name' => 'Features', 'href' => '#features'], ['name' => 'Pricing', 'href' => '#pricing']],
                        'company' => [['name' => 'About Us', 'href' => '#about'], ['name' => 'Contact', 'href' => '#contact']],
                        'support' => [['name' => 'Help Center', 'href' => '#help'], ['name' => 'Terms of Service', 'href' => '#terms']],
                        'legal' => [['name' => 'Privacy Policy', 'href' => '#privacy'], ['name' => 'Terms of Service', 'href' => '#terms']],
                    ],
                    'social_links' => [
                        ['name' => 'Facebook', 'icon' => 'Facebook', 'href' => '#'],
                        ['name' => 'Twitter', 'icon' => 'Twitter', 'href' => '#']
                    ],
                    'section_titles' => [
                        'product' => 'Product',
                        'company' => 'Company',
                        'support' => 'Support',
                        'legal' => 'Legal'
                    ]
                ]
            ],
            'theme' => [
                'primary_color' => '#10b981',
                'secondary_color' => '#ffffff',
                'accent_color' => '#f7f7f7',
                'logo_light' => '',
                'logo_dark' => '',
                'favicon' => ''
            ],
            'seo' => [
                'meta_title' => $app_name . ' - Project Management & Team Collaboration',
                'meta_description' => 'Streamline your team productivity with our comprehensive project management platform.',
                'meta_keywords' => 'project management, team collaboration, task management, productivity, workflow'
            ],
            'custom_css' => '',
            'custom_js' => '',
            'section_order' => ['header', 'hero', 'features', 'screenshots', 'why_choose_us', 'about', 'team', 'testimonials', 'faq', 'newsletter', 'contact', 'footer'],
            'section_visibility' => [
                'header' => true,
                'hero' => true,
                'features' => true,
                'screenshots' => true,
                'why_choose_us' => true,
                'about' => true,
                'team' => true,
                'testimonials' => true,
                'faq' => true,
                'newsletter' => true,
                'contact' => true,
                'footer' => true
            ]
        ];
    }
}