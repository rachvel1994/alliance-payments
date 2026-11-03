export type WorkspaceLink = {
    label: string;
    url: string;
    description?: string | null;
    permission?: string;
};

export type WorkspaceValue = string | number | null | WorkspaceLink | WorkspaceLink[];
export type WorkspaceRow = Record<string, WorkspaceValue>;

export type WorkspacePaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type WorkspaceSearch = {
    q: string;
    status: string;
    project_id: string;
    client_id: string;
    as_of: string;
    sort: string;
    direction: string;
    import_status: string;
    client_match: string;
};
