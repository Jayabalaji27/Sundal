import React, { useState } from 'react';
import { Plus, Bell, Mail, MessageSquare, Settings } from 'lucide-react';
import {
  Sidebar,
  SidebarContent,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuItem,
  SidebarMenuButton,
  SidebarGroup,
  SidebarGroupLabel,
  SidebarGroupContent,
  SidebarSeparator,
} from '@/components/ui/sidebar';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import NotificationTemplateForm from './NotificationTemplateForm';
import NotificationTemplateList from './NotificationTemplateList';

interface NotificationTemplate {
  id: string;
  name: string;
  type: 'email' | 'push' | 'sms';
  subject?: string;
  content: string;
  isActive: boolean;
  createdAt: string;
}

interface Props {
  className?: string;
}

export default function NotificationTemplateSidebar({ className }: Props) {
  const [activeView, setActiveView] = useState<'list' | 'create' | 'edit'>('list');
  const [selectedTemplate, setSelectedTemplate] = useState<NotificationTemplate | null>(null);
  const [templates, setTemplates] = useState<NotificationTemplate[]>([
    {
      id: '1',
      name: 'Welcome Email',
      type: 'email',
      subject: 'Welcome to Taskly',
      content: 'Welcome to our platform! We\'re excited to have you.',
      isActive: true,
      createdAt: '2024-01-15'
    },
    {
      id: '2',
      name: 'Task Reminder',
      type: 'push',
      content: 'You have pending tasks that need attention.',
      isActive: true,
      createdAt: '2024-01-14'
    }
  ]);

  const handleCreateTemplate = (template: Omit<NotificationTemplate, 'id' | 'createdAt'>) => {
    const newTemplate: NotificationTemplate = {
      ...template,
      id: Date.now().toString(),
      createdAt: new Date().toISOString().split('T')[0]
    };
    setTemplates([...templates, newTemplate]);
    setActiveView('list');
  };

  const handleEditTemplate = (template: NotificationTemplate) => {
    setTemplates(templates.map(t => t.id === template.id ? template : t));
    setActiveView('list');
    setSelectedTemplate(null);
  };

  const handleDeleteTemplate = (id: string) => {
    setTemplates(templates.filter(t => t.id !== id));
  };

  const getTypeIcon = (type: string) => {
    switch (type) {
      case 'email': return <Mail className="h-4 w-4" />;
      case 'push': return <Bell className="h-4 w-4" />;
      case 'sms': return <MessageSquare className="h-4 w-4" />;
      default: return <Bell className="h-4 w-4" />;
    }
  };

  const renderContent = () => {
    switch (activeView) {
      case 'create':
        return (
          <NotificationTemplateForm
            onSubmit={handleCreateTemplate}
            onCancel={() => setActiveView('list')}
          />
        );
      case 'edit':
        return selectedTemplate ? (
          <NotificationTemplateForm
            template={selectedTemplate}
            onSubmit={handleEditTemplate}
            onCancel={() => {
              setActiveView('list');
              setSelectedTemplate(null);
            }}
          />
        ) : null;
      default:
        return (
          <NotificationTemplateList
            templates={templates}
            onEdit={(template) => {
              setSelectedTemplate(template);
              setActiveView('edit');
            }}
            onDelete={handleDeleteTemplate}
          />
        );
    }
  };

  return (
    <Sidebar className={className}>
      <SidebarHeader className="border-b">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">Notification Templates</h2>
          <Button
            size="sm"
            onClick={() => setActiveView('create')}
            className="h-8"
          >
            <Plus className="h-4 w-4 mr-1" />
            New
          </Button>
        </div>
      </SidebarHeader>

      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Quick Actions</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton
                  isActive={activeView === 'list'}
                  onClick={() => setActiveView('list')}
                >
                  <Bell className="h-4 w-4" />
                  All Templates
                  <Badge variant="secondary" className="ml-auto">
                    {templates.length}
                  </Badge>
                </SidebarMenuButton>
              </SidebarMenuItem>
              <SidebarMenuItem>
                <SidebarMenuButton
                  isActive={activeView === 'create'}
                  onClick={() => setActiveView('create')}
                >
                  <Plus className="h-4 w-4" />
                  Create Template
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        <SidebarSeparator />

        <SidebarGroup>
          <SidebarGroupLabel>Template Types</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {['email', 'push', 'sms'].map((type) => {
                const count = templates.filter(t => t.type === type).length;
                return (
                  <SidebarMenuItem key={type}>
                    <SidebarMenuButton>
                      {getTypeIcon(type)}
                      <span className="capitalize">{type}</span>
                      <Badge variant="outline" className="ml-auto">
                        {count}
                      </Badge>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                );
              })}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        <SidebarSeparator />

        <div className="flex-1 p-4">
          {renderContent()}
        </div>
      </SidebarContent>
    </Sidebar>
  );
}