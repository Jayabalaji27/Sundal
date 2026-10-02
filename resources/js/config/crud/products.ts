// config/crud/products.ts
import { CrudConfig } from '@/types/crud';
import { columnRenderers } from '@/utils/columnRenderers';
import { t } from '@/utils/i18n';

export const productsConfig: CrudConfig = {
  entity: {
    name: 'products',
    endpoint: route('products.index'),
    permissions: {
      view: 'view-products',
      create: 'create-products',
      edit: 'edit-products',
      delete: 'delete-products'
    }
  },
  table: {
    columns: [
      { key: 'name', label: t('Name'), sortable: true },
      { 
        key: 'price', 
        label: t('Price'), 
        sortable: true, 
        render: columnRenderers.price('USD')
      },
      { 
        key: 'category.name', 
        label: t('Category'),
        render: (value) => value || '-'
      },
      { 
        key: 'featured_image', 
        label: t('Image'), 
        render: columnRenderers.image('h-16 w-24 rounded-md object-cover shadow-sm')
      },
      { 
        key: 'created_at', 
        label: t('Created At'), 
        sortable: true, 
        render: columnRenderers.date()
      }
    ],
    actions: [
      { 
        label: t('View'), 
        icon: 'Eye', 
        action: 'view', 
        className: 'text-blue-500',
        requiredPermission: 'view-products'
      },
      { 
        label: t('Edit'), 
        icon: 'Edit', 
        action: 'edit', 
        className: 'text-amber-500',
        requiredPermission: 'edit-products'
      },
      { 
        label: t('Delete'), 
        icon: 'Trash2', 
        action: 'delete', 
        className: 'text-red-500',
        requiredPermission: 'delete-products'
      }
    ]
  },
  filters: [
    {
      key: 'category_id',
      label: t('Category'),
      type: 'select',
      options: [] // Will be populated dynamically from the backend
    }
  ],
  form: {
    fields: [
      { name: 'name', label: t('Name'), type: 'text', required: true },
      { 
        name: 'category_id', 
        label: t('Category'), 
        type: 'select',
        options: [] // Will be populated dynamically
      },
      { name: 'price', label: t('Price'), type: 'number', required: true },
      { name: 'description', label: t('Description'), type: 'textarea' },
      { 
        name: 'featured_image', 
        label: t('Featured Image'),
        type: 'media-picker',
        required: false
      }
    ],
    modalSize: '4xl'
  }
};