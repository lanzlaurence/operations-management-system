import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useFormatters } from '@/hooks/use-formatters';
import { usePermissions } from '@/hooks/use-permissions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Edit, Eye, Plus, ShoppingBag, ShoppingCart, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import type { MaterialData, Material, SharedData } from '@/types';

export default function Index({ materials }: MaterialData) {
    const { hasPermission } = usePermissions();
    const { formatAmount } = useFormatters();
    const { preferences } = usePage<SharedData>().props;
    const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; id: number; code: string }>({
        open: false, id: 0, code: '',
    });

    const handleDeleteConfirm = () => {
        router.delete(`/materials/${deleteDialog.id}`);
        setDeleteDialog({ open: false, id: 0, code: '' });
    };

    const columns: ColumnDef<Material>[] = [
        {
            accessorKey: 'sku',
            header: 'SKU',
            size: 100,
            cell: ({ row }) => <span className="font-mono text-sm text-muted-foreground">{row.original.sku || '-'}</span>,
        },
        {
            accessorKey: 'code',
            header: 'Material',
            size: 200,
            filterFn: 'multiField' as any,
            cell: ({ row }) => (
                <div>
                    <p className="font-mono text-sm font-medium">{row.original.code}</p>
                    <p className="text-xs text-muted-foreground">{row.original.name}</p>
                </div>
            ),
        },
        {
            accessorKey: 'description',
            header: 'Description',
            size: 200,
            cell: ({ row }) => (
                <span className="text-muted-foreground">{row.original.description || '-'}</span>
            ),
        },
        {
            accessorKey: 'category',
            header: 'Category',
            size: 130,
            accessorFn: (row) => row.category?.name ?? '',
            cell: ({ row }) => <span className="text-muted-foreground">{row.original.category?.name || '-'}</span>,
        },
        {
            accessorKey: 'brand',
            header: 'Brand',
            size: 120,
            accessorFn: (row) => row.brand?.name ?? '',
            cell: ({ row }) => <span className="text-muted-foreground">{row.original.brand?.name || '-'}</span>,
        },
        {
            accessorKey: 'uom',
            header: 'UOM',
            size: 90,
            accessorFn: (row) => row.uom?.acronym ?? '',
            cell: ({ row }) => <span className="text-muted-foreground">{row.original.uom?.acronym || '-'}</span>,
        },
        {
            accessorKey: 'unit_cost',
            header: 'Unit Cost',
            size: 120,
            cell: ({ row }) => <span className="font-mono">{formatAmount(Number(row.original.unit_cost))}</span>,
        },
        {
            accessorKey: 'unit_price',
            header: 'Unit Price',
            size: 120,
            cell: ({ row }) => <span className="font-mono">{formatAmount(Number(row.original.unit_price))}</span>,
        },
        {
            accessorKey: 'avg_unit_cost',
            header: 'Avg Cost',
            size: 120,
            cell: ({ row }) => <span className="font-mono">{formatAmount(Number(row.original.avg_unit_cost))}</span>,
        },
        {
            accessorKey: 'avg_unit_price',
            header: 'Avg Price',
            size: 120,
            cell: ({ row }) => <span className="font-mono">{formatAmount(Number(row.original.avg_unit_price))}</span>,
        },
        {
            accessorKey: 'status',
            header: 'Status',
            size: 100,
            cell: ({ row }) => (
                <Badge variant={row.original.status === 'active' ? 'default' : 'secondary'}>
                    {row.original.status}
                </Badge>
            ),
        },
        {
            id: 'history',
            header: 'History',
            enableSorting: false,
            enableColumnFilter: false,
            size: 130,
            cell: ({ row }) => (
                <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={`/materials/${row.original.id}/purchase-history`} className="flex flex-col items-center gap-1 h-auto py-1 w-14">
                            <ShoppingCart className="h-4 w-4 text-blue-600" />
                            <span className="text-[10px] leading-none">Purchase</span>
                        </Link>
                    </Button>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={`/materials/${row.original.id}/sales-history`} className="flex flex-col items-center gap-1 h-auto py-1 w-14">
                            <ShoppingBag className="h-4 w-4 text-green-600" />
                            <span className="text-[10px] leading-none">Sales</span>
                        </Link>
                    </Button>
                </div>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            enableSorting: false,
            enableColumnFilter: false,
            size: 120,
            cell: ({ row }) => (
                <div className="flex justify-end gap-1">
                    {hasPermission('material-view') && (
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={`/materials/${row.original.id}`} className="flex flex-col items-center gap-1 h-auto py-1 w-14">
                                <Eye className="h-4 w-4" />
                                <span className="text-[10px] leading-none">View</span>
                            </Link>
                        </Button>
                    )}
                    {hasPermission('material-edit') && (
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={`/materials/${row.original.id}/edit`} className="flex flex-col items-center gap-1 h-auto py-1 w-14">
                                <Edit className="h-4 w-4" />
                                <span className="text-[10px] leading-none">Edit</span>
                            </Link>
                        </Button>
                    )}
                    {hasPermission('material-delete') && (
                        <Button variant="ghost" size="sm"
                            onClick={() => setDeleteDialog({ open: true, id: row.original.id, code: row.original.code })}
                            className="flex flex-col items-center gap-1 h-auto py-1 w-14">
                            <Trash2 className="h-4 w-4 text-red-600" />
                            <span className="text-[10px] leading-none text-red-600">Delete</span>
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Materials" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Materials</h1>
                    {hasPermission('material-create') && (
                        <Button asChild size="sm">
                            <Link href="/materials/create">
                                <Plus className="mr-2 h-4 w-4" />Add Material
                            </Link>
                        </Button>
                    )}
                </div>
                <DataTable
                    columns={columns}
                    data={materials}
                    exportFileName="materials"
                    timezone={preferences.timezone}
                    initialColumnVisibility={{ description: false }}
                    storageKey="materials"
                />
            </div>

            <AlertDialog open={deleteDialog.open} onOpenChange={(open) => setDeleteDialog({ ...deleteDialog, open })}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete material <span className="font-semibold">{deleteDialog.code}</span>. This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDeleteConfirm} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">Delete</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

Index.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Materials', href: '/materials' },
    ]}>{page}</AppLayout>
);
