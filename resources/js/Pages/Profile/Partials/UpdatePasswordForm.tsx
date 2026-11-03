import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PasswordInput from '@/Components/PasswordInput';
import PrimaryButton from '@/Components/PrimaryButton';
import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';

export default function UpdatePasswordForm() {
    const user = usePage().props.auth.user;
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const updatePassword: FormEventHandler = (event) => {
        event.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }
                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section
            aria-labelledby="profile-password-title"
            className="flex min-w-0 flex-col border-t border-gray-200 p-5 sm:p-8 lg:border-l lg:border-t-0"
        >
            <h2 id="profile-password-title" className="mb-6 text-base font-semibold text-ink">
                პაროლის შეცვლა
            </h2>
            <form onSubmit={updatePassword} className="flex flex-1 flex-col gap-6">
                <input type="hidden" name="username" autoComplete="username" value={user.email} />
                <div className="space-y-5">
                    <div>
                        <InputLabel htmlFor="current-password" value="მიმდინარე პაროლი" />
                        <div className="mt-2">
                            <PasswordInput
                                id="current-password"
                                name="current_password"
                                ref={currentPasswordInput}
                                value={data.current_password}
                                onChange={(event) => setData('current_password', event.target.value)}
                                required
                                autoComplete="current-password"
                                aria-invalid={Boolean(errors.current_password)}
                                aria-describedby={errors.current_password ? 'current-password-error' : undefined}
                            />
                        </div>
                        <InputError
                            id="current-password-error"
                            role="alert"
                            message={errors.current_password}
                            className="mt-2"
                        />
                    </div>
                    <div>
                        <InputLabel htmlFor="new-password" value="ახალი პაროლი" />
                        <div className="mt-2">
                            <PasswordInput
                                id="new-password"
                                name="password"
                                ref={passwordInput}
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                required
                                autoComplete="new-password"
                                aria-invalid={Boolean(errors.password)}
                                aria-describedby={`password-hint${errors.password ? ' new-password-error' : ''}`}
                            />
                        </div>
                        <p id="password-hint" className="mt-2 text-xs leading-5 text-gray-500">
                            გამოიყენე მინიმუმ 8 სიმბოლო.
                        </p>
                        <InputError id="new-password-error" role="alert" message={errors.password} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="password-confirmation" value="ახალი პაროლი განმეორებით" />
                        <div className="mt-2">
                            <PasswordInput
                                id="password-confirmation"
                                name="password_confirmation"
                                value={data.password_confirmation}
                                onChange={(event) => setData('password_confirmation', event.target.value)}
                                required
                                autoComplete="new-password"
                                aria-invalid={Boolean(errors.password_confirmation)}
                                aria-describedby={
                                    errors.password_confirmation ? 'password-confirmation-error' : undefined
                                }
                            />
                        </div>
                        <InputError
                            id="password-confirmation-error"
                            role="alert"
                            message={errors.password_confirmation}
                            className="mt-2"
                        />
                    </div>
                </div>
                <div className="mt-auto flex min-h-16 flex-wrap items-center justify-end gap-4 border-t border-gray-100 pt-5">
                    {recentlySuccessful && (
                        <p role="status" className="text-sm text-green-700">
                            პაროლი განახლებულია.
                        </p>
                    )}
                    <PrimaryButton
                        className="min-h-11 !rounded-lg !text-sm !normal-case !tracking-normal"
                        disabled={processing}
                    >
                        {processing ? 'ინახება…' : 'პაროლის შეცვლა'}
                    </PrimaryButton>
                </div>
            </form>
        </section>
    );
}
