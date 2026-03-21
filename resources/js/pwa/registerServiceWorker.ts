/**
 * Registers the app service worker on every visit so the site meets PWA install criteria
 * (manifest + SW). Push subscription remains optional on the settings page.
 */
export function registerPwaServiceWorker(): void {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
        return;
    }

    void navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
        // Non-fatal (offline, unsupported context, etc.)
    });
}
