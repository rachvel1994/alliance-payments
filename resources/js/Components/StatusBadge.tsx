const tones = {
    success: 'bg-green-50 text-green-800 ring-green-600/20',
    danger: 'bg-red-50 text-red-700 ring-red-600/20',
    warning: 'bg-amber-50 text-amber-800 ring-amber-600/20',
    info: 'bg-sky-50 text-sky-800 ring-sky-600/20',
    neutral: 'bg-gray-50 text-gray-700 ring-gray-600/20',
};

const statusTones: Record<string, keyof typeof tones> = {
    paid: 'success',
    overdue: 'danger',
    pending: 'warning',
    partial: 'warning',
    free: 'info',
    sold: 'neutral',
    queued: 'info',
    processing: 'info',
    completed: 'success',
    completed_with_errors: 'warning',
    failed: 'danger',
    imported: 'success',
    duplicate: 'neutral',
    rejected: 'danger',
    matched: 'success',
    unmatched: 'warning',
    yes: 'success',
    future: 'info',
};

export default function StatusBadge({ status, label }: { status: string; label: string }) {
    return <span data-status={status} className={`inline-flex w-fit items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${tones[statusTones[status] ?? 'neutral']}`}>
        <span aria-hidden="true" className="h-1.5 w-1.5 shrink-0 rounded-full bg-current" />
        {label}
    </span>;
}
