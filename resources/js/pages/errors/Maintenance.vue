<!-- Wartungsseite (503): Gast = Auth-Karte; eingeloggt = Panel-Layout -->
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
        message?: string | null;
        until?: string | null;
        brandName?: string | null;
        shell?: 'guest' | 'admin' | 'app';
    }>(),
    {
        shell: 'guest',
    },
);

const authCardBgSrc = useAuthCardBgUrl();

const defaultMessage =
    'Wir führen gerade Wartungsarbeiten durch. Bitte versuchen Sie es später erneut.';

const displayMessage = computed(() => (props.message?.trim() ? props.message : defaultMessage));

const untilFormatted = computed(() => {
    if (!props.until) {
        return null;
    }
    try {
        return new Intl.DateTimeFormat('de-DE', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(props.until));
    } catch {
        return null;
    }
});

const breadcrumbs = computed((): BreadcrumbItem[] => {
    if (props.shell === 'admin') {
        return [
            { title: 'Admin', href: '/admin' },
            { title: 'Wartung', href: '#' },
        ];
    }
    if (props.shell === 'app') {
        return [
            { title: 'Dashboard', href: dashboard().url },
            { title: 'Wartung', href: '#' },
        ];
    }
    return [];
});

const primaryDashboardHref = computed(() => (props.shell === 'admin' ? '/admin' : dashboard().url));
</script>

<template>
    <Head title="Wartung" />

    <AdminLayout v-if="shell === 'admin'" :breadcrumbs="breadcrumbs">
        <BCard no-body class="shadow-sm">
            <BCardBody class="p-4">
                <h4 class="mb-3">Wartungsmodus</h4>
                <p v-if="brandName" class="text-muted small mb-2">{{ brandName }}</p>
                <p class="text-muted mb-3">{{ displayMessage }}</p>
                <p v-if="untilFormatted" class="text-muted small mb-3">
                    Voraussichtlich bis: <strong>{{ untilFormatted }}</strong>
                </p>
                <Link :href="primaryDashboardHref" class="btn btn-primary btn-sm">Zum Dashboard</Link>
            </BCardBody>
        </BCard>
    </AdminLayout>

    <AppLayout v-else-if="shell === 'app'" :breadcrumbs="breadcrumbs">
        <BCard no-body class="shadow-sm">
            <BCardBody class="p-4">
                <h4 class="mb-3">Wartungsmodus</h4>
                <p v-if="brandName" class="text-muted small mb-2">{{ brandName }}</p>
                <p class="text-muted mb-3">{{ displayMessage }}</p>
                <p v-if="untilFormatted" class="text-muted small mb-3">
                    Voraussichtlich bis: <strong>{{ untilFormatted }}</strong>
                </p>
                <Link :href="primaryDashboardHref" class="btn btn-primary btn-sm">Zum Dashboard</Link>
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
                                    <h4 class="fw-bold text-dark mt-3">Wartungsmodus</h4>
                                    <p v-if="brandName" class="text-muted small mb-2">{{ brandName }}</p>
                                    <p class="text-muted w-lg-75 mx-auto mb-0">{{ displayMessage }}</p>
                                    <p v-if="untilFormatted" class="text-muted small mt-3 mb-0">
                                        Voraussichtlich bis: <strong>{{ untilFormatted }}</strong>
                                    </p>
                                </div>
                                <Link href="/login" class="btn btn-outline-primary btn-sm">Zur Anmeldung</Link>
                            </BCardBody>
                        </BCard>
                    </BCol>
                </BRow>
            </BContainer>
        </div>
    </div>
</template>
