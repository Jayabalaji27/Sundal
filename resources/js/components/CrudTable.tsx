// components/CrudTable.tsx
import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { ChevronDown, ChevronUp, ChevronsUpDown, MoreHorizontal } from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import * as LucidIcons from 'lucide-react';
import { hasPermission } from '@/utils/authorization';
import { TableColumn, TableAction } from '@/types/crud';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface CrudTableProps {
  columns: TableColumn[];
  actions: TableAction[];
  data: any[];
  from: number;
  onAction: (action: string, row: any) => void;
  sortField?: string;
  sortDirection?: 'asc' | 'desc';
  onSort?: (field: string) => void;
  statusColors?: Record<string, string>;
  permissions: string[];
  entityPermissions?: {
    view: string;
    edit: string;
    delete: string;
  };
  showActionsAsIcons?: boolean;
  showActions?: boolean;
}

export function CrudTable({
  columns,
  actions,
  data,
  from,
  onAction,
  sortField,
  sortDirection,
  onSort,
  statusColors = {},
  permissions,
  entityPermissions,
  showActions = true,
}: CrudTableProps) {
  const { t } = useTranslation();
  const renderSortIcon = (column: TableColumn) => {
    if (!column.sortable) return null;

    if (sortField === column.key) {
      return sortDirection === 'asc' ?
        <ChevronUp className="ml-1 h-4 w-4" /> :
        <ChevronDown className="ml-1 h-4 w-4" />;
    }

    return <ChevronsUpDown className="ml-1 h-4 w-4 opacity-50" />;
  };

  const handleSort = (column: TableColumn) => {
    if (!column.sortable || !onSort) return;
    onSort(column.key);
  };
  const hasAnyActionPermission = actions.some((action) => {
    const permissionKey =
      action.requiredPermission ||
      (entityPermissions &&
        (action.action === 'view'
          ? entityPermissions.view
          : action.action === 'edit'
            ? entityPermissions.edit
            : action.action === 'delete'
              ? entityPermissions.delete
              : action.permission));

    return !permissionKey || hasPermission(permissions, permissionKey);
  });
  // An action the user may use on this row (permission and the action's own condition).
  const isActionVisible = (action: TableAction, row: any) => {
    const permissionKey = action.requiredPermission || (
      entityPermissions && (
        action.action === 'view'
          ? entityPermissions.view
          : action.action === 'edit'
            ? entityPermissions.edit
            : action.action === 'delete'
              ? entityPermissions.delete
              : action.permission
      )
    );
    if (permissionKey && !hasPermission(permissions, permissionKey)) return false;
    return !action.condition || action.condition(row);
  };

  const actionHref = (action: TableAction, row: any) => typeof action.href === 'function'
    ? action.href(row)
    : (action.href as string).replace(':id', row.id);

  const renderIconAction = (action: TableAction, row: any, index: number) => {
    const IconComponent = (LucidIcons as any)[action.icon] as React.ElementType;
    const button = (
      <Button
        variant="ghost"
        size="icon"
        className={action.className}
        aria-label={action.label}
        onClick={action.href ? undefined : () => onAction(action.action, row)}
      >
        <IconComponent />
      </Button>
    );

    return (
      <TooltipProvider key={index}>
        <Tooltip>
          <TooltipTrigger asChild>
            {action.href ? (
              <Link href={actionHref(action, row)} target={action.openInNewTab ? '_blank' : undefined}>{button}</Link>
            ) : button}
          </TooltipTrigger>
          <TooltipContent>
            <p>{action.label}</p>
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
    );
  };

  // Up to two actions show as icons. With more, the first (usually View) stays and the
  // rest - including Delete - move into a labelled "..." menu, so the column stays narrow.
  const renderActionButtons = (row: any) => {
    const visible = actions.filter(action => isActionVisible(action, row));
    const [first, ...rest] = visible;
    if (visible.length <= 2) {
      return <div className="flex items-center justify-end gap-1">{visible.map((action, index) => renderIconAction(action, row, index))}</div>;
    }

    return (
      <div className="flex items-center justify-end gap-1">
        {renderIconAction(first, row, 0)}
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" aria-label={t('More actions')}>
              <MoreHorizontal />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="min-w-40">
            {rest.map((action, index) => {
              const IconComponent = (LucidIcons as any)[action.icon] as React.ElementType;
              const content = (
                <>
                  <IconComponent />
                  {action.label}
                </>
              );
              return action.href ? (
                <DropdownMenuItem key={index} asChild>
                  <Link href={actionHref(action, row)} target={action.openInNewTab ? '_blank' : undefined}>{content}</Link>
                </DropdownMenuItem>
              ) : (
                <DropdownMenuItem
                  key={index}
                  variant={action.action === 'delete' ? 'destructive' : 'default'}
                  onClick={() => onAction(action.action, row)}
                >
                  {content}
                </DropdownMenuItem>
              );
            })}
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    );
  };

  // Helper function to get nested property value using dot notation
  const getNestedValue = (obj: any, path: string) => {
    if (!obj || !path) return null;

    const keys = path.split('.');
    return keys.reduce((acc, key) => {
      return acc && acc[key] !== undefined ? acc[key] : null;
    }, obj);
  };

  const renderCellContent = (row: any, col: TableColumn) => {
    // Get value using dot notation for nested properties
    const value = getNestedValue(row, col.key);

    // If column has custom render function, use it
    if (col.render) {
      return col.render(value, row);
    }

    // Handle different column types
    switch (col.type) {
      case 'badge':
        return (
          <Badge className={cn("capitalize", statusColors[value])}>
            {value}
          </Badge>
        );

      case 'image':
        if (!value) {
          return <div className="text-center text-ds-fg-placeholder">{t("No image")}</div>;
        }
        return (
          <div className="flex justify-center">
            <img
              src={value.startsWith && value.startsWith('http')
                ? value
                : `/storage/${value}`}
              alt={row.name || 'Image'}
              className={col.className || "h-16 w-20 rounded-md object-cover shadow-sm"}
              onError={(e) => {
                e.currentTarget.src = 'https://placehold.co/200x150?text=Image+Not+Found';
              }}
            />
          </div>
        );

      case 'date':
        return value ? <span className="text-sm">{new Date(value).toLocaleDateString()}</span> : <span>-</span>;

      case 'currency':
        return <span className="text-sm">{typeof value === 'number' ?
          value.toLocaleString('en-US', { style: 'currency', currency: 'USD' }) :
          value}</span>;

      case 'boolean':
        return <span className="text-sm">{value ? 'Yes' : 'No'}</span>;

      case 'link':
        if (!value) return <span>-</span>;

        const href = col.href
          ? (typeof col.href === 'function' ? col.href(row) : col.href.replace(':id', row.id))
          : '#';

        return (
          <Link
            href={href}
            className={col.linkClassName || "font-medium text-ds-fg-accent underline-offset-4 hover:underline"}
            target={col.openInNewTab ? '_blank' : undefined}
          >
            {value}
          </Link>
        );

      default:
        return <span className="text-sm">{value || '-'}</span>;
    }
  };

  return (
    <div className="overflow-hidden rounded-lg">
      {/* Fits the page: cells wrap rather than pushing the actions column off-screen. */}
      <Table className="w-full">
        <TableHeader>
          <TableRow>
            <TableHead scope="col" className="w-12">#</TableHead>
            {columns.map((column) => (
              <TableHead
                key={column.key}
                scope="col"
                className={column.className}
                aria-sort={column.sortable && sortField === column.key ? (sortDirection === 'asc' ? 'ascending' : 'descending') : undefined}
              >
                {/* Sortable headers are buttons so sorting works from the keyboard. */}
                {column.sortable && onSort ? (
                  <button
                    type="button"
                    onClick={() => handleSort(column)}
                    className="-mx-1 inline-flex cursor-pointer select-none items-center rounded-sm px-1 hover:text-ds-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ds-border-focus"
                  >
                    {column.label}
                    {renderSortIcon(column)}
                  </button>
                ) : (
                  <div className="flex items-center">{column.label}</div>
                )}
              </TableHead>
            ))}
            {/* <TableHead className="w-24 py-2.5 font-semibold text-right">{t("Actions")}</TableHead> */}
            {showActions && hasAnyActionPermission && <TableHead scope="col" className="w-24 text-right">{t('Actions')}</TableHead>}
          </TableRow>
        </TableHeader>
        <TableBody>
          {data.length > 0 ? (
            data.map((row, index) => (
              <TableRow key={row.id || index}>
                <TableCell className="text-ds-fg-muted tabular-nums">{from + index}</TableCell>
                {columns.map((col) => (
                  <TableCell
                    key={col.key}
                    className={col.className}
                  >
                    {renderCellContent(row, col)}
                  </TableCell>
                ))}
                {/* <TableCell className="py-2.5 text-right">
                  {renderActionButtons(row)}
                </TableCell> */}
                {showActions && hasAnyActionPermission && <TableCell className="py-1.5 text-right">{renderActionButtons(row)}</TableCell>}
              </TableRow>
            ))
          ) : (
            <TableRow>
              {/* <TableCell 
                colSpan={columns.length + 2} 
                className="h-24 text-center text-muted-foreground dark:text-gray-400"
              >
                {t("No results found.")}
              </TableCell> */}
              <TableCell
                colSpan={columns.length + (showActions && hasAnyActionPermission ? 2 : 1)}
                className="h-auto py-10 text-center"
              >
                <p className="text-sm font-medium text-ds-fg">{t('No results found')}</p>
                <p className="mt-1 text-sm text-ds-fg-muted">{t('Try adjusting your search or filters.')}</p>
              </TableCell>
            </TableRow>
          )}
        </TableBody>
      </Table>
    </div>
  );
}