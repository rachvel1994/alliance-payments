import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import { DialogDescription, DialogTitle } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

export default function UserActions({ user }: { user: { id: number; name: string; email: string } }) {
    const isSelf = usePage().props.auth.user.id === user.id;
    const [open, setOpen] = useState(false);
    const form = useForm<Record<string, string>>({});
    const close = () => {
        setOpen(false);
        form.clearErrors();
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.delete(route('admin.users.destroy', user.id), { preserveScroll: true, onSuccess: close });
    };

    return (
        <>
            <div className="inline-flex items-center gap-3 whitespace-nowrap">
                <Link
                    href={route('admin.users.edit', user.id)}
                    className="inline-flex min-h-11 items-center rounded text-sm font-medium text-sky-700 hover:text-sky-900 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-600"
                >
                    რედაქტირება
                </Link>
                {!isSelf && (
                    <>
                        <span aria-hidden="true" className="text-gray-300">
                            |
                        </span>
                        <button
                            type="button"
                            onClick={() => setOpen(true)}
                            className="inline-flex min-h-11 items-center rounded text-sm font-medium text-red-700 hover:text-red-900 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
                        >
                            წაშლა
                        </button>
                    </>
                )}
            </div>
            <Modal show={open} onClose={close} closeable={!form.processing} maxWidth="lg">
                <form onSubmit={submit} className="p-5 sm:p-6">
                    <DialogTitle as="h2" className="text-lg font-semibold text-ink">
                        მომხმარებლის წაშლა
                    </DialogTitle>
                    <DialogDescription className="mt-3 text-sm leading-6 text-gray-600">
                        წაიშლება {user.name}-ის ანგარიში ({user.email}). მოქმედება შეუქცევადია.
                    </DialogDescription>
                    <InputError role="alert" message={form.errors.user} className="mt-4" />
                    <div className="mt-6 flex flex-wrap justify-end gap-3">
                        <SecondaryButton
                            className="min-h-11 !text-sm !normal-case !tracking-normal"
                            onClick={close}
                            disabled={form.processing}
                        >
                            გაუქმება
                        </SecondaryButton>
                        <DangerButton
                            className="min-h-11 !text-sm !normal-case !tracking-normal"
                            disabled={form.processing}
                        >
                            {form.processing ? 'იშლება…' : 'მომხმარებლის წაშლა'}
                        </DangerButton>
                    </div>
                </form>
            </Modal>
        </>
    );
}
