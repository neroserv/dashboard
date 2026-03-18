/** Rechnungsstatus (DB) → Anzeige für Kundenbereich */

const LABELS_DE: Record<string, string> = {
    draft: 'Entwurf',
    sent: 'Versendet',
    pending: 'Zahlung ausstehend',
    paid: 'Bezahlt',
    cancelled: 'Storniert',
    failed: 'Zahlung fehlgeschlagen',
};

export function invoiceStatusLabelDe(status: string): string {
    const key = String(status).toLowerCase();

    return LABELS_DE[key] ?? status;
}

/**
 * Bootstrap-Badge-Klassen (subtle, wie Admin-Listen).
 */
export function invoiceStatusBadgeClass(status: string): string {
    const base = 'badge rounded-pill px-2 py-1 fw-medium small';
    switch (String(status).toLowerCase()) {
        case 'paid':
            return `${base} bg-success-subtle text-success border border-success border-opacity-25`;
        case 'pending':
        case 'sent':
            return `${base} bg-warning-subtle text-warning border border-warning border-opacity-25`;
        case 'draft':
            return `${base} bg-secondary-subtle text-secondary border border-secondary border-opacity-25`;
        case 'cancelled':
            return `${base} bg-dark-subtle text-dark border border-dark border-opacity-10`;
        case 'failed':
            return `${base} bg-danger-subtle text-danger border border-danger border-opacity-25`;
        default:
            return `${base} bg-light text-muted border`;
    }
}
