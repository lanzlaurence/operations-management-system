import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { useFormatters } from '@/hooks/use-formatters';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    AlertTriangle,
    Boxes,
    ClipboardList,
    PackageX,
    ShoppingBag,
    ShoppingCart,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import type { BreadcrumbItem, DashboardData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const CHART_COLORS = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
    'var(--muted-foreground)',
];

/** Shortens large amounts for axis ticks, e.g. 1234567 -> "1.2M". */
function compact(value: number): string {
    const abs = Math.abs(value);
    if (abs >= 1_000_000_000) return `${(value / 1_000_000_000).toFixed(1)}B`;
    if (abs >= 1_000_000) return `${(value / 1_000_000).toFixed(1)}M`;
    if (abs >= 1_000) return `${(value / 1_000).toFixed(1)}K`;
    return value.toFixed(0);
}

type StatCardProps = {
    label: string;
    value: React.ReactNode;
    hint?: string;
    icon: React.ComponentType<{ className?: string }>;
    accent?: string;
};

function StatCard({ label, value, hint, icon: Icon, accent = 'text-muted-foreground' }: StatCardProps) {
    return (
        <Card className="gap-2 py-4">
            <CardHeader className="px-4">
                <div className="flex items-center justify-between">
                    <CardDescription className="text-xs font-medium uppercase tracking-wide">{label}</CardDescription>
                    <Icon className={`h-4 w-4 ${accent}`} />
                </div>
            </CardHeader>
            <CardContent className="px-4">
                <p className="font-mono text-xl font-semibold tabular-nums">{value}</p>
                {hint && <p className="mt-0.5 text-xs text-muted-foreground">{hint}</p>}
            </CardContent>
        </Card>
    );
}

function ChartCard({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
                {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

function EmptyChart({ message }: { message: string }) {
    return (
        <div className="flex h-[260px] items-center justify-center text-sm text-muted-foreground">{message}</div>
    );
}

export default function Dashboard({
    stats,
    monthlyTrend,
    stockByCategory,
    topStockValue,
    topSoldValue,
    lowStockItems,
    orderStatus,
}: DashboardData) {
    const { currency, formatAmount, formatDecimal } = useFormatters();

    const money = (value: number) => `${currency.symbol} ${value.toLocaleString('en-US', { maximumFractionDigits: 2 })}`;
    const axisMoney = (value: number) => `${currency.symbol}${compact(value)}`;

    const tooltipStyle = {
        backgroundColor: 'var(--popover)',
        border: '1px solid var(--border)',
        borderRadius: '0.5rem',
        color: 'var(--popover-foreground)',
        fontSize: '0.75rem',
    } as const;

    const statusRows = [
        ...orderStatus.purchase.map((s) => ({ ...s, kind: 'Purchase' as const })),
        ...orderStatus.sales.map((s) => ({ ...s, kind: 'Sales' as const })),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="space-y-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Dashboard</h1>
                    <p className="text-sm text-muted-foreground">Inventory, purchasing and sales at a glance</p>
                </div>

                {/* Primary KPIs */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Stock Value"
                        value={formatAmount(stats.stock_value)}
                        hint={`${formatDecimal(stats.stock_qty)} units on hand`}
                        icon={Boxes}
                        accent="text-blue-600"
                    />
                    <StatCard
                        label="Sales Value"
                        value={formatAmount(stats.sales_value)}
                        hint={`${stats.open_so} open sales order${stats.open_so === 1 ? '' : 's'}`}
                        icon={ShoppingBag}
                        accent="text-green-600"
                    />
                    <StatCard
                        label="Purchase Value"
                        value={formatAmount(stats.purchase_value)}
                        hint={`${stats.open_po} open purchase order${stats.open_po === 1 ? '' : 's'}`}
                        icon={ShoppingCart}
                        accent="text-amber-600"
                    />
                    <StatCard
                        label="Low Stock"
                        value={stats.low_stock}
                        hint={`${stats.out_of_stock} out of stock`}
                        icon={AlertTriangle}
                        accent="text-red-600"
                    />
                </div>

                {/* Secondary KPIs */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Materials" value={stats.materials} icon={ClipboardList} />
                    <StatCard label="Vendors" value={stats.vendors} icon={Wallet} />
                    <StatCard label="Customers" value={stats.customers} icon={Users} />
                    <StatCard
                        label="Gross Margin"
                        value={formatAmount(stats.sales_value - stats.purchase_value)}
                        hint="Sales value less purchase value"
                        icon={TrendingUp}
                    />
                </div>

                {/* Trend */}
                <ChartCard title="Purchases vs Sales" description="Order totals for the last 12 months">
                    <ResponsiveContainer width="100%" height={300}>
                        <AreaChart data={monthlyTrend} margin={{ top: 8, right: 8, left: 8, bottom: 0 }}>
                            <defs>
                                <linearGradient id="fillPurchases" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="var(--chart-1)" stopOpacity={0.4} />
                                    <stop offset="95%" stopColor="var(--chart-1)" stopOpacity={0.03} />
                                </linearGradient>
                                <linearGradient id="fillSales" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="var(--chart-2)" stopOpacity={0.4} />
                                    <stop offset="95%" stopColor="var(--chart-2)" stopOpacity={0.03} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid vertical={false} stroke="var(--border)" />
                            <XAxis
                                dataKey="month"
                                tickLine={false}
                                axisLine={false}
                                tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                            />
                            <YAxis
                                tickFormatter={axisMoney}
                                tickLine={false}
                                axisLine={false}
                                width={70}
                                tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                            />
                            <Tooltip contentStyle={tooltipStyle} formatter={(value) => money(Number(value))} />
                            <Legend wrapperStyle={{ fontSize: '0.75rem' }} />
                            <Area
                                type="monotone"
                                dataKey="purchases"
                                name="Purchases"
                                stroke="var(--chart-1)"
                                fill="url(#fillPurchases)"
                                strokeWidth={2}
                            />
                            <Area
                                type="monotone"
                                dataKey="sales"
                                name="Sales"
                                stroke="var(--chart-2)"
                                fill="url(#fillSales)"
                                strokeWidth={2}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </ChartCard>

                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Stock value by category */}
                    <ChartCard title="Stock Value by Category" description="Share of on-hand value">
                        {stockByCategory.length === 0 ? (
                            <EmptyChart message="No stock on hand" />
                        ) : (
                            <ResponsiveContainer width="100%" height={280}>
                                <PieChart>
                                    <Pie
                                        data={stockByCategory}
                                        dataKey="value"
                                        nameKey="category"
                                        innerRadius={60}
                                        outerRadius={100}
                                        paddingAngle={2}
                                        stroke="var(--background)"
                                    >
                                        {stockByCategory.map((entry, i) => (
                                            <Cell key={entry.category} fill={CHART_COLORS[i % CHART_COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <Tooltip contentStyle={tooltipStyle} formatter={(value) => money(Number(value))} />
                                    <Legend wrapperStyle={{ fontSize: '0.75rem' }} />
                                </PieChart>
                            </ResponsiveContainer>
                        )}
                    </ChartCard>

                    {/* Low stock */}
                    <Card className="gap-4">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <PackageX className="h-4 w-4 text-red-600" />
                                Low Stock Alerts
                            </CardTitle>
                            <CardDescription>Materials at or below their reorder level</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {lowStockItems.length === 0 ? (
                                <EmptyChart message="Every material is above its reorder level" />
                            ) : (
                                <div className="divide-y">
                                    {lowStockItems.map((item) => (
                                        <div key={item.id} className="flex items-center justify-between gap-3 py-2">
                                            <div className="min-w-0">
                                                <Link
                                                    href={`/materials/${item.id}`}
                                                    className="font-mono text-sm font-medium hover:underline"
                                                >
                                                    {item.code}
                                                </Link>
                                                <p className="truncate text-xs text-muted-foreground">{item.name}</p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                <span className="font-mono text-sm tabular-nums">
                                                    {formatDecimal(item.stock)}
                                                    {item.uom ? ` ${item.uom}` : ''}
                                                </span>
                                                <Badge variant={item.stock <= 0 ? 'destructive' : 'secondary'}>
                                                    {item.stock <= 0 ? 'Out' : `Reorder ${item.reorder_level}`}
                                                </Badge>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Top stock value */}
                    <ChartCard title="Top Materials by Stock Value" description="Highest on-hand value">
                        {topStockValue.length === 0 ? (
                            <EmptyChart message="No stock on hand" />
                        ) : (
                            <ResponsiveContainer width="100%" height={280}>
                                <BarChart
                                    data={topStockValue}
                                    layout="vertical"
                                    margin={{ top: 4, right: 16, left: 8, bottom: 4 }}
                                >
                                    <CartesianGrid horizontal={false} stroke="var(--border)" />
                                    <XAxis
                                        type="number"
                                        tickFormatter={axisMoney}
                                        tickLine={false}
                                        axisLine={false}
                                        tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                                    />
                                    <YAxis
                                        type="category"
                                        dataKey="code"
                                        width={70}
                                        tickLine={false}
                                        axisLine={false}
                                        tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                                    />
                                    <Tooltip
                                        cursor={{ fill: 'var(--muted)' }}
                                        contentStyle={tooltipStyle}
                                        formatter={(value) => money(Number(value))}
                                        labelFormatter={(code) =>
                                            topStockValue.find((m) => m.code === code)?.name ?? code
                                        }
                                    />
                                    <Bar dataKey="value" name="Stock value" fill="var(--chart-1)" radius={[0, 4, 4, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </ChartCard>

                    {/* Top sold */}
                    <ChartCard title="Top Materials by Sales" description="Value shipped on completed goods issues">
                        {topSoldValue.length === 0 ? (
                            <EmptyChart message="Nothing shipped yet" />
                        ) : (
                            <ResponsiveContainer width="100%" height={280}>
                                <BarChart
                                    data={topSoldValue}
                                    layout="vertical"
                                    margin={{ top: 4, right: 16, left: 8, bottom: 4 }}
                                >
                                    <CartesianGrid horizontal={false} stroke="var(--border)" />
                                    <XAxis
                                        type="number"
                                        tickFormatter={axisMoney}
                                        tickLine={false}
                                        axisLine={false}
                                        tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                                    />
                                    <YAxis
                                        type="category"
                                        dataKey="code"
                                        width={70}
                                        tickLine={false}
                                        axisLine={false}
                                        tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
                                    />
                                    <Tooltip
                                        cursor={{ fill: 'var(--muted)' }}
                                        contentStyle={tooltipStyle}
                                        formatter={(value) => money(Number(value))}
                                        labelFormatter={(code) =>
                                            topSoldValue.find((m) => m.code === code)?.name ?? code
                                        }
                                    />
                                    <Bar dataKey="value" name="Sales value" fill="var(--chart-2)" radius={[0, 4, 4, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </ChartCard>
                </div>

                {/* Order status */}
                <ChartCard title="Order Status" description="Posted orders by current status">
                    {statusRows.length === 0 ? (
                        <EmptyChart message="No posted orders yet" />
                    ) : (
                        <div className="grid gap-6 sm:grid-cols-2">
                            {(['Purchase', 'Sales'] as const).map((kind) => {
                                const rows = statusRows.filter((r) => r.kind === kind);
                                const total = rows.reduce((sum, r) => sum + r.total, 0);
                                return (
                                    <div key={kind} className="space-y-2">
                                        <p className="text-sm font-medium">{kind} Orders</p>
                                        {rows.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">None</p>
                                        ) : (
                                            rows.map((row, i) => (
                                                <div key={row.status} className="space-y-1">
                                                    <div className="flex items-center justify-between text-xs">
                                                        <span className="capitalize text-muted-foreground">
                                                            {row.status}
                                                        </span>
                                                        <span className="font-mono tabular-nums">{row.total}</span>
                                                    </div>
                                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            className="h-full rounded-full"
                                                            style={{
                                                                width: `${total ? (row.total / total) * 100 : 0}%`,
                                                                backgroundColor:
                                                                    CHART_COLORS[i % CHART_COLORS.length],
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </ChartCard>
            </div>
        </AppLayout>
    );
}
