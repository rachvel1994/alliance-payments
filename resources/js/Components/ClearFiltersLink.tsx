import { Link } from '@inertiajs/react';
import Icon from './Icon';

export default function ClearFiltersLink({ href }: { href: string }) {
    return (
        <Link
            href={href}
            preserveState={false}
            preserveScroll
            aria-label="ფილტრების გასუფთავება"
            title="ფილტრების გასუფთავება"
            className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-500 transition hover:border-brand hover:bg-brand-soft hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
        >
            <Icon name="reset" />
        </Link>
    );
}
