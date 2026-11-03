import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Link } from '@inertiajs/react';
import Icon from '@/Components/Icon';

export const navigationItems = [
    { name: 'overview.index', label: 'მიმოხილვა', permission: 'overview.view', sections: ['dashboard', 'overview'], group: 'overview', description: 'ფინანსები და პორტფელის სტატისტიკა', icon: 'overview' },
    { name: 'overdue.index', label: 'ვადაგადაცილება', permission: 'clients.view', sections: ['overdue'], group: 'finance', description: 'დავალიანება და გადახდის ვადები', icon: 'overdue' },
    { name: 'clients.index', label: 'კლიენტები', permission: 'clients.view', sections: ['clients'], group: 'sales', description: 'კლიენტები და საკონტაქტო ინფორმაცია', icon: 'clients' },
    { name: 'apartments.index', label: 'ბინები', permission: 'apartments.view', sections: ['apartments', 'projects'], group: 'sales', description: 'პროექტები, თავისუფალი და გაყიდული ბინები', icon: 'apartments' },
    { name: 'contracts.index', label: 'ხელშეკრულებები', permission: 'contracts.view', sections: ['contracts'], group: 'sales', description: 'ხელშეკრულებები და გადახდის გრაფიკები', icon: 'contracts' },
    { name: 'payments.index', label: 'გადახდები', permission: 'payments.view', sections: ['payments'], group: 'finance', description: 'შემოსული თანხები და განაწილება', icon: 'payments' },
    { name: 'imports.index', label: 'იმპორტები', permission: 'imports.view', sections: ['imports'], group: 'finance', description: 'ფაილები და ატვირთვის შედეგები', icon: 'imports' },
    { name: 'admin.users.index', label: 'მომხმარებლები', permission: 'users.manage', sections: ['admin.users'], group: 'administration', description: 'ანგარიშები, როლები და უფლებები', icon: 'clients' },
] as const;

const groups = [
    { key: 'overview', label: 'მიმოხილვა', icon: 'overview' },
    { key: 'sales', label: 'გაყიდვები', icon: 'sales' },
    { key: 'finance', label: 'ფინანსები', icon: 'finance' },
    { key: 'administration', label: 'ადმინისტრირება', icon: 'profile' },
] as const;
type NavigationItem = (typeof navigationItems)[number];

function isActive(item: NavigationItem, currentRoute?: string) {
    return item.sections.some((section) => currentRoute === section || currentRoute?.startsWith(`${section}.`));
}

function navClass(active: boolean, mobile = false) {
    return `relative inline-flex min-h-11 items-center whitespace-nowrap rounded-lg px-3 text-sm font-medium transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 motion-reduce:transition-none ${mobile ? 'w-full' : ''} ${active ? 'bg-brand-soft font-semibold text-brand' : 'text-gray-600 hover:bg-gray-50 hover:text-brand'}`;
}

function ActiveMark({ mobile = false }: { mobile?: boolean }) {
    return <span aria-hidden="true" className={`pointer-events-none absolute rounded-full bg-brand ${mobile ? 'bottom-3 left-0 top-3 w-0.5' : 'bottom-0 left-3 right-3 h-0.5'}`} />;
}

export default function AppNavigation({ items, currentRoute, mobile = false, onNavigate }: {
    items: NavigationItem[];
    currentRoute?: string;
    mobile?: boolean;
    onNavigate?: () => void;
}) {
    return groups.map((group) => {
        const children = items.filter((item) => item.group === group.key);
        if (!children.length) return null;
        if (mobile) return <div key={group.key} className="border-b border-gray-100 py-2 last:border-0">
            <p className="px-3 pb-2 text-xs font-semibold text-gray-500">{group.label}</p>
            <div className="grid gap-1">{children.map((item) => {
                const active = isActive(item, currentRoute);
                return <Link key={item.name} href={route(item.name)} onClick={onNavigate} aria-current={active ? 'page' : undefined} className={navClass(active, true)}>
                    <Icon name={item.icon} className="mr-2.5 h-5 w-5" />{item.label}{active && <ActiveMark mobile />}
                </Link>;
            })}</div>
        </div>;
        if (group.key === 'overview' || group.key === 'administration') {
            const item = children[0];
            const active = isActive(item, currentRoute);
            return <Link key={group.key} href={route(item.name)} aria-current={active ? 'page' : undefined} className={`${navClass(active)} gap-2`}><Icon name={group.icon} />{group.label}{active && <ActiveMark />}</Link>;
        }
        const current = children.find((item) => isActive(item, currentRoute));
        return <Menu as="div" key={group.key} className="relative">
            <MenuButton aria-label={current ? `${group.label} — ${current.label}` : group.label} aria-current={current ? 'true' : undefined} className={`${navClass(Boolean(current))} gap-2`}>
                <Icon name={group.icon} />{group.label}
                <svg aria-hidden="true" className="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round"><path d="m6 8 4 4 4-4" /></svg>
                {current && <ActiveMark />}
            </MenuButton>
            <MenuItems className="absolute left-0 z-50 mt-3 w-80 rounded-xl border border-brand-border/60 bg-white p-1.5 shadow-lg focus:outline-none">
                {children.map((item) => <MenuItem key={item.name}>
                    <Link href={route(item.name)} aria-current={isActive(item, currentRoute) ? 'page' : undefined}
                        className={`flex min-h-11 gap-3 rounded-lg px-3 py-3 text-sm data-[focus]:bg-brand-soft ${isActive(item, currentRoute) ? 'bg-brand-soft text-brand' : 'text-ink'}`}>
                        <Icon name={item.icon} className="mt-0.5 h-5 w-5" /><span><span className="font-medium">{item.label}</span><span className="mt-1 block text-xs text-gray-500">{item.description}</span></span>
                    </Link>
                </MenuItem>)}
            </MenuItems>
        </Menu>;
    });
}
