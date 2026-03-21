function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
}

export async function registerServiceWorker(): Promise<ServiceWorkerRegistration | null> {
    if (!('serviceWorker' in navigator)) {
        return null;
    }

    return navigator.serviceWorker.register('/sw.js', { scope: '/' });
}

export async function subscribeWithVapid(vapidPublicKey: string): Promise<PushSubscription | null> {
    const registration = await registerServiceWorker();
    if (!registration) {
        return null;
    }

    const existing = await registration.pushManager.getSubscription();
    if (existing) {
        return existing;
    }

    const applicationServerKey = urlBase64ToUint8Array(vapidPublicKey);

    return registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey,
    });
}

export async function pushSubscribeRequest(subscription: PushSubscription): Promise<Response> {
    const json = subscription.toJSON();

    return fetch('/api/push/subscribe', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            endpoint: json.endpoint,
            keys: json.keys,
            content_encoding: null,
        }),
    });
}

export async function pushUnsubscribeRequest(endpoint: string): Promise<Response> {
    return fetch('/api/push/unsubscribe', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ endpoint }),
    });
}

export async function pushPreferencesRequest(payload: {
    master_enabled: boolean;
    sync_with_email: boolean;
    types: Record<string, boolean>;
}): Promise<Response> {
    return fetch('/api/push/preferences', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
    });
}

export function pushSupported(): boolean {
    return typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window;
}
