import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { usePermissions } from '@/hooks/use-permissions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Edit, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import type { CurrencyData, Currency, SharedData } from '@/types';

export default function Index({ currencies }: CurrencyData) {
    const { hasPermission } = usePermissions();
    const { preferences } = usePage<SharedData>().props;
    const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; id: number; name: string }>({
        open: false, id: 0, name: '',
    });

    const handleDeleteConfirm = () => {
        router.delete(`/currencies/${deleteDialog.id}`);
        setDeleteDialog({ open: false, id: 0, name: '' });
    };

    const columns: ColumnDef<Currency>[] = [
        {
            accessorKey: 'code',
            header: 'Code',
            size: 100,
            cell: ({ row }) => <span className="font-medium">{row.original.code}</span>,
        },
        {
            accessorKey: 'name',
            header: 'Name',
            size: 180,
            cell: ({ row }) => row.original.name,
        },
        {
            accessorKey: 'symbol',
            header: 'Symbol',
            size: 90,
            cell: ({ row }) => <span className="font-mono">{row.original.symbol}</span>,
        },
        {
            accessorKey: 'exchange_rate',
            header: 'Exchange Rate',
            size: 140,
            cell: ({ row }) => (
                <span className="font-mono text-sm">{Number(row.original.exchange_rate).toFixed(6)}</span>
            ),
        },
        {
            accessorKey: 'is_active',
            header: 'Status',
            size: 100,
            accessorFn: (row) => row.is_active ? 'Active' : 'Inactive',
            cell: ({ row }) => (
                <Badge variant={row.original.is_active ? 'default' : 'secondary'}>
                    {row.original.is_active ? 'Active' : 'Inactive'}
                </Badge>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            enableSorting: false,
            enableColumnFilter: false,
            size: 130,
            cell: ({ row }) => (
                <div className="flex justify-end gap-1">
                    {hasPermission('currency-edit') && (
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={`/currencies/${row.original.id}/edit`} className="flex flex-col items-center gap-1 h-auto py-1 w-14">
                                <Edit className="h-4 w-4" />
                                <span className="text-[10px] leading-none">Edit</span>
                            </Link>
                        </Button>
                    )}
                    {hasPermission('currency-delete') && (
                        <Button variant="ghost" size="sm"
                            onClick={() => setDeleteDialog({ open: true, id: row.original.id, name: row.original.name })}
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
            <Head title="Currencies" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Currencies</h1>
                    {hasPermission('currency-create') && (
                        <Button asChild size="sm">
                            <Link href="/currencies/create"><Plus className="mr-2 h-4 w-4" />Add Currency</Link>
                        </Button>
                    )}
                </div>
                <DataTable
                    columns={columns}
                    data={currencies}
                    exportFileName="currencies"
                    timezone={preferences.timezone}
                    storageKey="currencies"
                />
            </div>

            <AlertDialog open={deleteDialog.open} onOpenChange={(open) => setDeleteDialog({ ...deleteDialog, open })}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete <span className="font-semibold">{deleteDialog.name}</span>. This action cannot be undone.
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
        { title: 'Currencies', href: '/currencies' },
    ]}>{page}</AppLayout>
);
