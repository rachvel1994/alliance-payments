export type WorkspaceLink = {
    label: string;
    url: string;
    description?: string | null;
    permission?: string;
};

export type WorkspaceValue = string | number | null | WorkspaceLink | WorkspaceLink[];
export type WorkspaceRow = Record<string, WorkspaceValue>;
