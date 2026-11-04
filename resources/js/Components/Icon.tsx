import { SVGProps } from 'react';

const paths = {
    reset: 'M3 10a9 9 0 1 1 2.6 8.4M3 4v6h6',
    overview: 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
    clients:
        'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
    apartments: 'M3 21h18M5 21V3h14v18M9 7h1M14 7h1M9 11h1M14 11h1M10 21v-6h4v6',
    contracts: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8ZM14 2v6h6M8 13h8M8 17h5',
    payments: 'M3 5h18v14H3zM3 9h18M7 14h3M16 14h1',
    imports: 'M12 16V3M7 8l5-5 5 5M4 14v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6',
    overdue:
        'M12 8v5M12 17h.01M10.3 3.9 1.8 18.5A1.7 1.7 0 0 0 3.3 21h17.4a1.7 1.7 0 0 0 1.5-2.5L13.7 3.9a2 2 0 0 0-3.4 0Z',
    sales: 'M3 10l9-7 9 7M5 9v12h14V9M9 21v-8h6v8',
    finance: 'M3 21h18M5 17v-5M10 17V7M15 17v-7M20 17V3',
    arrow: 'M5 12h14M13 6l6 6-6 6',
    calendar: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z',
    profile: 'M20 21v-2a7 7 0 0 0-14 0v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
    logout: 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
    eye: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    eyeOff: 'm3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3 4M6.5 6.5A21 21 0 0 0 2 12s3.5 7 10 7a13 13 0 0 0 5.5-1.5M9.9 9.9a3 3 0 0 0 4.2 4.2',
} as const;
export type IconName = keyof typeof paths;

export default function Icon({ name, className = 'h-5 w-5', ...props }: SVGProps<SVGSVGElement> & { name: IconName }) {
    return (
        <svg
            aria-hidden="true"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.6}
            strokeLinecap="round"
            strokeLinejoin="round"
            className={`shrink-0 ${className}`}
            {...props}
        >
            <path d={paths[name]} />
        </svg>
    );
}
