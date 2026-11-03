import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    emailVerified,
    status,
}: {
    mustVerifyEmail: boolean;
    emailVerified: boolean;
    status?: string;
}) {
    const user = usePage().props.auth.user;
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        patch(route('profile.update'), { preserveScroll: true });
    };

    return (
        <section aria-labelledby="profile-information-title" className="flex min-w-0 flex-col p-5 sm:p-8">
            <h2 id="profile-information-title" className="mb-6 text-base font-semibold text-ink">
                პირადი მონაცემები
            </h2>
            <form onSubmit={submit} className="flex flex-1 flex-col gap-6">
                <div className="space-y-5">
                    <div>
                        <InputLabel htmlFor="profile-name" value="სახელი და გვარი" />
                        <TextInput
                            id="profile-name"
                            name="name"
                            className="mt-2 block min-h-11 w-full rounded-lg shadow-none"
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            required
                            maxLength={255}
                            autoComplete="name"
                            aria-invalid={Boolean(errors.name)}
                            aria-describedby={errors.name ? 'profile-name-error' : undefined}
                        />
                        <InputError id="profile-name-error" role="alert" className="mt-2" message={errors.name} />
                    </div>
                    <div>
                        <InputLabel htmlFor="profile-email" value="ელ. ფოსტა" />
                        <TextInput
                            id="profile-email"
                            name="email"
                            type="email"
                            className="mt-2 block min-h-11 w-full rounded-lg shadow-none"
                            value={data.email}
                            onChange={(event) => setData('email', event.target.value)}
                            required
                            maxLength={255}
                            autoComplete="email"
                            aria-invalid={Boolean(errors.email)}
                            aria-describedby={errors.email ? 'profile-email-error' : undefined}
                        />
                        <InputError id="profile-email-error" role="alert" className="mt-2" message={errors.email} />
                    </div>
                </div>
                {mustVerifyEmail && !emailVerified && (
                    <div className="text-sm text-gray-600">
                        <p>ელ. ფოსტის მისამართი ჯერ არ არის დადასტურებული.</p>
                        <Link
                            href={route('verification.send')}
                            method="post"
                            as="button"
                            className="mt-1 inline-flex min-h-11 items-center rounded font-medium underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            დადასტურების ბმულის ხელახლა გაგზავნა
                        </Link>
                        {status === 'verification-link-sent' && (
                            <p role="status" className="mt-2 text-green-800">
                                დადასტურების ახალი ბმული გამოგზავნილია.
                            </p>
                        )}
                    </div>
                )}
                <div className="mt-auto flex min-h-16 flex-wrap items-center justify-end gap-4 border-t border-gray-100 pt-5">
                    {recentlySuccessful && (
                        <p role="status" className="text-sm text-green-700">
                            მონაცემები შენახულია.
                        </p>
                    )}
                    <PrimaryButton
                        className="min-h-11 !rounded-lg !text-sm !normal-case !tracking-normal"
                        disabled={processing}
                    >
                        {processing ? 'ინახება…' : 'შენახვა'}
                    </PrimaryButton>
                </div>
            </form>
        </section>
    );
}
