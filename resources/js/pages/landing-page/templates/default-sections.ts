export const defaultLandingPageSections = {
  sections: [
    {
      key: 'header',
      transparent: false,
      background_color: '#ffffff',
      dark_background_color: '#111827',
      text_color: '#1f2937',
      dark_text_color: '#f9fafb',
      button_style: 'gradient'
    },
    {
      key: 'hero',
      title: 'Taskly - Project Management & Team Collaboration',
      subtitle: 'Transform your team productivity with our comprehensive project management platform.',
      announcement_text: '🚀 New: Advanced Analytics Dashboard',
      primary_button_text: 'Start Free Trial',
      secondary_button_text: 'Login',
      image: '',
      background_color: '#f8fafc',
      dark_background_color: '#1f2937',
      text_color: '#1f2937',
      dark_text_color: '#f9fafb',
      layout: 'image-right',
      height: 600,
      stats: [
        { value: '10K+', label: 'Active Users' },
        { value: '50+', label: 'Countries' },
        { value: '99%', label: 'Satisfaction' }
      ],
      card: {
        name: 'John Doe',
        title: 'Senior Developer',
        company: 'Tech Solutions Inc.',
        initials: 'JD'
      }
    },
    {
      key: 'features',
      title: 'Powerful Features for Modern Teams',
      description: 'Everything you need to manage projects, tasks, and team collaboration.',
      background_color: '#ffffff',
      dark_background_color: '#111827',
      layout: 'grid',
      columns: 3,
      image: '',
      show_icons: true,
      features_list: [
        {
          title: 'Project Management',
          description: 'Create projects, assign tasks, and track progress with intuitive dashboards.',
          icon: 'folder'
        },
        {
          title: 'Team Collaboration',
          description: 'Real-time collaboration with comments, file sharing, and notifications.',
          icon: 'users'
        },
        {
          title: 'Time Tracking',
          description: 'Track time spent on tasks and generate detailed reports.',
          icon: 'clock'
        }
      ]
    },
    {
      key: 'screenshots',
      title: 'See Taskly in Action',
      subtitle: 'Explore our intuitive interface and powerful features designed to streamline your project management.',
      screenshots_list: [
        {
          src: '/screenshots/dashboard.png',
          alt: 'Taskly Dashboard Overview',
          title: 'Dashboard Overview',
          description: 'Comprehensive dashboard with project analytics and team performance'
        },
        {
         src: '/screenshots/projects.png',
         alt: 'Project Management Interface',
         title: 'Project Management',
         description: 'Intuitive project interface for efficient task and milestone tracking'
        },
        {
          src: '/screenshots/tasks.png',
          alt: 'Task Management',
          title: 'Task Management',
          description: 'Streamlined task creation and assignment with progress tracking'
        },
        {
          src: '/screenshots/team.png',
          alt: 'Team Collaboration',
          title: 'Team Collaboration',
          description: 'Comprehensive team workspace with real-time collaboration tools'
        },
        {
          src: '/screenshots/reports.png',
          alt: 'Project Reports',
          title: 'Project Reports',
          description: 'Comprehensive project analytics with time tracking and progress reports'
        },
        {
          src: '/screenshots/timesheet.png',
          alt: 'Time Tracking',
          title: 'Time Tracking',
          description: 'Complete time tracking with detailed timesheets and billing reports'
        }
      ]
    },
    {
      key: 'why_choose_us',
      title: 'Why Choose Taskly?',
      subtitle: 'We\'re not just another project management platform.',
      reasons: [
        { title: 'Quick Setup', description: 'Get your team organized and productive in under 5 minutes.', icon: 'clock' },
        { title: 'Team Network', description: 'Join thousands of teams using our platform.', icon: 'users' }
      ],
      stats: [
        { value: '10K+', label: 'Active Users', color: 'blue' },
        { value: '99%', label: 'Satisfaction', color: 'green' }
      ]
    },
    {
      key: 'templates',
      title: 'Explore Our Templates',
      subtitle: 'Choose from our professionally designed templates to create your perfect digital business card.',
      background_color: '#f8fafc',
      layout: 'grid',
      columns: 3,
      templates_list: [
        { name: 'freelancer', category: 'professional' },
        { name: 'doctor', category: 'medical' },
        { name: 'restaurant', category: 'food' },
        { name: 'realestate', category: 'business' },
        { name: 'fitness', category: 'health' },
        { name: 'photography', category: 'creative' },
        { name: 'lawfirm', category: 'professional' },
        { name: 'cafe', category: 'food' },
        { name: 'salon', category: 'beauty' },
        { name: 'construction', category: 'business' },
        { name: 'eventplanner', category: 'services' },
        { name: 'tech-startup', category: 'technology' }
      ],
      cta_text: 'View All Templates',
      cta_link: '#'
    },
    {
      key: 'about',
      title: 'About Taskly',
      description: 'We are passionate about transforming how teams collaborate and manage projects.',
      story_title: 'Empowering Team Productivity Since 2020',
      story_content: 'Founded by a team of project management enthusiasts and technology experts, Taskly was born from the frustration of scattered tools and inefficient workflows.',
      image: '',
      background_color: '#f9fafb',
      dark_background_color: '#1f2937',
      layout: 'image-right',
      stats: [
        { value: '4+ Years', label: 'Experience', color: 'blue' },
        { value: '10K+', label: 'Happy Teams', color: 'green' },
        { value: '50+', label: 'Countries', color: 'purple' }
      ]
    },
    {
      key: 'team',
      title: 'Meet Our Team',
      subtitle: 'We\'re a diverse team of innovators and problem-solvers.',
      cta_title: 'Want to Join Our Team?',
      cta_description: 'We\'re always looking for talented individuals.',
      cta_button_text: 'View Open Positions',
      members: [
        { name: 'Sarah Johnson', role: 'CEO & Founder', bio: 'Former tech executive with 15+ years experience.', image: '', linkedin: '#', email: 'sarah@taskly.com' }
      ]
    },
    {
      key: 'testimonials',
      title: 'What Our Clients Say',
      subtitle: 'Don\'t just take our word for it.',
      trust_title: 'Trusted by Teams Worldwide',
      trust_stats: [
        { value: '4.9/5', label: 'Average Rating', color: 'blue' },
        { value: '10K+', label: 'Happy Teams', color: 'green' }
      ],
      testimonials: [
        { name: 'Alex Thompson', role: 'Project Manager', company: 'TechCorp Inc.', content: 'Taskly has revolutionized how we manage projects and collaborate as a team.', rating: 5 }
      ]
    },
    {
      key: 'plans',
      title: 'Choose Your Plan',
      subtitle: 'Start with our free plan and upgrade as you grow.',
      faq_text: 'Have questions about our plans? Contact our sales team'
    },
    {
      key: 'faq',
      title: 'Frequently Asked Questions',
      subtitle: 'Got questions? We\'ve got answers.',
      cta_text: 'Still have questions?',
      button_text: 'Contact Support',
      faqs: [
        { question: 'How does Taskly work?', answer: 'Taskly allows you to create projects, assign tasks, track progress, and collaborate with your team efficiently.' }
      ]
    },
    {
      key: 'newsletter',
      title: 'Stay Updated with Taskly',
      subtitle: 'Get the latest updates and project management tips.',
      privacy_text: 'No spam, unsubscribe at any time.',
      benefits: [
        { icon: '📧', title: 'Weekly Updates', description: 'Latest features and improvements' }
      ]
    },
    {
      key: 'contact',
      title: 'Get in Touch',
      subtitle: 'Have questions about Taskly? We\'d love to hear from you.',
      form_title: 'Send us a Message',
      info_title: 'Contact Information',
      info_description: 'We\'re here to help and answer any question you might have.',
      layout: 'split',
      background_color: '#f9fafb',
      dark_background_color: '#1f2937'
    },
    {
      key: 'footer',
      description: 'Transforming team productivity with innovative project management solutions.',
      newsletter_title: 'Stay Updated',
      newsletter_subtitle: 'Join our newsletter for updates',
      links: {
        product: [{ name: 'Features', href: '#features' }, { name: 'Pricing', href: '#pricing' }],
        company: [{ name: 'About Us', href: '#about' }, { name: 'Contact', href: '#contact' }]
      },
      social_links: [
        { name: 'Facebook', icon: 'Facebook', href: '#' },
        { name: 'Twitter', icon: 'Twitter', href: '#' }
      ],
      section_titles: {
        product: 'Product',
        company: 'Company'
      }
    }
  ],
  theme: {
    primary_color: '#10b981',
    secondary_color: '#ffffff',
    accent_color: '#f7f7f7',
    logo_light: '',
    logo_dark: '',
    favicon: '',
    dark_mode_colors: {
      background: '#111827',
      surface: '#1f2937',
      text_primary: '#f9fafb',
      text_secondary: '#d1d5db'
    }
  },
  seo: {
    meta_title: 'POS System - Point of Sale Solutions',
    meta_description: 'Streamline your retail operations with our comprehensive POS system.',
    meta_keywords: 'pos system, point of sale, retail management, inventory, sales'
  },
  custom_css: '',
  custom_js: '',
  section_order: ['header', 'hero', 'features', 'screenshots', 'why_choose_us', 'about', 'team', 'testimonials', 'plans', 'faq', 'newsletter', 'contact', 'footer'],
  section_visibility: {
    header: true,
    hero: true,
    features: true,
    screenshots: true,
    why_choose_us: true,
    // templates: true,
    about: true,
    team: true,
    testimonials: true,
    plans: true,
    faq: true,
    newsletter: true,
    contact: true,
    footer: true
  }
};