import StatusBadge from '@/Components/StatusBadge';
import { WorkspaceValue } from '@/types/workspace';
import { Link } from '@inertiajs/react';

const statuses: Record<string, string> = {
    free: 'თავისუფალი',
    sold: 'გაყიდული',
    paid: 'დაფარული',
    overdue: 'ვადაგადაცილებული',
    partial: 'ნაწილობრივ დაფარული',
    pending: 'მოსალოდნელი',
    queued: 'რიგშია',
    processing: 'მუშავდება',
    completed: 'დასრულებული',
    completed_with_errors: 'დასრულებული შეცდომებით',
    failed: 'შეჩერებული',
    imported: 'ჩაწერილი',
    duplicate: 'დუბლიკატი',
    rejected: 'უარყოფილი',
    matched: 'დაკავშირებული',
    unmatched: 'დაუკავშირებელი',
    yes: 'კი',
    future: 'მომავალი გადახდა',
};

export function formatAmount(value: string | number): string {
    const [whole, fraction] = String(value).split('.');
    return whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + (fraction === undefined ? '' : `.${fraction}`);
}

export default function DataValue({
    field,
    value,
    permissions,
}: {
    field: string;
    value: WorkspaceValue | undefined;
    permissions: string[];
}) {
    if (value === null || value === undefined || value === '') return <span className="text-gray-400">—</span>;
    if (Array.isArray(value))
        return value.length ? (
            <div className="flex flex-col gap-2">
                {value.map((link) => (
                    <DataValue key={link.url} field={field} value={link} permissions={permissions} />
                ))}
            </div>
        ) : (
            <span className="text-gray-400">—</span>
        );
    if (typeof value === 'object') {
        const content = (
            <>
                <span className="font-medium">{value.label}</span>
                {value.description && (
                    <span className="mt-0.5 block text-xs leading-5 text-gray-500">{value.description}</span>
                )}
            </>
        );
        return !value.permission || permissions.includes(value.permission) ? (
            <Link
                href={value.url}
                className="inline-flex min-h-11 max-w-full flex-col justify-center break-words text-brand underline decoration-brand/25 underline-offset-4 hover:decoration-brand focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
                {content}
            </Link>
        ) : (
            <span className="inline-flex min-h-11 max-w-full flex-col justify-center break-words text-ink">
                {content}
            </span>
        );
    }
    if (typeof value === 'string' && ['status', 'client_match', 'included_as_of'].includes(field)) {
        return <StatusBadge status={value} label={statuses[value] ?? value} />;
    }
    if (field === 'phone' && typeof value === 'string' && /\d/.test(value))
        return (
            <a
                href={`tel:${value.replace(/[^\d+]/g, '')}`}
                className="inline-flex min-h-11 items-center text-brand underline underline-offset-4"
            >
                {value}
            </a>
        );
    if (field.endsWith('_GEL'))
        return (
            <span className="whitespace-nowrap tabular-nums">
                {formatAmount(value)} <span className="text-xs font-normal text-gray-500">₾</span>
            </span>
        );
    return <>{value}</>;
}
