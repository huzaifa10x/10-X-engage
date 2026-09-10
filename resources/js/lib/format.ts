export function formatDate(value?: string | null, withTime = true): string {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return withTime
        ? d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
        : d.toLocaleDateString(undefined, { dateStyle: 'medium' });
}

export function timeAgo(value?: string | null): string {
    if (!value) return '—';
    const diff = (Date.now() - new Date(value).getTime()) / 1000;
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}

/** Count {{1}}, {{2}} ... placeholders in a template string. */
export function countVariables(text?: string | null): number {
    if (!text) return 0;
    const matches = text.match(/\{\{\d+\}\}/g);
    return matches ? new Set(matches).size : 0;
}

/** Replace {{n}} placeholders with example values for previews. */
export function fillVariables(text: string, values: string[]): string {
    return text.replace(/\{\{(\d+)\}\}/g, (_, n) => {
        const v = values[Number(n) - 1];
        return v && v.trim() !== '' ? v : `{{${n}}}`;
    });
}
