import { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeft, Mail, User, Calendar, MessageSquare, Edit, Trash2 } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';

export default function ContactShow() {
  const { t } = useTranslation();
  const { contact, flash } = usePage().props as any;
  
  // State
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [status, setStatus] = useState(contact.status);
  const [adminNotes, setAdminNotes] = useState(contact.admin_notes || '');
  const [isUpdating, setIsUpdating] = useState(false);
  
  // Handle flash messages
  useEffect(() => {
    if (flash?.success) {
      toast.success(flash.success);
    }
    if (flash?.error) {
      toast.error(flash.error);
    }
  }, [flash]);
  
  const handleStatusUpdate = () => {
    setIsUpdating(true);
    
    router.put(route('contacts.update-status', contact.id), {
      status,
      admin_notes: adminNotes
    }, {
      onSuccess: () => {
        setIsUpdating(false);
        toast.success(t('Contact status updated successfully'));
      },
      onError: () => {
        setIsUpdating(false);
        toast.error(t('Failed to update contact status'));
      }
    });
  };
  
  const handleEdit = () => {
    router.get(route('contacts.index'), {}, {
      onSuccess: () => {
        // This would typically open an edit modal, but since we're using the index page pattern
        // we'll redirect back to index where the edit functionality exists
      }
    });
  };
  
  const handleDelete = () => {
    setIsDeleteModalOpen(true);
  };
  
  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting contact...'));
    
    router.delete(route('contacts.destroy', contact.id), {
      onSuccess: () => {
        toast.dismiss();
        router.get(route('contacts.index'));
      },
      onError: () => {
        toast.dismiss();
        toast.error(t('Failed to delete contact'));
        setIsDeleteModalOpen(false);
      }
    });
  };
  
  const getStatusColor = (status: string) => {
    switch (status) {
      case 'new':
        return 'bg-blue-100 text-blue-800';
      case 'read':
        return 'bg-yellow-100 text-yellow-800';
      case 'replied':
        return 'bg-green-100 text-green-800';
      case 'closed':
        return 'bg-gray-100 text-gray-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const pageActions = [
    {
      label: t('Edit'),
      icon: <Edit className="h-4 w-4 mr-2" />,
      variant: 'outline',
      onClick: handleEdit
    },
    {
      label: t('Delete'),
      icon: <Trash2 className="h-4 w-4 mr-2" />,
      variant: 'destructive',
      onClick: handleDelete
    }
  ];

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Contacts'), href: route('contacts.index') },
    { title: contact.name }
  ];

  return (
    <PageTemplate 
      title={t("Contact Details")} 
      url={`/contacts/${contact.id}`}
      actions={pageActions}
      breadcrumbs={breadcrumbs}
    >
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Contact Information */}
        <div className="lg:col-span-2 space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <User className="h-5 w-5" />
                {t('Contact Information')}
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <Label className="text-sm font-medium text-gray-500">{t('Name')}</Label>
                  <p className="mt-1 text-sm text-gray-900">{contact.name}</p>
                </div>
                <div>
                  <Label className="text-sm font-medium text-gray-500">{t('Email')}</Label>
                  <div className="mt-1 flex items-center gap-2">
                    <Mail className="h-4 w-4 text-gray-400" />
                    <a 
                      href={`mailto:${contact.email}`}
                      className="text-sm text-blue-600 hover:text-blue-800"
                    >
                      {contact.email}
                    </a>
                  </div>
                </div>
              </div>
              
              <div>
                <Label className="text-sm font-medium text-gray-500">{t('Subject')}</Label>
                <p className="mt-1 text-sm text-gray-900">{contact.subject}</p>
              </div>
              
              <div>
                <Label className="text-sm font-medium text-gray-500">{t('Message')}</Label>
                <div className="mt-1 p-3 bg-gray-50 rounded-md">
                  <p className="text-sm text-gray-900 whitespace-pre-wrap">{contact.message}</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Status and Actions */}
        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <MessageSquare className="h-5 w-5" />
                {t('Status & Actions')}
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <Label className="text-sm font-medium text-gray-500">{t('Current Status')}</Label>
                <div className="mt-1">
                  <Badge className={getStatusColor(contact.status)}>
                    {t(contact.status.charAt(0).toUpperCase() + contact.status.slice(1))}
                  </Badge>
                </div>
              </div>
              
              <div>
                <Label htmlFor="status">{t('Update Status')}</Label>
                <Select value={status} onValueChange={setStatus}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="new">{t('New')}</SelectItem>
                    <SelectItem value="read">{t('Read')}</SelectItem>
                    <SelectItem value="replied">{t('Replied')}</SelectItem>
                    <SelectItem value="closed">{t('Closed')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              
              <div>
                <Label htmlFor="admin_notes">{t('Admin Notes')}</Label>
                <Textarea
                  id="admin_notes"
                  value={adminNotes}
                  onChange={(e) => setAdminNotes(e.target.value)}
                  placeholder={t('Add internal notes about this contact...')}
                  rows={4}
                />
              </div>
              
              <Button 
                onClick={handleStatusUpdate}
                disabled={isUpdating}
                className="w-full"
              >
                {isUpdating ? t('Updating...') : t('Update Status')}
              </Button>
            </CardContent>
          </Card>

          {/* Metadata */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Calendar className="h-5 w-5" />
                {t('Metadata')}
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div>
                <Label className="text-sm font-medium text-gray-500">{t('Created At')}</Label>
                <p className="mt-1 text-sm text-gray-900">
                  {window.appSettings?.formatDateTime(contact.created_at) || new Date(contact.created_at).toLocaleString()}
                </p>
              </div>
              
              {contact.read_at && (
                <div>
                  <Label className="text-sm font-medium text-gray-500">{t('Read At')}</Label>
                  <p className="mt-1 text-sm text-gray-900">
                    {window.appSettings?.formatDateTime(contact.read_at) || new Date(contact.read_at).toLocaleString()}
                  </p>
                </div>
              )}
              
              {contact.replied_at && (
                <div>
                  <Label className="text-sm font-medium text-gray-500">{t('Replied At')}</Label>
                  <p className="mt-1 text-sm text-gray-900">
                    {window.appSettings?.formatDateTime(contact.replied_at) || new Date(contact.replied_at).toLocaleString()}
                  </p>
                </div>
              )}
              
              <div>
                <Label className="text-sm font-medium text-gray-500">{t('Last Updated')}</Label>
                <p className="mt-1 text-sm text-gray-900">
                  {window.appSettings?.formatDateTime(contact.updated_at) || new Date(contact.updated_at).toLocaleString()}
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={contact.name}
        entityName="contact"
      />
    </PageTemplate>
  );
}