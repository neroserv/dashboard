/**
 * Hosting plans store KeyHelp „unlimited“ quotas as this sentinel (see admin KeyHelp import).
 */
export const WEBSPACE_UNLIMITED_QUOTA = 999_999;

export function formatWebspaceQuotaNumber(value: number): string {
    return value === WEBSPACE_UNLIMITED_QUOTA ? '∞' : String(value);
}
