<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminDashboardWidgetShell from '@/components/admin/dashboard/AdminDashboardWidgetShell.vue';

type Data = {
    expiringSubscriptions?: Array<{ site_uuid?: string; site_name?: string; current_period_ends_at?: string }>;
    overdueOrFailedInvoices?: Array<{
        id: number;
        number: string;
        user_id?: number;
        user_name?: string;
        status: string;
        uuid?: string;
    }>;
    openDunningInvoices?: Array<{ id: number; number: string; user_name?: string; max_level: number; uuid?: string }>;
};

defineProps<{ data?: Data | null }>();

const invoiceStatusLabel: Record<string, string> = {
    paid: 'Bezahlt',
    pending: 'Ausstehend',
    draft: 'Entwurf',
    sent: 'Gesendet',
    failed: 'Fehlgeschlagen',
};
</script>

<template>
    <AdminDashboardWidgetShell
        title="Zu erledigen"
        description="Handlungsbedarf: Abos, Rechnungen, Mahnungen"
    >
        <div v-if="data?.expiringSubscriptions?.length" class="mb-3">
            <p class="text-muted small fw-semibold mb-1">Abos (7 Tage)</p>
            <ul class="list-unstyled small mb-0">
                <li v-for="(item, i) in data.expiringSubscriptions" :key="i" class="mb-1 text-truncate">
                    <Link
                        v-if="item.site_uuid"
                        :href="`/admin/sites/${item.site_uuid}`"
                        class="text-primary text-decoration-none"
                    >
                        {{ item.site_name }} – {{ item.current_period_ends_at }}
                    </Link>
                    <span v-else>{{ item.site_name }} – {{ item.current_period_ends_at }}</span>
                </li>
            </ul>
        </div>
        <div v-if="data?.overdueOrFailedInvoices?.length" class="mb-3">
            <p class="text-muted small fw-semibold mb-1">Überfällig / fehlgeschlagen</p>
            <ul class="list-unstyled small mb-0">
                <li v-for="inv in data.overdueOrFailedInvoices" :key="inv.id" class="mb-1">
                    <Link
                        v-if="inv.uuid"
                        :href="`/admin/invoices/${inv.uuid}`"
                        class="text-primary text-decoration-none"
                    >
                        {{ inv.number }}
                    </Link>
                    <span v-else>{{ inv.number }}</span>
                    <span class="text-muted"> · {{ invoiceStatusLabel[inv.status] ?? inv.status }}</span>
                    <Link
                        v-if="inv.user_id"
                        :href="`/admin/customers/${inv.user_id}`"
                        class="text-primary text-decoration-none ms-1"
                    >
                        ({{ inv.user_name }})
                    </Link>
                </li>
            </ul>
        </div>
        <div v-if="data?.openDunningInvoices?.length" class="mb-0">
            <p class="text-muted small fw-semibold mb-1">Mahnungen (Stufe 2+)</p>
            <ul class="list-unstyled small mb-0">
                <li v-for="inv in data.openDunningInvoices" :key="inv.id" class="mb-1">
                    <Link
                        v-if="inv.uuid"
                        :href="`/admin/invoices/${inv.uuid}`"
                        class="text-primary text-decoration-none"
                    >
                        {{ inv.number }} (Stufe {{ inv.max_level }})
                    </Link>
                    <span v-else>{{ inv.number }} (Stufe {{ inv.max_level }})</span>
                    <span v-if="inv.user_name" class="text-muted ms-1">({{ inv.user_name }})</span>
                </li>
            </ul>
        </div>
        <p
            v-if="
                !data?.expiringSubscriptions?.length &&
                    !data?.overdueOrFailedInvoices?.length &&
                    !data?.openDunningInvoices?.length
            "
            class="text-muted small mb-0"
        >
            Keine offenen Punkte.
        </p>
    </AdminDashboardWidgetShell>
</template>
