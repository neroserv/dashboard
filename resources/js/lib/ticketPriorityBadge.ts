export type TicketPriorityLike = {
    slug: string;
    color?: string | null;
};

/**
 * Normalize #rgb / #rrggbb for luminance; returns null if invalid.
 */
function parseHex(input: string | null | undefined): string | null {
    if (input == null || typeof input !== 'string') {
        return null;
    }
    const t = input.trim();
    if (t === '') {
        return null;
    }
    if (/^#[0-9a-fA-F]{6}$/.test(t)) {
        return t.toLowerCase();
    }
    if (/^#[0-9a-fA-F]{3}$/.test(t)) {
        const r = t[1];
        const g = t[2];
        const b = t[3];

        return `#${r}${r}${g}${g}${b}${b}`.toLowerCase();
    }

    return null;
}

function relativeLuminance(hex: string): number {
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;
    const lin = (c: number): number => (c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));

    const rs = lin(r);
    const gs = lin(g);
    const bs = lin(b);

    return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
}

/**
 * Distinct, fixed hues per typical slug so priorities are easy to tell apart at a glance.
 * (DB `color` still wins when set and valid.)
 */
const SLUG_COLORS: Record<string, string> = {
    low: '#475569',
    niedrig: '#475569',
    'sehr-niedrig': '#64748b',
    trivial: '#64748b',
    minor: '#6366f1',
    normal: '#1d4ed8',
    standard: '#2563eb',
    medium: '#0e7490',
    mittel: '#0e7490',
    moderate: '#0369a1',
    high: '#c2410c',
    hoch: '#c2410c',
    'sehr-hoch': '#9a3412',
    important: '#b45309',
    urgent: '#b91c1c',
    kritisch: '#991b1b',
    critical: '#991b1b',
    highest: '#7f1d1d',
    emergency: '#450a0a',
    sofort: '#991b1b',
    p1: '#991b1b',
    p2: '#c2410c',
    p3: '#a16207',
    p4: '#1d4ed8',
    p5: '#475569',
};

function fallbackColorForSlug(slug: string): string {
    const s = slug.toLowerCase();

    return SLUG_COLORS[s] ?? '#475569';
}

function contrastTextForBackground(bgHex: string): string {
    return relativeLuminance(bgHex) > 0.55 ? '#0f172a' : '#ffffff';
}

/**
 * Inline styles for a priority BBadge (background + readable text).
 */
export function ticketPriorityBadgeStyle(priority: TicketPriorityLike): Record<string, string> {
    const fromDb = parseHex(priority.color ?? undefined);
    const bg = fromDb ?? fallbackColorForSlug(priority.slug);
    const fg = contrastTextForBackground(bg);

    return {
        backgroundColor: bg,
        color: fg,
        border: 'none',
        fontWeight: '600',
    };
}

/**
 * BBadge props: bootstrap-vue-next defaults to `variant="secondary"`; `text-bg-*` utilities use
 * `!important` and override inline colors from `ticketPriorityBadgeStyle`.
 */
export function ticketPriorityBadgeAttrs(priority: TicketPriorityLike): {
    variant: null;
    style: Record<string, string>;
} {
    return {
        variant: null,
        style: ticketPriorityBadgeStyle(priority),
    };
}
