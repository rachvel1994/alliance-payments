import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="ელ. ფოსტის დადასტურება" />

            <div className="mb-4 text-sm text-gray-600">
                ელ. ფოსტის დასადასტურებლად გახსენი წერილში მითითებული ბმული. თუ წერილი არ მიგიღია, მოითხოვე ხელახლა.
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    დადასტურების ახალი ბმული გამოგეგზავნა ანგარიშის ელ. ფოსტაზე.
                </div>
            )}

            <form onSubmit={submit} noValidate>
                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <PrimaryButton disabled={processing}>ბმულის ხელახლა გაგზავნა</PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2"
                    >
                        გასვლა
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
