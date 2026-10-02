import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Plus, Edit, Trash2 } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { SettingsSection } from '@/components/settings-section';
import { Card, CardContent } from '@/components/ui/card';


interface Tax {
  id: number;
  name: string;
  rate: number;
}

interface TaxSettingsProps {
  taxes?: Tax[];
}

export default function TaxSettings({ taxes = [] }: TaxSettingsProps) {
  const { t } = useTranslation();
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [editingTax, setEditingTax] = useState<Tax | null>(null);
  const [deletingTax, setDeletingTax] = useState<Tax | null>(null);

  const deleteForm = useForm();


  const createForm = useForm({
    name: '',
    rate: '',
  });

  const editForm = useForm({
    name: '',
    rate: '',
  });

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    createForm.post(route('taxes.store'), {
        preserveScroll: true,
      onSuccess: (page) => {
        setIsCreateOpen(false);
        createForm.reset();
const successMessage = page.props.flash?.success;
            const errorMessage = page.props.flash?.error;

            if (successMessage) {
                toast.success(successMessage);
            } else if (errorMessage) {
                toast.error(errorMessage);
            }
      },
      onError: () => {

      },
    });
  };

  const handleEdit = (tax: Tax) => {
    setEditingTax(tax);
    editForm.setData({
      name: tax.name,
      rate: tax.rate.toString(),
    });
    setIsEditOpen(true);
  };

  const handleUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingTax) return;

    editForm.put(route('taxes.update', editingTax.id), {
        preserveScroll: true,
      onSuccess: (page) => {
        setIsEditOpen(false);
        setEditingTax(null);
        editForm.reset();
        const successMessage = page.props.flash?.success;
            const errorMessage = page.props.flash?.error;

            if (successMessage) {
                toast.success(successMessage);
            } else if (errorMessage) {
                toast.error(errorMessage);
            }

      },
      onError: () => {

      },
    });
  };

  const handleDelete = (tax: Tax) => {
    setDeletingTax(tax);
    setIsDeleteOpen(true);
  };

  const confirmDelete = () => {
    if (!deletingTax) return;

    deleteForm.delete(route('taxes.destroy', deletingTax.id), {
        preserveScroll: true,
      onSuccess: (page) => {
        setIsDeleteOpen(false);
        setDeletingTax(null);
        const successMessage = page.props.flash?.success;
            const errorMessage = page.props.flash?.error;

            if (successMessage) {
                toast.success(successMessage);
            } else if (errorMessage) {
                toast.error(errorMessage);
            }
      },
      onError: () => {
        setIsDeleteOpen(false);
        setDeletingTax(null);
      },
    });
  };

  return (
    <SettingsSection
      title={t('Tax Settings')}
      description={t('Manage tax rates for your workspace')}
      action={
        <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
          <DialogTrigger asChild>
            <Button size="sm">
              <Plus className="h-4 w-4 mr-2" />
              {t('Add Tax')}
            </Button>
          </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>{t('Create Tax')}</DialogTitle>
                <DialogDescription>
                  {t('Add a new tax rate to your workspace.')}
                </DialogDescription>
              </DialogHeader>
              <form onSubmit={handleCreate}>
                <div className="grid gap-4 pb-4">
                  <div className="grid gap-2">
                    <Label required htmlFor="name">{t('Tax Name')}</Label>
                    <Input
                      id="name"
                      value={createForm.data.name}
                      onChange={(e) => createForm.setData('name', e.target.value)}
                      placeholder={t('e.g., VAT, GST, Sales Tax')}
                      required
                    />
                    {createForm.errors.name && (
                      <p className="text-sm text-red-600">{createForm.errors.name}</p>
                    )}
                  </div>
                  <div className="grid gap-2">
                    <Label required htmlFor="rate">{t('Tax Rate (%)')}</Label>
                    <Input
                      id="rate"
                      type="number"
                      step="0.01"
                      min="0"
                      max="100"
                      value={createForm.data.rate}
                      onChange={(e) => createForm.setData('rate', e.target.value)}
                      placeholder={t('e.g., 18.00')}
                      required
                    />
                    {createForm.errors.rate && (
                      <p className="text-sm text-red-600">{createForm.errors.rate}</p>
                    )}
                  </div>
                </div>
                <DialogFooter>
                  <Button type="button" variant="outline" onClick={() => setIsCreateOpen(false)}>
                    {t('Cancel')}
                  </Button>
                  <Button type="submit">
                    {t('Create')}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        }
      >
          <div className="bg-white rounded-lg shadow overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow className="bg-[#F0F0F1] dark:bg-gray-800 border-b hover:bg-[#F0F0F1] dark:hover:bg-gray-800">
                <TableHead className="w-12 py-2.5 font-semibold">#</TableHead>
                <TableHead className="py-2.5 font-semibold">{t('Name')}</TableHead>
                <TableHead className="py-2.5 font-semibold">{t('Rate')}</TableHead>
                <TableHead className="py-2.5 font-semibold text-right">{t('Actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {taxes.length > 0 ? taxes.map((tax, index) => (
                <TableRow key={tax.id} className="hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900 border-b">
                  <TableCell className="py-2.5 font-medium">{index + 1}</TableCell>
                  <TableCell className="py-2.5"><span className="text-sm font-medium">{tax.name}</span></TableCell>
                  <TableCell className="py-2.5">
                    <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                      {tax.rate}%
                    </span>
                  </TableCell>
                  <TableCell className="py-2.5 text-right">
                    <div className="flex items-center justify-end space-x-2">
                      <TooltipProvider>
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-8 w-8 text-amber-500 hover:text-amber-700" onClick={() => handleEdit(tax)}>
                              <Edit className="h-4 w-4" />
                            </Button>
                          </TooltipTrigger>
                          <TooltipContent><p>{t('Edit')}</p></TooltipContent>
                        </Tooltip>
                      </TooltipProvider>
                      <TooltipProvider>
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-8 w-8 text-red-500 hover:text-red-700" onClick={() => handleDelete(tax)}>
                              <Trash2 className="h-4 w-4" />
                            </Button>
                          </TooltipTrigger>
                          <TooltipContent><p>{t('Delete')}</p></TooltipContent>
                        </Tooltip>
                      </TooltipProvider>
                    </div>
                  </TableCell>
                </TableRow>
              )) : (
                <TableRow>
                  <TableCell colSpan={4} className="h-24 text-center text-muted-foreground">
                    {t('No results found.')}
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </div>

        <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>{t('Edit Tax')}</DialogTitle>
              <DialogDescription>
                {t('Update the tax rate information.')}
              </DialogDescription>
            </DialogHeader>
            <form onSubmit={handleUpdate}>
              <div className="grid gap-4 pb-4">
                <div className="grid gap-2">
                  <Label required htmlFor="edit-name">{t('Tax Name')}</Label>
                  <Input
                    id="edit-name"
                    value={editForm.data.name}
                    onChange={(e) => editForm.setData('name', e.target.value)}
                    placeholder={t('e.g., VAT, GST, Sales Tax')}
                    required
                  />
                  {editForm.errors.name && (
                    <p className="text-sm text-red-600">{editForm.errors.name}</p>
                  )}
                </div>
                <div className="grid gap-2">
                  <Label required htmlFor="edit-rate">{t('Tax Rate (%)')}</Label>
                  <Input
                    id="edit-rate"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    value={editForm.data.rate}
                    onChange={(e) => editForm.setData('rate', e.target.value)}
                    placeholder={t('e.g., 18.00')}
                    required
                  />
                  {editForm.errors.rate && (
                    <p className="text-sm text-red-600">{editForm.errors.rate}</p>
                  )}
                </div>
              </div>
              <DialogFooter>
                <Button type="button" variant="outline" onClick={() => setIsEditOpen(false)}>
                  {t('Cancel')}
                </Button>
                <Button type="submit">
                  {t('Update')}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        <CrudDeleteModal
          isOpen={isDeleteOpen}
          onClose={() => setIsDeleteOpen(false)}
          onConfirm={confirmDelete}
          itemName={deletingTax?.name || ''}
          entityName={t('Tax')}
        />

      </SettingsSection>
  );
}
