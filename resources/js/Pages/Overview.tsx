import { CashflowChart, CoverageChart, ProjectChart } from '@/Components/OverviewCharts';
import { formatAmount } from '@/Components/DataValue';
import Icon, { IconName } from '@/Components/Icon';
import PrimaryButton from '@/Components/PrimaryButton';
import InputError from '@/Components/InputError';
import StatusBadge from '@/Components/StatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { OverviewProps } from '@/types/overview';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';

const importLabels: Record<string, string> = {
    queued: 'რიგშია',
    processing: 'მუშავდება',
    completed: 'დასრულებული',
    completed_with_errors: 'შეცდომებით',
    failed: 'შეჩერებული',
};
const linkClass =
    'inline-flex min-h-11 items-center gap-2 rounded text-xs font-semibold text-brand hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand';

function Amount({ value, large = false }: { value: string; large?: boolean }) {
    return (
        <span className={`break-words tabular-nums ${large ? 'text-2xl font-semibold sm:text-3xl' : 'font-semibold'}`}>
            {formatAmount(value)} <span className="text-sm font-normal opacity-70">₾</span>
        </span>
    );
}
function Panel({
    title,
    hint,
    action,
    children,
    className = '',
}: {
    title: string;
    hint?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={`min-w-0 rounded-xl border border-gray-200/80 bg-white p-5 sm:p-6 ${className}`}>
            <div className="mb-5 flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                <div>
                    <h2 className="text-sm font-semibold text-ink">{title}</h2>
                    {hint && <p className="mt-1.5 text-xs leading-5 text-gray-500">{hint}</p>}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}
function Metric({
    title,
    value,
    hint,
    icon,
    danger = false,
    url,
}: {
    title: string;
    value: ReactNode;
    hint: string;
    icon: IconName;
    danger?: boolean;
    url?: string;
}) {
    const content = (
        <>
            <div className="flex items-start justify-between gap-3">
                <span className="text-xs font-medium text-gray-600">{title}</span>
                <Icon name={icon} className={`h-5 w-5 ${danger ? 'text-red-600' : 'text-brand'}`} />
            </div>
            <div className={`mt-4 text-xl font-semibold ${danger ? 'text-red-700' : 'text-ink'}`}>{value}</div>
            <p className="mt-2 text-xs leading-5 text-gray-500">{hint}</p>
        </>
    );
    const style = `block min-w-0 rounded-xl border bg-white p-5 ${danger ? 'border-red-200' : 'border-gray-200/80'} ${url ? 'transition-colors hover:border-brand-border focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand' : ''}`;
    return url ? (
        <Link href={url} className={style}>
            {content}
        </Link>
    ) : (
        <div className={style}>{content}</div>
    );
}
function Empty({ children }: { children: ReactNode }) {
    return <p className="py-8 text-center text-sm leading-6 text-gray-500">{children}</p>;
}

export default function Overview({ asOf, portfolio, payments, financial, monthly, projects, imports }: OverviewProps) {
    const { auth, flash } = usePage().props;
    const can = (permission: string) => auth.permissions.includes(permission);
    const form = useForm({ as_of: asOf });
    const filter = (event: FormEvent) => {
        event.preventDefault();
        form.get(route('overview.index'), { preserveScroll: true });
    };
    const hasPortfolio = Object.values(portfolio).some((value) => value !== null);
    const importTotal = imports ? imports.imported + imports.duplicates + imports.rejected : 0;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-end justify-between gap-5">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-ink">მიმოხილვა</h1>
                        <p className="mt-2 text-sm text-gray-500">გადახდები, ვალდებულებები და პროექტების მდგომარეობა</p>
                    </div>
                    <form onSubmit={filter} className="flex max-w-full flex-wrap items-end gap-2">
                        <div>
                            <label htmlFor="overview-date" className="mb-1.5 block text-xs font-medium text-gray-600">
                                საანგარიშო თარიღი
                            </label>
                            <input
                                id="overview-date"
                                type="date"
                                required
                                value={form.data.as_of}
                                onChange={(event) => form.setData('as_of', event.target.value)}
                                className="min-h-11 max-w-full rounded-lg border-gray-300 text-sm shadow-none focus:border-brand focus:ring-brand"
                            />
                            <InputError message={form.errors.as_of} />
                        </div>
                        <PrimaryButton disabled={form.processing}>ჩვენება</PrimaryButton>
                    </form>
                </div>
            }
        >
            <Head title="მიმოხილვა" />
            <div className="mx-auto max-w-7xl space-y-5 px-4 pt-6 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div
                        role="status"
                        className="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800"
                    >
                        {flash.success}
                    </div>
                )}
                {payments && (
                    <section aria-label="თანხების მიმოხილვა" className="overflow-hidden rounded-xl bg-ink text-white">
                        <div className="grid gap-6 p-6 sm:p-8 lg:grid-cols-[1.3fr_1fr_1fr] lg:items-end">
                            <div>
                                <p className="text-sm text-white/70">სულ შემოსული თანხა</p>
                                <p className="mt-3">
                                    <Amount value={payments.received_GEL} large />
                                </p>
                                <p className="mt-3 text-xs text-white/60">
                                    {payments.count} გადახდა · {asOf}-ის ჩათვლით
                                </p>
                            </div>
                            <div className="border-t border-white/15 pt-5 lg:border-l lg:border-t-0 lg:pl-7 lg:pt-0">
                                <p className="mb-3 text-xs text-white/65">გრაფიკებზე განაწილებული</p>
                                <p className="text-xl">
                                    <Amount value={payments.allocated_GEL} />
                                </p>
                                <p className="mt-3 text-xs text-white/60">ხელშეკრულებებზე დაფარული თანხა</p>
                            </div>
                            <div className="border-t border-white/15 pt-5 lg:border-l lg:border-t-0 lg:pl-7 lg:pt-0">
                                <p className="mb-3 text-xs text-white/65">გაუნაწილებელი ნაშთი</p>
                                <p className="text-xl">
                                    <Amount value={payments.unallocated_GEL} />
                                </p>
                                <p className="mt-3 text-xs text-white/60">ავანსი და დაუკავშირებელი გადახდები</p>
                            </div>
                        </div>
                        <Link
                            href={`/payments?as_of=${asOf}`}
                            className="flex min-h-11 items-center justify-between border-t border-white/10 bg-white/5 px-6 py-3 text-xs font-medium text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-white sm:px-8"
                        >
                            გადახდების ნახვა
                            <Icon name="arrow" className="h-4 w-4" />
                        </Link>
                    </section>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {financial && (
                        <>
                            <Metric
                                title="ვადაგადაცილებული თანხა"
                                value={<Amount value={financial.overdue_GEL} />}
                                hint={`${financial.overdue_installments} შენატანი${financial.overdue_clients === null ? '' : ` · ${financial.overdue_clients} კლიენტი`}`}
                                icon="overdue"
                                danger
                                url={can('clients.view') ? `/overdue?as_of=${asOf}` : undefined}
                            />
                            <Metric
                                title="დარჩენილი ვალდებულება"
                                value={<Amount value={financial.remaining_GEL} />}
                                hint="მომავალი და ვადაგადაცილებული გადასახდელები"
                                icon="contracts"
                                url="/contracts"
                            />
                        </>
                    )}
                    {portfolio.free !== null && (
                        <Metric
                            title="თავისუფალი ბინები"
                            value={
                                <>
                                    {portfolio.free}
                                    <span className="ml-2 text-sm font-normal text-gray-500">
                                        / {portfolio.apartments}
                                    </span>
                                </>
                            }
                            hint="გასაყიდად ხელმისაწვდომი მიმდინარე პორტფელი"
                            icon="apartments"
                            url="/apartments?status=free"
                        />
                    )}
                    {payments && (
                        <Metric
                            title="დაუკავშირებელი გადახდები"
                            value={<Amount value={payments.unmatched_GEL} />}
                            hint={`${payments.unmatched_count} გადახდას კლიენტი არ უკავშირდება`}
                            icon="payments"
                            url={`/payments?client_match=unmatched&as_of=${asOf}`}
                        />
                    )}
                </div>

                {hasPortfolio && (
                    <section
                        aria-label="პორტფელის მაჩვენებლები"
                        className="flex flex-wrap gap-x-8 gap-y-4 rounded-xl border border-gray-200/80 bg-white px-5 py-4 sm:px-6"
                    >
                        {(
                            [
                                ['პროექტი', portfolio.projects],
                                ['ბინა', portfolio.apartments],
                                ['გაყიდული ბინა', portfolio.sold],
                                ['კლიენტი', portfolio.clients],
                                ['ხელშეკრულება', portfolio.contracts],
                            ] as const
                        ).map(
                            ([label, value]) =>
                                value !== null && (
                                    <div key={label} className="flex items-baseline gap-2">
                                        <strong className="text-xl font-semibold tabular-nums">{value}</strong>
                                        <span className="text-xs text-gray-500">{label}</span>
                                    </div>
                                ),
                        )}
                        <p className="w-full text-xs text-gray-500">
                            ბინებისა და კლიენტების რაოდენობა მიმდინარეა; ხელშეკრულებები დათვლილია {asOf}-ის ჩათვლით.
                        </p>
                    </section>
                )}

                {(monthly.length > 0 || financial) && (
                    <div
                        className={`grid gap-5 ${financial && monthly.length ? 'lg:grid-cols-[minmax(0,1.8fr)_minmax(0,1fr)]' : ''}`}
                    >
                        {monthly.length > 0 && (
                            <Panel
                                title="თანხების დინამიკა"
                                hint="ბოლო 12 თვე · მიმდინარე თვე საანგარიშო თარიღამდე · თანხები ლარში"
                            >
                                <CashflowChart monthly={monthly} />
                            </Panel>
                        )}
                        {financial && (
                            <Panel
                                title="გადახდის გრაფიკების მდგომარეობა"
                                hint="შენატანების რაოდენობა საანგარიშო თარიღისთვის"
                            >
                                <CoverageChart states={financial.states} />
                                <div className="mt-5 border-t border-gray-100 pt-4">
                                    <p className="text-xs text-gray-500">ხელშეკრულებების სრული ღირებულება</p>
                                    <p className="mt-2 text-lg">
                                        <Amount value={financial.contract_value_GEL} />
                                    </p>
                                    <div className="mt-4 flex flex-wrap justify-between gap-2 text-xs">
                                        <span className="text-gray-500">დაფარულია {financial.paid_percent}%</span>
                                        <span className="font-semibold text-green-700">
                                            <Amount value={financial.paid_GEL} />
                                        </span>
                                    </div>
                                    <div
                                        aria-hidden="true"
                                        className="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100"
                                    >
                                        <div
                                            className="h-full bg-green-600"
                                            style={{ width: `${Math.min(100, Number(financial.paid_percent))}%` }}
                                        />
                                    </div>
                                </div>
                            </Panel>
                        )}
                    </div>
                )}

                {(projects.length > 0 || payments) && (
                    <div className="grid gap-5 lg:grid-cols-2">
                        {portfolio.apartments !== null && (
                            <Panel
                                title="ბინები პროექტების მიხედვით"
                                hint="მიმდინარე სტატუსები · მაქსიმუმ 10 პროექტი ბინების რაოდენობით"
                                action={
                                    <Link href="/apartments" className={linkClass}>
                                        ბინები
                                        <Icon name="arrow" className="h-4 w-4" />
                                    </Link>
                                }
                            >
                                {projects.length ? (
                                    <>
                                        <ProjectChart projects={projects} />
                                        <div className="mt-4 flex flex-wrap gap-x-4 gap-y-1 border-t border-gray-100 pt-3">
                                            {projects.map((p) => (
                                                <Link key={p.url} href={p.url} className={linkClass}>
                                                    {p.name}
                                                    <span className="font-normal text-gray-500">
                                                        {p.sold}/{p.free + p.sold} გაყიდული
                                                    </span>
                                                </Link>
                                            ))}
                                        </div>
                                    </>
                                ) : (
                                    <Empty>დაამატე პროექტი და ბინები მათი სტატისტიკის სანახავად.</Empty>
                                )}
                            </Panel>
                        )}
                        {payments && (
                            <Panel
                                title="გადახდების დაკავშირება"
                                hint="გადახდების რაოდენობა კლიენტთან კავშირის მიხედვით"
                            >
                                <div className="flex items-end justify-between gap-3">
                                    <div>
                                        <p className="text-3xl font-semibold tabular-nums">
                                            {payments.matched_count}
                                            <span className="ml-2 text-sm font-normal text-gray-500">
                                                დაკავშირებული
                                            </span>
                                        </p>
                                        <p className="mt-3 text-xs text-gray-500">სულ {payments.count} გადახდიდან</p>
                                    </div>
                                    <Icon name="clients" className="h-8 w-8 text-brand/70" />
                                </div>
                                <div
                                    aria-hidden="true"
                                    className="mt-6 flex h-3 overflow-hidden rounded-full bg-gray-100"
                                >
                                    {payments.count > 0 && (
                                        <>
                                            <span
                                                className="bg-green-600"
                                                style={{ width: `${(payments.matched_count / payments.count) * 100}%` }}
                                            />
                                            <span
                                                className="bg-amber-400"
                                                style={{
                                                    width: `${(payments.unmatched_count / payments.count) * 100}%`,
                                                }}
                                            />
                                        </>
                                    )}
                                </div>
                                <dl className="mt-5 grid grid-cols-2 gap-4 text-xs">
                                    <div>
                                        <dt className="flex items-center gap-2 text-gray-500">
                                            <span className="h-2 w-2 rounded-full bg-green-600" />
                                            დაკავშირებული
                                        </dt>
                                        <dd className="mt-2 text-lg font-semibold">{payments.matched_count}</dd>
                                    </div>
                                    <div>
                                        <dt className="flex items-center gap-2 text-gray-500">
                                            <span className="h-2 w-2 rounded-full bg-amber-400" />
                                            დაუკავშირებელი
                                        </dt>
                                        <dd className="mt-2 text-lg font-semibold">{payments.unmatched_count}</dd>
                                    </div>
                                </dl>
                                <div className="mt-5 border-t border-gray-100 pt-4 text-xs leading-6 text-gray-500">
                                    გაუნაწილებელი თანხა შეიძლება შეიცავდეს კლიენტის ავანსსაც. დაუკავშირებელ გადახდას კი
                                    კლიენტი ჯერ არ აქვს მინიჭებული.
                                </div>
                            </Panel>
                        )}
                    </div>
                )}

                {financial && (
                    <div className="grid gap-5 lg:grid-cols-2">
                        {can('clients.view') && (
                            <Panel
                                title="ყველაზე დიდი დავალიანებები"
                                hint="ხუთი კლიენტი დარჩენილი ვადაგადაცილებული თანხით"
                                action={
                                    <Link href={`/overdue?as_of=${asOf}`} className={linkClass}>
                                        ყველა
                                        <Icon name="arrow" className="h-4 w-4" />
                                    </Link>
                                }
                            >
                                {financial.debtors.length ? (
                                    <ol className="divide-y divide-gray-100">
                                        {financial.debtors.map((row, index) => (
                                            <li key={row.url}>
                                                <Link
                                                    href={row.url}
                                                    className="flex min-h-16 items-center gap-3 rounded py-3 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                                >
                                                    <span className="w-5 text-xs tabular-nums text-gray-400">
                                                        {index + 1}
                                                    </span>
                                                    <span className="min-w-0 flex-1 break-words text-sm font-medium">
                                                        {row.name}
                                                    </span>
                                                    <span className="shrink-0 text-sm text-red-700">
                                                        <Amount value={row.amount_GEL} />
                                                    </span>
                                                </Link>
                                            </li>
                                        ))}
                                    </ol>
                                ) : (
                                    <Empty>ამ თარიღისთვის ვადაგადაცილებული დავალიანება არ არის.</Empty>
                                )}
                            </Panel>
                        )}
                        <Panel title="უახლოესი გადასახდელები" hint="მომდევნო 30 დღე · პირველი ხუთი დაუფარავი შენატანი">
                            <div className="divide-y divide-gray-100">
                                {financial.upcoming.map((row) => (
                                    <Link
                                        key={`${row.url}-${row.date}`}
                                        href={row.url}
                                        className="flex min-h-16 flex-wrap items-center justify-between gap-2 rounded py-3 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                    >
                                        <span className="min-w-0 flex-1">
                                            <span className="block break-words text-sm font-medium text-brand">
                                                {row.name}
                                            </span>
                                            <span className="mt-1 block text-xs text-gray-500">{row.date}</span>
                                        </span>
                                        <span className="text-sm">
                                            <Amount value={row.amount_GEL} />
                                        </span>
                                    </Link>
                                ))}
                            </div>
                            {!financial.upcoming.length && <Empty>მომდევნო 30 დღეში დაუფარავი შენატანი არ არის.</Empty>}
                        </Panel>
                    </div>
                )}

                {(payments || imports) && (
                    <div className="grid gap-5 lg:grid-cols-2">
                        {payments && (
                            <Panel
                                title="ბოლო გადახდები"
                                hint="გადახდის თარიღით · საანგარიშო თარიღის ჩათვლით"
                                action={
                                    <Link href={`/payments?as_of=${asOf}`} className={linkClass}>
                                        ყველა
                                        <Icon name="arrow" className="h-4 w-4" />
                                    </Link>
                                }
                            >
                                <div className="divide-y divide-gray-100">
                                    {payments.recent.map((row) => (
                                        <Link
                                            key={row.url}
                                            href={row.url}
                                            className="flex min-h-20 flex-wrap items-center justify-between gap-3 rounded py-3 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                        >
                                            <span className="min-w-0 flex-1">
                                                <span className="block break-words text-sm font-medium text-brand">
                                                    {row.reference}
                                                </span>
                                                <span className="mt-1 block break-words text-xs text-gray-500">
                                                    {row.payer} · {row.date}
                                                </span>
                                            </span>
                                            <span className="text-right">
                                                <span className="block text-sm">
                                                    <Amount value={row.amount_GEL} />
                                                </span>
                                                <span
                                                    className={`mt-1 block text-xs ${row.matched ? 'text-green-700' : 'text-amber-700'}`}
                                                >
                                                    {row.matched ? 'დაკავშირებული' : 'დაუკავშირებელი'}
                                                </span>
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                                {!payments.recent.length && <Empty>გადახდები ჯერ არ არის.</Empty>}
                            </Panel>
                        )}
                        {imports && (
                            <Panel
                                title="იმპორტის შედეგები"
                                hint="ფაილებისა და რიგების მიმდინარე მდგომარეობა"
                                action={
                                    <Link href="/imports" className={linkClass}>
                                        იმპორტები
                                        <Icon name="arrow" className="h-4 w-4" />
                                    </Link>
                                }
                            >
                                <div className="grid grid-cols-3 gap-2">
                                    {[
                                        { label: 'ჩაწერილი', value: imports.imported, color: 'text-green-700' },
                                        { label: 'დუბლიკატი', value: imports.duplicates, color: 'text-gray-600' },
                                        { label: 'უარყოფილი', value: imports.rejected, color: 'text-red-700' },
                                    ].map((item) => (
                                        <div key={item.label}>
                                            <p className={`text-2xl font-semibold tabular-nums ${item.color}`}>
                                                {item.value}
                                            </p>
                                            <p className="mt-1 text-xs text-gray-500">{item.label}</p>
                                        </div>
                                    ))}
                                </div>
                                <div
                                    aria-hidden="true"
                                    className="mt-4 flex h-2 overflow-hidden rounded-full bg-gray-100"
                                >
                                    {importTotal > 0 && (
                                        <>
                                            <span
                                                className="bg-green-600"
                                                style={{ width: `${(imports.imported / importTotal) * 100}%` }}
                                            />
                                            <span
                                                className="bg-gray-400"
                                                style={{ width: `${(imports.duplicates / importTotal) * 100}%` }}
                                            />
                                            <span
                                                className="bg-red-600"
                                                style={{ width: `${(imports.rejected / importTotal) * 100}%` }}
                                            />
                                        </>
                                    )}
                                </div>
                                <p className="mt-3 text-xs text-gray-500">
                                    {imports.files} ფაილი · რიგში/მუშავდება:{' '}
                                    {(imports.statuses.queued ?? 0) + (imports.statuses.processing ?? 0)} · შეჩერებული:{' '}
                                    {imports.statuses.failed ?? 0}
                                </p>
                                <div className="mt-4 divide-y divide-gray-100 border-t border-gray-100">
                                    {imports.recent.map((row) => (
                                        <Link
                                            key={row.url}
                                            href={row.url}
                                            className="flex min-h-16 flex-wrap items-center justify-between gap-2 rounded py-3 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                        >
                                            <span className="min-w-0 flex-1 break-words text-sm font-medium text-brand">
                                                {row.filename}
                                            </span>
                                            <StatusBadge
                                                status={row.status}
                                                label={importLabels[row.status] ?? row.status}
                                            />
                                        </Link>
                                    ))}
                                </div>
                                {!imports.recent.length && <Empty>იმპორტის ფაილები ჯერ არ არის.</Empty>}
                            </Panel>
                        )}
                    </div>
                )}
                {!hasPortfolio && !payments && !financial && !imports && (
                    <Panel title="მიმოხილვა">
                        <Empty>მონაცემების სანახავად საჭიროა შესაბამისი განყოფილებების ნახვის უფლება.</Empty>
                    </Panel>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
