import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { useFormatters } from '@/hooks/use-formatters';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-react';
import ClickableCode from '@/components/ui/clickable-code';
import { DataTable } from '@/components/data-table';
import { ColumnDef } from '@tanstack/react-table';
import { usePage } from '@inertiajs/react';
import type { SalesHistoryItem, StockByLocation, SharedData } from '@/types';

type Props = {
    material: { id: number; code: string; name: string; uom: string | null };
    salesHistory: SalesHistoryItem[];
    stockByLocation: StockByLocation[];
};

export default function SalesHistory({ material, salesHistory, stockByLocation }: Props) {
    const { formatAmount, formatDecimal, formatDate } = useFormatters();
    const { preferences } = usePage<SharedData>().props;

    const columns: ColumnDef<SalesHistoryItem>[] = [
        {
            accessorKey: 'so_code',
            header: 'SO Code',
            size: 150,
            cell: ({ row }) => <ClickableCode href={`/sales-orders/${row.original.so_id}`} value={row.original.so_code} />,
        },
        {
            accessorKey: 'customer_code',
            header: 'Customer',
            size: 200,
            filterFn: 'multiField' as any,
            cell: ({ row }) => (
                <div>
                    <ClickableCode href={`/customers/${row.original.customer_id}`} value={row.original.customer_code} />
                    <p className="text-xs text-muted-foreground">{row.original.customer_name}</p>
                </div>
            ),
        },
        {
            accessorKey: 'customer_name',
            header: 'Customer Name',
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
            accessorKey: 'unit_price_after_discount',
            header: 'Unit Price After Disc.',
            size: 170,
            cell: ({ row }) => <span className="font-mono">{formatAmount(row.original.unit_price_after_discount)}</span>,
        },
        {
            accessorKey: 'qty_ordered',
            header: 'Qty Ordered',
            size: 120,
            cell: ({ row }) => <span className="font-mono">{formatDecimal(row.original.qty_ordered)}</span>,
        },
        {
            accessorKey: 'net_price',
            header: 'Net Price',
            size: 130,
            cell: ({ row }) => <span className="font-mono">{formatAmount(row.original.net_price)}</span>,
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
            <Head title={`Sales History — ${material.name}`} />
            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Sales History</h1>
                        <p className="text-sm text-muted-foreground">{material.code} — {material.name}</p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/materials"><ArrowLeft className="mr-2 h-4 w-4" />Back</Link>
                    </Button>
                </div>

                <div className="space-y-2">
                    <h3 className="font-semibold">Sales Orders</h3>
                    <DataTable
                        columns={columns}
                        data={salesHistory}
                        exportFileName={`sales-history-${material.code}`}
                        timezone={preferences.timezone}
                        initialColumnVisibility={{ customer_name: false }}
                        storageKey="sales-history"
                    />
                </div>

                <div className="space-y-2">
                    <h3 className="font-semibold">Stock by Location</h3>
                    <DataTable
                        columns={stockColumns}
                        data={stockByLocation}
                        exportFileName={`stock-by-location-${material.code}`}
                        timezone={preferences.timezone}
                        storageKey="sales-history-stock"
                    />
                </div>
            </div>
        </>
    );
}

SalesHistory.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Materials', href: '/materials' },
        { title: 'Sales History', href: '#' },
    ]}>{page}</AppLayout>
);
