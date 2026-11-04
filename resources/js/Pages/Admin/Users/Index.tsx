import ClearFiltersLink from '@/Components/ClearFiltersLink';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import UserActions from './Partials/UserActions';

type UserRow = { id: number; name: string; email: string; roles: string[] };
type UsersPage = {
    data: UserRow[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
const pageLink =
    'inline-flex min-h-11 items-center rounded-lg border border-gray-200 px-4 text-sm text-gray-600 hover:border-brand hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand';

export default function Index({
    users,
    roles,
    filters,
    auth,
    flash,
}: PageProps<{
    users: UsersPage;
    roles: Record<string, string>;
    filters: { q?: string; role?: string };
}>) {
    const search = useForm({ q: filters.q ?? '', role: filters.role ?? '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        search.get(route('admin.users.index'), { preserveState: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-2xl font-semibold text-ink">მომხმარებლები</h1>
                    <Link
                        href={route('admin.users.create')}
                        className="inline-flex min-h-11 items-center rounded-lg bg-brand px-4 text-sm font-medium text-white hover:bg-brand-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
                    >
                        ახალი მომხმარებელი
                    </Link>
                </div>
            }
        >
            <Head title="მომხმარებლები" />
            <div lang="ka" className="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
                {flash.success && (
                    <p
                        role="status"
                        className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                    >
                        {flash.success}
                    </p>
                )}
                <form
                    onSubmit={submit}
                    className="flex flex-wrap items-end gap-4 rounded-xl border border-gray-200 bg-white p-5"
                >
                    <div className="min-w-0 flex-1">
                        <label htmlFor="user-search" className="mb-1.5 block text-sm font-medium text-gray-700">
                            ძიება
                        </label>
                        <input
                            id="user-search"
                            type="search"
                            placeholder="სახელი ან ელ. ფოსტა"
                            maxLength={100}
                            value={search.data.q}
                            onChange={(event) => search.setData('q', event.target.value)}
                            className="min-h-11 w-full min-w-44 rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand"
                        />
                    </div>
                    <SelectInput
                        className="w-full sm:w-52"
                        label="როლი"
                        value={search.data.role}
                        onChange={(value) => search.setData('role', value)}
                        placeholder="ყველა როლი"
                        options={Object.entries(roles).map(([value, label]) => ({ value, label }))}
                        error={search.errors.role}
                    />
                    <PrimaryButton
                        className="min-h-11 !text-sm !normal-case !tracking-normal"
                        disabled={search.processing}
                    >
                        ძებნა
                    </PrimaryButton>
                    <ClearFiltersLink href={route('admin.users.index')} />
                </form>
                {search.errors.q && (
                    <p role="alert" className="text-sm text-red-600">
                        {search.errors.q}
                    </p>
                )}
                <section
                    aria-label="მომხმარებლების სია"
                    className="overflow-hidden rounded-xl border border-gray-200 bg-white"
                >
                    <p className="border-b border-gray-100 px-5 py-4 text-sm text-gray-500">
                        სულ {users.total} მომხმარებელი
                    </p>
                    {!users.data.length ? (
                        <p className="p-6 text-sm text-gray-500">მომხმარებელი ვერ მოიძებნა.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-gray-50 text-xs text-gray-500">
                                    <tr>
                                        <th scope="col" className="px-5 py-3">
                                            მომხმარებელი
                                        </th>
                                        <th scope="col" className="px-5 py-3">
                                            როლი
                                        </th>
                                        <th scope="col" className="px-5 py-3 text-right">
                                            მოქმედებები
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {users.data.map((user) => (
                                        <tr key={user.id} className="hover:bg-gray-50/70">
                                            <td className="px-5 py-3">
                                                <Link
                                                    href={route('admin.users.edit', user.id)}
                                                    className="inline-flex min-h-11 flex-col justify-center rounded text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                                >
                                                    <span className="font-medium">
                                                        {user.name}
                                                        {user.id === auth.user.id && (
                                                            <span className="ml-2 text-xs font-normal text-gray-500">
                                                                თქვენ
                                                            </span>
                                                        )}
                                                    </span>
                                                    <span className="mt-1 text-xs text-gray-500">{user.email}</span>
                                                </Link>
                                            </td>
                                            <td className="px-5 py-3">
                                                <div className="flex flex-wrap gap-1.5">
                                                    {user.roles.length ? (
                                                        user.roles.map((role) => (
                                                            <span
                                                                key={role}
                                                                className={`whitespace-nowrap rounded-full px-2.5 py-1 text-xs ${role === 'admin' ? 'bg-brand-soft text-brand' : 'bg-gray-100 text-gray-600'}`}
                                                            >
                                                                {roles[role] ?? role}
                                                            </span>
                                                        ))
                                                    ) : (
                                                        <span className="text-xs text-gray-500">ინდივიდუალური</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-5 py-3">
                                                <div className="flex justify-end">
                                                    <UserActions user={user} />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {users.last_page > 1 && (
                        <nav
                            aria-label="მომხმარებლების გვერდები"
                            className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-5"
                        >
                            <span className="text-sm text-gray-500">
                                {users.current_page} / {users.last_page}
                            </span>
                            <div className="flex gap-2">
                                {users.prev_page_url && (
                                    <Link href={users.prev_page_url} className={pageLink}>
                                        წინა
                                    </Link>
                                )}
                                {users.next_page_url && (
                                    <Link href={users.next_page_url} className={pageLink}>
                                        შემდეგი
                                    </Link>
                                )}
                            </div>
                        </nav>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
