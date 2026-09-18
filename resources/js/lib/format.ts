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

export function formatTime(value?: string | null): string {
    if (!value) return '';
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? '' : d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

/** "Today", "Yesterday" or a date — used for chat day separators. */
export function formatDayLabel(value: string): string {
    const d = new Date(value);
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    const same = (a: Date, b: Date) => a.toDateString() === b.toDateString();
    if (same(d, today)) return 'Today';
    if (same(d, yesterday)) return 'Yesterday';
    return d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: d.getFullYear() !== today.getFullYear() ? 'numeric' : undefined });
}

/** "5h 12m" style countdown from seconds. */
export function formatCountdown(seconds: number): string {
    if (seconds <= 0) return '0m';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (h > 0) return `${h}h ${m}m`;
    if (m > 0) return `${m}m`;
    return `${seconds}s`;
}

/** Relative time for conversation lists: "12:40", "Yesterday", "Mon", "3 Sep". */
export function formatListTime(value?: string | null): string {
    if (!value) return '';
    const d = new Date(value);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) return formatTime(value);
    const diffDays = Math.floor((now.getTime() - d.getTime()) / 86400000);
    if (diffDays < 1) return 'Yesterday';
    if (diffDays < 7) return d.toLocaleDateString(undefined, { weekday: 'short' });
    return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}
