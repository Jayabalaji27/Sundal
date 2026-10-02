import React, { useState } from 'react';
import { useForm, usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { Switch } from '@/components/ui/switch';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Plus, Edit, Trash2, Eye, EyeOff, Search } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { CrudTable } from '@/components/CrudTable';
import { toast } from '@/components/custom-toast';
import { Toaster } from '@/components/ui/toaster';
import { useTranslation } from 'react-i18next';
import { PageForm } from './components/PageForm';
import { EnhancedDeleteModal } from '@/components/EnhancedDeleteModal';

interface CustomPage {
  id: number;
  title: string;
  slug: string;
  content: string;
  meta_title?: string;
  meta_description?: string;
  is_active: boolean;
  sort_order: number;
}

interface PageProps {
  pages: CustomPage[];
  flash?: {
    success?: string;
    error?: string;
  };
}

export default function CustomPagesIndex() {
  const { t } = useTranslation();
  const { pages, flash, filters: pageFilters = {} } = usePage<PageProps>().props;
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingPage, setEditingPage] = useState<CustomPage | null>(null);
  const [deletingPage, setDeletingPage] = useState<CustomPage | null>(null);
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');

  const { data, setData, post, put, processing, errors, reset } = useForm({
    title: '',
    content: '',
    meta_title: '',
    meta_description: '',
    is_active: true,
    sort_order: 0
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    if (editingPage) {
      put(route('landing-page.custom-pages.update', editingPage.id), {
        onSuccess: () => {
          setEditingPage(null);
          reset();
          toast.success(t('Page updated successfully!'));
        },
        onError: () => {
          toast.error(t('Failed to update page. Please try again.'));
        }
      });
    } else {
      post(route('landing-page.custom-pages.store'), {
        onSuccess: () => {
          setIsCreateOpen(false);
          reset();
          toast.success(t('Page created successfully!'));
        },
        onError: () => {
          toast.error(t('Failed to create page. Please try again.'));
        }
      });
    }
  };

  const handleEdit = (page: CustomPage) => {
    setData({
      title: page.title,
      content: page.content,
      meta_title: page.meta_title || '',
      meta_description: page.meta_description || '',
      is_active: page.is_active,
      sort_order: page.sort_order || 0
    });
    setEditingPage(page);
  };

  const handleDelete = (page: CustomPage) => {
    setDeletingPage(page);
  };

  const confirmDelete = () => {
    if (deletingPage) {
      router.delete(route('landing-page.custom-pages.destroy', deletingPage.id), {
        onSuccess: () => {
          toast.success(t('Page deleted successfully!'));
          setDeletingPage(null);
        },
        onError: () => {
          toast.error(t('Failed to delete page. Please try again.'));
        }
      });
    }
  };

  const resetForm = () => {
    reset();
    setEditingPage(null);
    setIsCreateOpen(false);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    const params: any = { page: 1 };
    if (searchTerm) {
      params.search = searchTerm;
    }
    router.get(route('landing-page.custom-pages.index'), params, { preserveState: true, preserveScroll: true });
  };

  const handleAction = (action: string, item: CustomPage) => {
    if (action === 'edit') {
      handleEdit(item);
    } else if (action === 'delete') {
      handleDelete(item);
    }
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'asc' ? 'desc' : 'asc';
    const params: any = { 
      sort_field: field, 
      sort_direction: direction, 
      page: 1 
    };
    if (searchTerm) {
      params.search = searchTerm;
    }
    router.get(route('landing-page.custom-pages.index'), params, { preserveState: true, preserveScroll: true });
  };

  const columns = [
    { 
      key: 'title', 
      label: t('Title'), 
      sortable: true,
      render: (value: string) => (
        <div className="font-medium">{value}</div>
      )
    },
    { 
      key: 'content', 
      label: t('Content'),
      render: (value: string) => {
        const strippedContent = value.replace(/<[^>]*>/g, '');
        return (
          <div className="max-w-xs truncate" title={strippedContent}>
            {strippedContent.substring(0, 100)}...
          </div>
        );
      }
    },
    { 
      key: 'is_active', 
      label: t('Status'),
      render: (value: boolean) => (
        <div className="flex items-center space-x-1">
          {value ? (
            <><Eye className="w-4 h-4 text-green-600" /><span className="text-green-600">{t('Active')}</span></>
          ) : (
            <><EyeOff className="w-4 h-4 text-gray-400" /><span className="text-gray-400">{t('Inactive')}</span></>
          )}
        </div>
      )
    },
    { 
      key: 'created_at', 
      label: t('Created'), 
      sortable: true,
      render: (value: string) => new Date(value).toLocaleDateString()
    }
  ];

  const actions = [
    { 
      label: t('Edit'), 
      icon: 'Edit', 
      action: 'edit', 
      className: 'text-amber-500'
    },
    { 
      label: t('Delete'), 
      icon: 'Trash2', 
      action: 'delete', 
      className: 'text-red-500'
    }
  ];

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Landing Page'), href: route('landing-page.settings') },
    { title: t('Custom Pages') }
  ];

  return (
    <PageTemplate 
      title={t('Custom Pages')} 
      url="/landing-page/custom-pages"
      breadcrumbs={breadcrumbs}
      actions={[
        {
          label: t('Add Page'),
          icon: <Plus className="w-4 h-4 mr-2" />,
          variant: 'default',
          onClick: () => setIsCreateOpen(true)
        }
      ]}
      noPadding
    >
      {/* Search and filters section */}
      <div className="bg-white rounded-lg shadow mb-4">
        <div className="p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <form onSubmit={handleSearch} className="flex gap-2">
                <div className="relative w-64">
                  <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input
                    placeholder={t('Search pages...')}
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="w-full pl-9"
                  />
                </div>
                <Button type="submit" size="sm">
                  <Search className="h-4 w-4 mr-1.5" />
                  {t('Search')}
                </Button>
              </form>
              
              {searchTerm && (
                <Button 
                  variant="outline"
                  size="sm" 
                  className="h-8 px-2 py-1"
                  onClick={() => {
                    setSearchTerm('');
                    router.get(route('landing-page.custom-pages.index'), {}, { preserveState: true, preserveScroll: true });
                  }}
                >
                  {t('Clear')}
                </Button>
              )}
            </div>
            
            <div className="flex items-center gap-2">
              <Label className="text-xs text-muted-foreground">{t('Per Page')}:</Label>
              <Select 
                value={pageFilters.per_page?.toString() || "20"} 
                onValueChange={(value) => {
                  const params: any = { page: 1, per_page: parseInt(value) };
                  if (searchTerm) params.search = searchTerm;
                  router.get(route('landing-page.custom-pages.index'), params, { preserveState: false, preserveScroll: false });
                }}
              >
                <SelectTrigger className="w-16 h-8">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="10">10</SelectItem>
                  <SelectItem value="20">20</SelectItem>
                  <SelectItem value="50">50</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </div>
      </div>

      {/* Table section */}
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={pages?.data || pages || []}
          from={pages?.from || 1}
          onAction={handleAction}
          sortField={pageFilters.sort_field}
          sortDirection={pageFilters.sort_direction}
          onSort={handleSort}
        />

        {/* Pagination section */}
        {pages?.links && (
          <div className="mt-6 bg-white p-4 rounded-lg shadow flex items-center justify-between">
            <div className="text-sm text-muted-foreground">
              {t('Showing')} <span className="font-medium">{pages?.from || 0}</span> {t('to')} <span className="font-medium">{pages?.to || 0}</span> {t('of')} <span className="font-medium">{pages?.total || 0}</span> {t('pages')}
            </div>
            
            <div className="flex gap-1">
              {pages?.links?.map((link: any, i: number) => {
                const isTextLink = link.label === "&laquo; Previous" || link.label === "Next &raquo;";
                const label = link.label.replace("&laquo; ", "").replace(" &raquo;", "");
                
                return (
                  <Button
                    key={i}
                    variant={link.active ? 'default' : 'outline'}
                    size={isTextLink ? "sm" : "icon"}
                    className={isTextLink ? "px-3" : "h-8 w-8"}
                    disabled={!link.url}
                    onClick={() => link.url && router.get(link.url)}
                  >
                    {isTextLink ? label : <span dangerouslySetInnerHTML={{ __html: link.label }} />}
                  </Button>
                );
              })}
            </div>
          </div>
        )}
      </div>

      {/* Edit Dialog */}
      <Dialog open={!!editingPage} onOpenChange={() => setEditingPage(null)}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t('Edit Page')}</DialogTitle>
          </DialogHeader>
          <PageForm
            data={data}
            setData={setData}
            errors={errors}
            processing={processing}
            onSubmit={handleSubmit}
            onCancel={resetForm}
            isEditing={true}
          />
        </DialogContent>
      </Dialog>

      {/* Create Dialog */}
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t('Create Custom Page')}</DialogTitle>
          </DialogHeader>
          <PageForm
            data={data}
            setData={setData}
            errors={errors}
            processing={processing}
            onSubmit={handleSubmit}
            onCancel={resetForm}
            isEditing={false}
          />
        </DialogContent>
      </Dialog>

      <EnhancedDeleteModal
        isOpen={!!deletingPage}
        onClose={() => setDeletingPage(null)}
        onConfirm={confirmDelete}
        itemName={deletingPage?.title || ''}
        entityName={t('Page')}
        warningMessage={t('This will permanently remove the page from your landing site.')}
      />

      <Toaster />
    </PageTemplate>
  );
}