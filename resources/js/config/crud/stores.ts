// config/crud/stores.ts
import { CrudConfig } from '@/types/crud';
import { t } from '@/utils/i18n';

export const storesConfig: CrudConfig = {
  entity: 'store',
  entityPlural: 'stores',
  route: '/stores',
  permissions: {
    view: 'manage-stores',
    create: 'create-stores',
    edit: 'edit-stores',
    delete: 'delete-stores',
  },
  columns: [
    {
      key: 'user_name',
      label: t('User Name'),
      sortable: true,
    },
    {
      key: 'user_email',
      label: t('Email'),
      sortable: true,
    },
    {
      key: 'store_count',
      label: t('Store Count'),
      sortable: true,
    },
    {
      key: 'plan',
      label: t('Plan'),
      sortable: true,
    },
    {
      key: 'created_at',
      label: t('Created At'),
      sortable: true,
    },
    {
      key: 'is_active',
      label: t('Status'),
      sortable: true,
    },
  ],
  filters: [
    {
      key: 'search',
      label: t('Search'),
      type: 'text',
      placeholder: t('Search stores...'),
    },
  ],
  actions: [
    {
      key: 'edit',
      label: t('Edit'),
      icon: 'Edit',
      permission: 'edit-stores',
    },
    {
      key: 'delete',
      label: t('Delete'),
      icon: 'Trash2',
      permission: 'delete-stores',
    },
    {
      key: 'store_links',
      label: t('Store Links'),
      icon: 'Link',
      permission: 'super-admin',
    },
    {
      key: 'toggle_login',
      label: t('Enable/Disable Login'),
      icon: 'Lock',
      permission: 'super-admin',
    },
    {
      key: 'upgrade_plan',
      label: t('Upgrade Plan'),
      icon: 'ArrowUp',
      permission: 'super-admin',
    },
    {
      key: 'reset_password',
      label: t('Reset Password'),
      icon: 'Key',
      permission: 'super-admin',
    },
    {
      key: 'login_as_admin',
      label: t('Login as Admin'),
      icon: 'UserCheck',
      permission: 'super-admin',
    },
  ],
  form: {
    fields: [
      {
        key: 'store_name',
        label: t('Store Name'),
        type: 'text',
        required: true,
      },
      {
        key: 'name',
        label: t('User Name'),
        type: 'text',
        required: true,
        conditional: (mode) => mode === 'create',
      },
      {
        key: 'email',
        label: t('Email'),
        type: 'email',
        required: true,
        conditional: (mode) => mode === 'create',
      },
      {
        key: 'password_switch',
        label: t('Set Password'),
        type: 'switch',
        required: false,
        conditional: (mode) => mode === 'create',
      },
      {
        key: 'password',
        label: t('Password'),
        type: 'password',
        required: false,
        conditional: (mode, data) => mode === 'create' && data?.password_switch,
      },
      {
        key: 'is_active',
        label: t('Status'),
        type: 'switch',
        required: false,
      },
    ],
  },
};