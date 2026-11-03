import AppNavigation, { navigationItems } from '@/Components/AppNavigation';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Icon from '@/Components/Icon';
import { Disclosure, DisclosureButton, DisclosurePanel, Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';

export default function Authenticated({ header, children }: PropsWithChildren<{ header?: ReactNode }>) {
    const { user, permissions } = usePage().props.auth;
    const links = navigationItems.filter((item) => permissions.includes(item.permission));
    const hasAdministration = permissions.includes('users.manage');
    const currentRoute = route().current();
    const home = links[0]?.name ?? 'profile.edit';
    const initials = user.name.trim().split(/\s+/u).slice(0, 2).map((name) => Array.from(name)[0]).join('').toUpperCase();

    return (
        <div className="min-h-screen bg-surface text-ink">
            <Disclosure as="header" lang="ka" className="sticky top-0 z-40 border-b border-brand-border/60 bg-white">
                {({ open, close }) => <div onKeyDown={(event) => {
                    if (event.key === 'Escape' && open) {
                        event.stopPropagation();
                        close();
                    }
                }}>
                    <a href="#main-content" onClick={() => close()}
                        className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-lg focus:bg-brand focus:px-4 focus:py-3 focus:text-white focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">
                        შიგთავსზე გადასვლა
                    </a>
                    <div className="mx-auto flex h-24 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <Link href={route(home)} aria-label="Alliance Group — მთავარი გვერდი"
                            className="flex min-h-11 shrink-0 items-center gap-3 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-4">
                            <ApplicationLogo alt="" aria-hidden="true" className="h-20 w-auto" />
                            <span className="hidden border-l border-brand-border pl-3 sm:block">
                                <span className="block text-sm font-semibold text-ink">გადახდების მართვა</span>
                                <span className="mt-1 block text-xs text-gray-500">სამუშაო სივრცე</span>
                            </span>
                        </Link>

                        <nav aria-label="მთავარი მენიუ" className={`hidden items-center gap-1 ${hasAdministration ? 'xl:flex' : 'lg:flex'}`}>
                            <AppNavigation items={links} currentRoute={currentRoute} />
                        </nav>

                        <div className="flex shrink-0 items-center gap-2">
                            {links.length > 0 && <DisclosureButton
                                className={`inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-brand-border/60 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 ${hasAdministration ? 'xl:hidden' : 'lg:hidden'}`}
                                aria-label={open ? 'მენიუს დახურვა' : 'მენიუს გახსნა'}>
                                <svg aria-hidden="true" className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} strokeLinecap="round">
                                    <path d={open ? 'M6 6l12 12M6 18L18 6' : 'M4 6h16M4 12h16M4 18h16'} />
                                </svg>
                                მენიუ
                            </DisclosureButton>}

                            <Menu as="div" className="relative lg:ml-2 lg:border-l lg:border-brand-border/60 lg:pl-4">
                                <MenuButton aria-label="მომხმარებლის მენიუ"
                                    className="inline-flex min-h-11 items-center gap-2 rounded-lg p-1.5 text-sm text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">
                                    <span aria-hidden="true" className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-semibold text-brand">{initials}</span>
                                    <span className="hidden max-w-28 truncate text-left font-medium md:block">{user.name}</span>
                                    <svg aria-hidden="true" className="hidden h-4 w-4 text-gray-500 md:block" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round">
                                        <path d="m6 8 4 4 4-4" />
                                    </svg>
                                </MenuButton>
                                <MenuItems className="absolute right-0 z-50 mt-3 w-64 rounded-xl border border-brand-border/60 bg-white p-1.5 shadow-lg focus:outline-none">
                                    <div className="mb-1 border-b border-gray-100 px-3 py-3">
                                        <p className="break-words text-sm font-semibold text-gray-900">{user.name}</p>
                                        <p className="mt-1 break-words text-xs text-gray-500">{user.email}</p>
                                    </div>
                                    <MenuItem>
                                        <Link href={route('profile.edit')} aria-current={currentRoute === 'profile.edit' ? 'page' : undefined}
                                            className="flex min-h-11 items-center gap-2.5 rounded-lg px-3 text-sm text-gray-700 data-[focus]:bg-gray-100 data-[focus]:text-ink"><Icon name="profile" />პროფილი</Link>
                                    </MenuItem>
                                    <MenuItem>
                                        <Link href={route('logout')} method="post" as="button"
                                            className="flex min-h-11 w-full items-center gap-2.5 rounded-lg px-3 text-left text-sm text-red-700 data-[focus]:bg-red-50"><Icon name="logout" />გასვლა</Link>
                                    </MenuItem>
                                </MenuItems>
                            </Menu>
                        </div>
                    </div>

                    <DisclosurePanel className={`max-h-[calc(100dvh-6rem)] overflow-y-auto border-t border-gray-100 ${hasAdministration ? 'xl:hidden' : 'lg:hidden'}`}>
                        <nav aria-label="მობილური მენიუ" className="mx-auto grid max-w-7xl gap-1 px-4 py-3 sm:px-6 lg:px-8">
                            <AppNavigation items={links} currentRoute={currentRoute} mobile onNavigate={() => close()} />
                        </nav>
                    </DisclosurePanel>
                </div>}
            </Disclosure>

            <main id="main-content" tabIndex={-1} className="pb-8 focus:outline-none">
                {header && <div className="mx-auto max-w-7xl px-4 pt-7 sm:px-6 lg:px-8">{header}</div>}
                {children}
            </main>
        </div>
    );
}
