import DataValue from '@/Components/DataValue';
import { WorkspacePaginationLink, WorkspaceRow } from '@/types/workspace';
import { Link } from '@inertiajs/react';
import { columnsBySection, labels } from '../config';

type Props = {
    section: string;
    rows: WorkspaceRow[];
    links: WorkspacePaginationLink[];
    total: number;
    permissions: string[];
    processing: boolean;
};

function cellClassName(field: string): string {
    const base = 'px-5 py-4 align-top';
    if (field === 'message') return `${base} min-w-48 max-w-sm break-words`;
    if (!field.endsWith('_GEL')) return base;

    let color = 'text-ink';
    if (field === 'overdue_GEL') color = 'text-red-700';
    if (field === 'paid_GEL') color = 'text-green-800';
    return `${base} whitespace-nowrap text-right font-medium ${color}`;
}

export default function ResultsTable({ section, rows, links, total, permissions, processing }: Props) {
    const columns = columnsBySection[section] ?? [];
    const canEdit =
        section === 'apartments' &&
        permissions.includes('apartments.update') &&
        rows.some((row) => typeof row._edit_url === 'string');

    return (
        <section
            aria-label="შედეგები"
            aria-busy={processing}
            className="overflow-hidden rounded-xl border border-gray-200 bg-white"
        >
            <p className="border-b border-gray-100 px-6 py-4 text-sm text-gray-500">
                ჩანაწერები: <span className="font-semibold text-ink">{total}</span>
            </p>
            {rows.length === 0 ? (
                <p className="p-6 text-sm text-gray-500">ჩანაწერები ვერ მოიძებნა.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">{labels[section]}</caption>
                        <thead className="bg-gray-50 text-xs font-medium text-gray-500">
                            <tr>
                                {columns.map((key) => (
                                    <th
                                        key={key}
                                        scope="col"
                                        className={`whitespace-nowrap px-5 py-3.5 ${key.endsWith('_GEL') ? 'text-right' : ''}`}
                                    >
                                        {labels[key] ?? key}
                                    </th>
                                ))}
                                {canEdit && (
                                    <th scope="col" className="px-5 py-3.5">
                                        მოქმედება
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {rows.map((row, index) => (
                                <tr key={String(row.id ?? index)} className="hover:bg-gray-50/60">
                                    {columns.map((key) => (
                                        <td key={key} className={cellClassName(key)}>
                                            <DataValue
                                                field={key}
                                                value={key === 'payments' ? row._links : row[key]}
                                                permissions={permissions}
                                            />
                                            {key === 'payer' && typeof row.personal_number === 'string' && (
                                                <p className="mt-1 text-xs text-gray-500">{row.personal_number}</p>
                                            )}
                                        </td>
                                    ))}
                                    {canEdit && (
                                        <td className="px-5 py-4">
                                            {typeof row._edit_url === 'string' && (
                                                <Link
                                                    href={row._edit_url}
                                                    className="inline-flex min-h-11 items-center text-brand underline underline-offset-4"
                                                >
                                                    რედაქტირება
                                                </Link>
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
            {links.filter((link) => /^\d+$/.test(link.label)).length > 1 && (
                <nav aria-label="შედეგების გვერდები" className="flex flex-wrap gap-2 border-t border-gray-100 p-4">
                    {links.map((link, index) => {
                        const label = link.label.includes('Previous')
                            ? 'წინა'
                            : link.label.includes('Next')
                              ? 'შემდეგი'
                              : link.label;
                        return link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                aria-current={link.active ? 'page' : undefined}
                                className={`inline-flex min-h-11 items-center rounded-lg border px-4 text-sm focus:outline-none focus:ring-2 focus:ring-brand ${link.active ? 'border-brand bg-brand text-white' : 'border-gray-200 text-gray-600 hover:border-brand hover:text-brand'}`}
                            >
                                {label}
                            </Link>
                        ) : (
                            <span
                                key={index}
                                aria-disabled="true"
                                className="inline-flex min-h-11 items-center px-4 text-sm text-gray-300"
                            >
                                {label}
                            </span>
                        );
                    })}
                </nav>
            )}
        </section>
    );
}
