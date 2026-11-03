import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-white pt-6 sm:justify-center sm:pt-0">
            <div>
                <Link href="/" aria-label="Alliance Group — მთავარი გვერდი">
                    <ApplicationLogo alt="" aria-hidden="true" className="h-28 w-auto" />
                </Link>
            </div>

            <div className="mt-6 w-full border border-gray-200 bg-white px-6 py-4 shadow-sm sm:max-w-md sm:rounded-xl">
                {children}
            </div>
        </div>
    );
}
