export interface OverviewProps {
    asOf: string;
    portfolio: Record<'clients' | 'contracts' | 'projects' | 'apartments' | 'free' | 'sold', number | null>;
    payments: null | {
        count: number;
        received_GEL: string;
        allocated_GEL: string;
        unallocated_GEL: string;
        unmatched_count: number;
        unmatched_GEL: string;
        matched_count: number;
        recent: { reference: string; payer: string; date: string; amount_GEL: string; matched: boolean; url: string }[];
    };
    financial: null | {
        contract_value_GEL: string;
        paid_GEL: string;
        paid_percent: string;
        remaining_GEL: string;
        overdue_GEL: string;
        overdue_installments: number;
        overdue_clients: number | null;
        states: { status: string; count: number }[];
        debtors: { name: string; amount_GEL: string; url: string }[];
        upcoming: { name: string; date: string; amount_GEL: string; url: string }[];
    };
    monthly: { month: string; received_GEL: string | null; scheduled_GEL: string | null }[];
    projects: { name: string; city: string; free: number; sold: number; url: string }[];
    imports: null | {
        files: number;
        imported: number;
        duplicates: number;
        rejected: number;
        statuses: Record<string, number>;
        recent: { filename: string; status: string; imported: number; rejected: number; url: string }[];
    };
}
