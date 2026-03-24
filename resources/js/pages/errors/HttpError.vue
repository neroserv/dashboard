<!-- HTTP-Fehler (4xx / 5xx): Gast = Auth-Karte; eingeloggt = Admin- oder Kunden-Panel mit Sidebar/Topbar -->
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { BContainer, BRow, BCol, BCard, BCardBody } from 'bootstrap-vue-next';
import AuthLogo from '@/components/AuthLogo.vue';
import { useAuthCardBgUrl } from '@/composables/useBrandLogos';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        status: number;
        title?: string | null;
        message?: string | null;
        shell?: 'guest' | 'admin' | 'app';
    }>(),
    {
        title: null,
        message: null,
        shell: 'guest',
    },
);

const authCardBgSrc = useAuthCardBgUrl();

const defaultTitles: Record<number, string> = {
    401: 'Nicht angemeldet',
    403: 'Zugriff verweigert',
    404: 'Seite nicht gefunden',
    405: 'Methode nicht erlaubt',
    408: 'Zeitüberschreitung',
    409: 'Konflikt',
    410: 'Nicht mehr verfügbar',
    419: 'Sitzung abgelaufen',
    422: 'Ungültige Daten',
    429: 'Zu viele Anfragen',
    500: 'Serverfehler',
    502: 'Ungültige Antwort',
    503: 'Dienst nicht verfügbar',
    504: 'Gateway-Zeitüberschreitung',
};

const displayTitle = computed(() => props.title?.trim() || defaultTitles[props.status] || 'Fehler');

const displayMessage = computed(() => {
    const m = props.message?.trim();
    if (m) {
        return m;
    }
    if (props.status >= 500) {
        return 'Es ist ein technischer Fehler aufgetreten. Bitte versuchen Sie es später erneut.';
    }
    if (props.status === 404) {
        return 'Die angeforderte Seite existiert nicht oder wurde verschoben.';
    }
    if (props.status === 403) {
        return 'Sie haben keine Berechtigung für diese Aktion.';
    }
    if (props.status === 401) {
        return 'Bitte melden Sie sich an, um fortzufahren.';
    }
    return 'Die Anfrage konnte nicht verarbeitet werden.';
});

const headTitle = computed(() => `${props.status} ${displayTitle.value}`);

const breadcrumbs = computed((): BreadcrumbItem[] => {
    if (props.shell === 'admin') {
        return [
            { title: 'Admin', href: '/admin' },
            { title: displayTitle.value, href: '#' },
        ];
    }
    if (props.shell === 'app') {
        return [
            { title: 'Dashboard', href: dashboard().url },
            { title: displayTitle.value, href: '#' },
        ];
    }
    return [];
});

const primaryDashboardHref = computed(() => (props.shell === 'admin' ? '/admin' : dashboard().url));

const showLoginLink = computed(() => props.shell === 'guest');
</script>

<template>
    <Head :title="headTitle" />

    <AdminLayout v-if="shell === 'admin'" :breadcrumbs="breadcrumbs">
        <BCard no-body class="shadow-sm">
            <BCardBody class="p-4">
                <p class="text-muted small mb-1 font-monospace">{{ status }}</p>
                <h4 class="mb-3">{{ displayTitle }}</h4>
                <p class="text-muted mb-4">{{ displayMessage }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <Link :href="primaryDashboardHref" class="btn btn-primary btn-sm">Zum Dashboard</Link>
                </div>
            </BCardBody>
        </BCard>
    </AdminLayout>

    <AppLayout v-else-if="shell === 'app'" :breadcrumbs="breadcrumbs">
        <BCard no-body class="shadow-sm">
            <BCardBody class="p-4">
                <p class="text-muted small mb-1 font-monospace">{{ status }}</p>
                <h4 class="mb-3">{{ displayTitle }}</h4>
                <p class="text-muted mb-4">{{ displayMessage }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <Link :href="primaryDashboardHref" class="btn btn-primary btn-sm">Zum Dashboard</Link>
                </div>
            </BCardBody>
        </BCard>
    </AppLayout>

    <div v-else class="error-page-guest">
        <div class="position-absolute top-0 end-0">
            <img :src="authCardBgSrc" class="auth-card-bg-img" alt="" />
        </div>
        <div class="position-absolute start-0 bottom-0" style="transform: rotate(180deg)">
            <img :src="authCardBgSrc" class="auth-card-bg-img" alt="" />
        </div>
        <div class="auth-box overflow-hidden align-items-center d-flex">
            <BContainer>
                <BRow class="justify-content-center">
                    <BCol xxl="5" md="6" sm="8">
                        <BCard no-body class="p-4">
                            <BCardBody class="text-center">
                                <div class="auth-brand mb-4">
                                    <AuthLogo />
                                    <p class="text-muted small mb-1 font-monospace">{{ status }}</p>
                                    <h4 class="fw-bold text-dark mt-2">{{ displayTitle }}</h4>
                                    <p class="text-muted w-lg-75 mx-auto mb-0">{{ displayMessage }}</p>
                                </div>
                                <div class="d-flex flex-wrap justify-content-center gap-2">
                                    <Link :href="primaryDashboardHref" class="btn btn-primary btn-sm">Zum Dashboard</Link>
                                    <Link href="/login" class="btn btn-outline-secondary btn-sm">Anmeldung</Link>
                                </div>
                            </BCardBody>
                        </BCard>
                    </BCol>
                </BRow>
            </BContainer>
        </div>
    </div>
</template>
