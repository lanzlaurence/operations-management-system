import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { useFormatters } from '@/hooks/use-formatters';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-react';
import ClickableCode from '@/components/ui/clickable-code';
import { DataTable } from '@/components/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { usePage } from '@inertiajs/react';
import type { PurchaseHistoryItem, StockByLocation, SharedData } from '@/types';

type Props = {
    material: { id: number; code: string; name: string; uom: string | null };
    purchaseHistory: PurchaseHistoryItem[];
    stockByLocation: StockByLocation[];
};

export default function PurchaseHistory({ material, purchaseHistory, stockByLocation }: Props) {
    const { formatAmount, formatDecimal, formatDate } = useFormatters();
    const { preferences } = usePage<SharedData>().props;

    const columns: ColumnDef<PurchaseHistoryItem>[] = [
        {
            accessorKey: 'po_code',
            header: 'PO Code',
            size: 150,
            cell: ({ row }) => <ClickableCode href={`/purchase-orders/${row.original.po_id}`} value={row.original.po_code} />,
        },
        {
            accessorKey: 'vendor_code',
            header: 'Vendor',
            size: 200,
            filterFn: 'multiField' as any,
            cell: ({ row }) => (
                <div>
                    <ClickableCode href={`/vendors/${row.original.vendor_id}`} value={row.original.vendor_code} />
                    <p className="text-xs text-muted-foreground">{row.original.vendor_name}</p>
                </div>
            ),
        },
        {
            accessorKey: 'vendor_name',
            header: 'Vendor Name',
            size: 0,
            meta: { hidden: true },
        },
        {
            accessorKey: 'order_date',
            header: 'Order Date',
            size: 130,
            accessorFn: (row) => formatDate(row.order_date),
            cell: ({ row }) => formatDate(row.original.order_date),
        },
        {
            accessorKey: 'discount_amount',
            header: 'Discount',
            size: 120,
            cell: ({ row }) => <span className="font-mono">{formatAmount(row.original.discount_amount)}</span>,
        },
        {
            accessorKey: 'unit_cost_after_discount',
            header: 'Unit Cost After Disc.',
            size: 170,
            cell: ({ row }) => <span className="font-mono">{formatAmount(row.original.unit_cost_after_discount)}</span>,
        },
        {
            accessorKey: 'qty_ordered',
            header: 'Qty Ordered',
            size: 120,
            cell: ({ row }) => <span className="font-mono">{formatDecimal(row.original.qty_ordered)}</span>,
        },
        {
            accessorKey: 'net_cost',
            header: 'Net Cost',
            size: 130,
            cell: ({ row }) => <span className="font-mono">{formatAmount(row.original.net_cost)}</span>,
        },
    ];

    const stockColumns: ColumnDef<StockByLocation>[] = [
        {
            accessorKey: 'location_name',
            header: 'Location',
            size: 200,
            cell: ({ row }) => row.original.location_name,
        },
        {
            accessorKey: 'quantity',
            header: 'Quantity',
            size: 130,
            cell: ({ row }) => <span className="font-mono">{formatDecimal(row.original.quantity)}</span>,
        },
    ];

    return (
        <>
            <Head title={`Purchase History — ${material.name}`} />
            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Purchase History</h1>
                        <p className="text-sm text-muted-foreground">{material.code} — {material.name}</p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/materials"><ArrowLeft className="mr-2 h-4 w-4" />Back</Link>
                    </Button>
                </div>

                <div className="space-y-2">
                    <h3 className="font-semibold">Purchase Orders</h3>
                    <DataTable
                        columns={columns}
                        data={purchaseHistory}
                        exportFileName={`purchase-history-${material.code}`}
                        timezone={preferences.timezone}
                        initialColumnVisibility={{ vendor_name: false }}
                        storageKey="purchase-history"
                    />
                </div>

                <div className="space-y-2">
                    <h3 className="font-semibold">Stock by Location</h3>
                    <DataTable
                        columns={stockColumns}
                        data={stockByLocation}
                        exportFileName={`stock-by-location-${material.code}`}
                        timezone={preferences.timezone}
                        storageKey="purchase-history-stock"
                    />
                </div>
            </div>
        </>
    );
}

PurchaseHistory.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Materials', href: '/materials' },
        { title: 'Purchase History', href: '#' },
    ]}>{page}</AppLayout>
);
