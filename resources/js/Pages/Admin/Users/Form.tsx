import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import PasswordInput from '@/Components/PasswordInput';
import PrimaryButton from '@/Components/PrimaryButton';
import SelectInput from '@/Components/SelectInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

type Role = { value: string; label: string; permissions: string[] };
type PermissionGroup = { label: string; permissions: { name: string; label: string }[] };
type UserData = { id: number; name: string; email: string; role: string | null; permissions: string[] };

export default function Form({
    user,
    isSelf,
    roles,
    permissionGroups,
}: PageProps<{
    user: UserData | null;
    isSelf: boolean;
    roles: Role[];
    permissionGroups: PermissionGroup[];
}>) {
    const title = user ? 'მომხმარებლის რედაქტირება' : 'ახალი მომხმარებელი';
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user ? (user.role ?? '') : 'viewer',
        permissions: user?.permissions ?? ([] as string[]),
        password: '',
        password_confirmation: '',
    });
    const inherited = new Set(roles.find((role) => role.value === form.data.role)?.permissions ?? []);
    const permissionNames = permissionGroups.flatMap((group) => group.permissions.map((permission) => permission.name));
    const effective = new Set([...inherited, ...form.data.permissions]);
    const field = 'mt-2 block min-h-11 w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand';
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (user) form.patch(route('admin.users.update', user.id));
        else
            form.post(route('admin.users.store'), { onSuccess: () => form.reset('password', 'password_confirmation') });
    };
    const changeRole = (value: string) =>
        form.setData((data) => ({
            ...data,
            role: value,
            permissions:
                value === '' ? [...effective].filter((name) => permissionNames.includes(name)) : data.permissions,
        }));
    const toggle = (name: string, checked: boolean) =>
        form.setData(
            'permissions',
            checked
                ? [...form.data.permissions, name]
                : form.data.permissions.filter((permission) => permission !== name),
        );
    const permissionErrors = Object.entries(form.errors).filter(
        ([key]) => key === 'permissions' || key.startsWith('permissions.'),
    );

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-semibold text-ink">{title}</h1>}>
            <Head title={title} />
            <div lang="ka" className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <Link
                    href={route('admin.users.index')}
                    className="mb-4 inline-flex min-h-11 items-center rounded text-sm text-brand underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                    მომხმარებლებში დაბრუნება
                </Link>
                <form
                    onSubmit={submit}
                    className="max-w-4xl space-y-6 rounded-xl border border-gray-200 bg-white p-5 sm:p-6"
                >
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label htmlFor="user-name" className="text-sm font-medium text-gray-700">
                                სახელი და გვარი
                            </label>
                            <input
                                id="user-name"
                                name="name"
                                autoComplete="name"
                                required
                                maxLength={255}
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                className={field}
                                aria-invalid={Boolean(form.errors.name)}
                                aria-describedby={form.errors.name ? 'user-name-error' : undefined}
                            />
                            <InputError id="user-name-error" role="alert" message={form.errors.name} className="mt-2" />
                        </div>
                        <div>
                            <label htmlFor="user-email" className="text-sm font-medium text-gray-700">
                                ელ. ფოსტა
                            </label>
                            <input
                                id="user-email"
                                name="email"
                                type="email"
                                autoComplete="email"
                                required
                                maxLength={255}
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                className={field}
                                aria-invalid={Boolean(form.errors.email)}
                                aria-describedby={form.errors.email ? 'user-email-error' : undefined}
                            />
                            <InputError
                                id="user-email-error"
                                role="alert"
                                message={form.errors.email}
                                className="mt-2"
                            />
                        </div>
                        {!user && (
                            <>
                                <div>
                                    <label
                                        htmlFor="user-password"
                                        className="mb-2 block text-sm font-medium text-gray-700"
                                    >
                                        საწყისი პაროლი
                                    </label>
                                    <PasswordInput
                                        id="user-password"
                                        name="password"
                                        autoComplete="new-password"
                                        required
                                        value={form.data.password}
                                        onChange={(event) => form.setData('password', event.target.value)}
                                        aria-invalid={Boolean(form.errors.password)}
                                        aria-describedby={`user-password-hint${form.errors.password ? ' user-password-error' : ''}`}
                                    />
                                    <p id="user-password-hint" className="mt-2 text-xs text-gray-500">
                                        მინიმუმ 8 სიმბოლო.
                                    </p>
                                    <InputError
                                        id="user-password-error"
                                        role="alert"
                                        message={form.errors.password}
                                        className="mt-2"
                                    />
                                </div>
                                <div>
                                    <label
                                        htmlFor="user-password-confirmation"
                                        className="mb-2 block text-sm font-medium text-gray-700"
                                    >
                                        პაროლი განმეორებით
                                    </label>
                                    <PasswordInput
                                        id="user-password-confirmation"
                                        name="password_confirmation"
                                        autoComplete="new-password"
                                        required
                                        value={form.data.password_confirmation}
                                        onChange={(event) => form.setData('password_confirmation', event.target.value)}
                                    />
                                </div>
                            </>
                        )}
                    </div>
                    <div className="border-t border-gray-100 pt-6">
                        <SelectInput
                            id="user-role"
                            className="max-w-sm"
                            label="როლი"
                            value={form.data.role}
                            onChange={changeRole}
                            options={[{ value: '', label: 'ინდივიდუალური უფლებები' }, ...roles]}
                            disabled={isSelf || form.processing}
                            error={form.errors.role}
                        />
                        {!isSelf && (
                            <p className="mt-2 text-xs leading-5 text-gray-500">
                                როლი უფლებებს ავტომატურად ანიჭებს. თავისუფალი არჩევანისთვის აირჩიე „ინდივიდუალური
                                უფლებები“.
                            </p>
                        )}
                    </div>
                    <fieldset disabled={isSelf || form.processing} className="border-t border-gray-100 pt-5">
                        <legend className="px-0 text-sm font-semibold text-ink">წვდომის უფლებები</legend>
                        <p className="mt-2 text-xs leading-5 text-gray-500">
                            როლიდან მიღებული უფლებები მონიშნულია. საჭიროების მიხედვით დაამატე სხვა უფლებებიც.
                        </p>
                        <div className="mt-5 grid gap-6 sm:grid-cols-2">
                            {permissionGroups.map((group) => (
                                <div key={group.label}>
                                    <h2 className="mb-2 text-xs font-semibold text-gray-500">{group.label}</h2>
                                    <div>
                                        {group.permissions.map((permission) => (
                                            <label
                                                key={permission.name}
                                                className="flex min-h-11 cursor-pointer items-center gap-3 rounded py-2 text-sm text-gray-700 has-[:disabled]:cursor-default"
                                            >
                                                <Checkbox
                                                    checked={effective.has(permission.name)}
                                                    disabled={isSelf || inherited.has(permission.name)}
                                                    onChange={(event) => toggle(permission.name, event.target.checked)}
                                                    value={permission.name}
                                                />
                                                <span>
                                                    {permission.label}
                                                    {inherited.has(permission.name) && (
                                                        <span className="ml-2 text-xs text-gray-400">როლიდან</span>
                                                    )}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </fieldset>
                    {permissionErrors.map(([key, error]) => (
                        <InputError key={key} role="alert" message={error} />
                    ))}
                    <div className="flex flex-wrap items-center gap-4 border-t border-gray-100 pt-5">
                        <PrimaryButton
                            className="min-h-11 !text-sm !normal-case !tracking-normal"
                            disabled={form.processing}
                        >
                            {form.processing ? 'ინახება…' : 'შენახვა'}
                        </PrimaryButton>
                        <Link
                            href={route('admin.users.index')}
                            className="inline-flex min-h-11 items-center rounded text-sm text-gray-500 hover:text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            გაუქმება
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
