import { computed, onMounted, onUnmounted, ref } from 'vue';

/** Chromium fires this before showing the install mini-infobar; calling preventDefault allows a custom install button. */
export interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform?: string }>;
}

export function usePwaInstall() {
    const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null);
    const isStandalone = ref(false);
    const installBusy = ref(false);

    let mediaQuery: MediaQueryList | null = null;
    let onDisplayModeChange: (() => void) | null = null;
    let onBeforeInstall: ((e: Event) => void) | null = null;

    function refreshStandalone(): void {
        if (typeof window === 'undefined') {
            return;
        }
        const mq = window.matchMedia('(display-mode: standalone)');
        const iosStandalone = (window.navigator as Navigator & { standalone?: boolean }).standalone === true;
        isStandalone.value = mq.matches || iosStandalone === true;
    }

    onMounted(() => {
        if (typeof window === 'undefined') {
            return;
        }

        refreshStandalone();

        mediaQuery = window.matchMedia('(display-mode: standalone)');
        onDisplayModeChange = (): void => {
            refreshStandalone();
        };
        mediaQuery.addEventListener('change', onDisplayModeChange);

        onBeforeInstall = (e: Event): void => {
            e.preventDefault();
            deferredPrompt.value = e as BeforeInstallPromptEvent;
        };
        window.addEventListener('beforeinstallprompt', onBeforeInstall);
    });

    onUnmounted(() => {
        if (mediaQuery !== null && onDisplayModeChange !== null) {
            mediaQuery.removeEventListener('change', onDisplayModeChange);
        }
        if (typeof window !== 'undefined' && onBeforeInstall !== null) {
            window.removeEventListener('beforeinstallprompt', onBeforeInstall);
        }
    });

    const canUseInstallPrompt = computed(
        () => deferredPrompt.value !== null && !isStandalone.value && typeof window !== 'undefined' && 'serviceWorker' in navigator,
    );

    async function promptInstall(): Promise<void> {
        const evt = deferredPrompt.value;
        if (!evt) {
            return;
        }
        installBusy.value = true;
        try {
            await evt.prompt();
            await evt.userChoice;
        } finally {
            deferredPrompt.value = null;
            installBusy.value = false;
            refreshStandalone();
        }
    }

    return {
        isStandalone,
        installBusy,
        canUseInstallPrompt,
        promptInstall,
        refreshStandalone,
    };
}
