import { formatAmount } from '@/Components/DataValue';
import { OverviewProps } from '@/types/overview';
import {
    Area,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ComposedChart,
    Legend,
    Line,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

const stateLabels: Record<string, string> = {
    paid: 'დაფარული',
    overdue: 'ვადაგადაცილებული',
    partial: 'ნაწილობრივი',
    pending: 'მოსალოდნელი',
};
const stateColors: Record<string, string> = {
    paid: '#15803d',
    overdue: '#dc2626',
    partial: '#b45309',
    pending: '#fbbf24',
};
const compact = (value: number) =>
    new Intl.NumberFormat('ka-GE', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
const months = ['იან', 'თებ', 'მარ', 'აპრ', 'მაი', 'ივნ', 'ივლ', 'აგვ', 'სექ', 'ოქტ', 'ნოე', 'დეკ'];
const monthLabel = (value: string) => months[Number(value.slice(5)) - 1];

export function CashflowChart({ monthly }: Pick<OverviewProps, 'monthly'>) {
    const data = monthly.map((row) => ({
        ...row,
        received: row.received_GEL === null ? undefined : Number(row.received_GEL),
        scheduled: row.scheduled_GEL === null ? undefined : Number(row.scheduled_GEL),
    }));
    const hasReceived = monthly.some((row) => row.received_GEL !== null);
    const hasScheduled = monthly.some((row) => row.scheduled_GEL !== null);
    return (
        <>
            <div
                className="h-72 w-full"
                role="img"
                aria-label="პერიოდის მიღებული თანხები და გრაფიკით გადასახდელი თანხები, ლარში"
            >
                <ResponsiveContainer width="100%" height="100%" minWidth={0}>
                    <ComposedChart data={data} margin={{ top: 10, right: 8, left: 0, bottom: 0 }} accessibilityLayer>
                        <CartesianGrid vertical={false} stroke="#e5e7eb" strokeDasharray="3 4" />
                        <XAxis
                            dataKey="month"
                            tickFormatter={monthLabel}
                            tickLine={false}
                            axisLine={false}
                            tick={{ fontSize: 11, fill: '#6b7280' }}
                            minTickGap={15}
                            dy={8}
                        />
                        <YAxis
                            tickFormatter={compact}
                            tickLine={false}
                            axisLine={false}
                            tick={{ fontSize: 11, fill: '#6b7280' }}
                            width={58}
                        />
                        <Tooltip
                            content={({ active, payload }) =>
                                active && payload?.length ? (
                                    <div className="rounded-lg border border-gray-200 bg-white p-3 text-xs shadow-lg">
                                        <p className="mb-2 font-semibold">{String(payload[0].payload.month)}</p>
                                        {payload.map((entry) => (
                                            <p key={String(entry.dataKey)} className="mt-1 flex justify-between gap-5">
                                                <span>{entry.name}</span>
                                                <strong className="tabular-nums">
                                                    {formatAmount(String(entry.payload[`${entry.dataKey}_GEL`]))} ₾
                                                </strong>
                                            </p>
                                        ))}
                                    </div>
                                ) : null
                            }
                        />
                        <Legend iconType="plainline" wrapperStyle={{ fontSize: 12, paddingTop: 18 }} />
                        {hasReceived && (
                            <Area
                                type="monotone"
                                dataKey="received"
                                name="მიღებული"
                                stroke="#940028"
                                fill="#940028"
                                fillOpacity={0.1}
                                strokeWidth={2.5}
                                isAnimationActive={false}
                            />
                        )}
                        {hasScheduled && (
                            <Line
                                type="monotone"
                                dataKey="scheduled"
                                name="გრაფიკით გადასახდელი"
                                stroke="#64748b"
                                strokeDasharray="5 4"
                                strokeWidth={2}
                                dot={false}
                                isAnimationActive={false}
                            />
                        )}
                    </ComposedChart>
                </ResponsiveContainer>
            </div>
            <details className="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-500">
                <summary className="cursor-pointer py-2 font-medium text-gray-600">თვიური თანხების ცხრილი</summary>
                <div className="overflow-x-auto">
                    <table className="mt-2 w-full text-left">
                        <thead>
                            <tr>
                                <th className="py-2">თვე</th>
                                {hasReceived && <th className="text-right">მიღებული ₾</th>}
                                {hasScheduled && <th className="text-right">გრაფიკი ₾</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {monthly.map((row) => (
                                <tr key={row.month} className="border-t border-gray-100">
                                    <td className="py-2">{row.month}</td>
                                    {hasReceived && (
                                        <td className="text-right tabular-nums">
                                            {formatAmount(row.received_GEL ?? '0.00')}
                                        </td>
                                    )}
                                    {hasScheduled && (
                                        <td className="text-right tabular-nums">
                                            {formatAmount(row.scheduled_GEL ?? '0.00')}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </details>
        </>
    );
}

export function CoverageChart({ states }: { states: NonNullable<OverviewProps['financial']>['states'] }) {
    const data = states.filter((row) => row.count > 0);
    const total = states.reduce((sum, row) => sum + row.count, 0);
    return (
        <>
            {total > 0 ? (
                <div
                    className="relative mx-auto h-48 max-w-64"
                    role="img"
                    aria-label={`გრაფიკში სულ ${total} შენატანია; სტატუსების რაოდენობები მოცემულია ქვემოთ`}
                >
                    <ResponsiveContainer width="100%" height="100%" minWidth={0}>
                        <PieChart accessibilityLayer>
                            <Pie
                                data={data}
                                dataKey="count"
                                nameKey="status"
                                innerRadius={65}
                                outerRadius={88}
                                paddingAngle={data.length > 1 ? 3 : 0}
                                stroke="none"
                                isAnimationActive={false}
                            >
                                {data.map((row) => (
                                    <Cell key={row.status} fill={stateColors[row.status]} />
                                ))}
                            </Pie>
                            <Tooltip formatter={(value, name) => [value, stateLabels[String(name)] ?? name]} />
                        </PieChart>
                    </ResponsiveContainer>
                    <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <strong className="text-3xl font-semibold tabular-nums">{total}</strong>
                        <span className="mt-1 text-xs text-gray-500">შენატანი</span>
                    </div>
                </div>
            ) : (
                <p className="py-12 text-center text-sm text-gray-500">გადახდის გრაფიკები ჯერ არ არის.</p>
            )}
            <dl className="mt-4 grid gap-x-4 gap-y-3 min-[380px]:grid-cols-2">
                {states.map((row) => (
                    <div key={row.status} className="flex items-center justify-between gap-2 text-xs">
                        <dt className="flex items-center gap-2 text-gray-600">
                            <span
                                aria-hidden="true"
                                className="h-2 w-2 shrink-0 rounded-full"
                                style={{ background: stateColors[row.status] }}
                            />
                            {stateLabels[row.status]}
                        </dt>
                        <dd className="font-semibold tabular-nums">{row.count}</dd>
                    </div>
                ))}
            </dl>
        </>
    );
}

export function ProjectChart({ projects }: Pick<OverviewProps, 'projects'>) {
    return (
        <div
            style={{ height: Math.max(150, projects.length * 46 + 40) }}
            className="w-full"
            role="img"
            aria-label="პროექტებში თავისუფალი და გაყიდული ბინების შედარება"
        >
            <ResponsiveContainer width="100%" height="100%" minWidth={0}>
                <BarChart
                    data={projects}
                    layout="vertical"
                    margin={{ left: 0, right: 8 }}
                    barSize={14}
                    accessibilityLayer
                >
                    <CartesianGrid horizontal={false} stroke="#f3f4f6" />
                    <XAxis
                        type="number"
                        allowDecimals={false}
                        axisLine={false}
                        tickLine={false}
                        tick={{ fontSize: 11, fill: '#6b7280' }}
                    />
                    <YAxis
                        type="category"
                        dataKey="name"
                        axisLine={false}
                        tickLine={false}
                        width={100}
                        tick={{ fontSize: 11, fill: '#4b5563' }}
                        tickFormatter={(value: string) => (value.length > 15 ? `${value.slice(0, 14)}…` : value)}
                    />
                    <Tooltip cursor={{ fill: '#faf9f9' }} />
                    <Legend wrapperStyle={{ fontSize: 12 }} iconType="circle" iconSize={8} />
                    <Bar dataKey="sold" stackId="inventory" name="გაყიდული" fill="#940028" isAnimationActive={false} />
                    <Bar
                        dataKey="free"
                        stackId="inventory"
                        name="თავისუფალი"
                        fill="#cbd5e1"
                        radius={[0, 3, 3, 0]}
                        isAnimationActive={false}
                    />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
