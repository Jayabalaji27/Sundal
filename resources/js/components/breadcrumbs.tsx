import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/react';
import { Fragment } from 'react';

export function Breadcrumbs({ items }: { items: Array<{ label: string; href?: string }> }) {
    return (
        <>
            {items && items.length > 0 && (
                // One line that truncates instead of wrapping into the header controls;
                // below lg only the current page is shown.
                <Breadcrumb className="min-w-0">
                    <BreadcrumbList className="min-w-0 flex-nowrap">
                        {items.map((item, index) => {
                            const isLast = index === items.length - 1;
                            const label = <span className="block max-w-[14rem] truncate" title={item.label}>{item.label}</span>;
                            return (
                                <Fragment key={index}>
                                    <BreadcrumbItem className={isLast ? 'min-w-0' : 'hidden min-w-0 lg:inline-flex'}>
                                        {isLast || !item.href ? (
                                            <BreadcrumbPage className="min-w-0">{label}</BreadcrumbPage>
                                        ) : (
                                            <BreadcrumbLink asChild>
                                                <Link href={item.href} className="min-w-0">{label}</Link>
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && <BreadcrumbSeparator className="hidden lg:block" />}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}
