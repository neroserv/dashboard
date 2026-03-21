<!-- Admin: Support-Tickets-Übersicht -->
<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import {
    BRow,
    BCol,
    BCard,
    BCardBody,
    BCardHeader,
    BCardTitle,
    BTable,
    BButton,
    BBadge,
    BFormSelect,
    BFormGroup,
    BFormInput,
    BFormCheckbox,
} from 'bootstrap-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Icon from '@/components/wrappers/Icon.vue';
import UserAvatarOrInitials from '@/components/UserAvatarOrInitials.vue';
import { dashboard } from '@/routes';
import adminTickets from '@/routes/admin/tickets';
import type { BreadcrumbItem } from '@/types';

type User = { id: number; name: string; email?: string; avatar?: string | null };
type TicketCategory = { id: number; name: string; slug: string };
type TicketPriority = { id: number; name: string; slug: string; color: string | null } | null;

type Ticket = {
    id: number;
    uuid?: string;
    subject: string;
    status: string;
    created_at: string;
    updated_at: string;
    user?: User;
    ticket_category?: TicketCategory;
    ticket_priority?: TicketPriority;
    /** Betroffene Dienste (aus ticket_services), z. B. Domain/Webspace-Namen */
    service_display?: string;
    assigned_to?: User | null;
};

type TableState = {
    search: string;
    include_archived: boolean;
    sort: string;
    direction: string;
    status: string;
    ticket_category_id: string;
    ticket_priority_id: string;
    user_id: string;
    assigned_to: string;
};

type Props = {
    tickets: { data: Ticket[]; links: { url: string | null; label: string; active: boolean }[] };
    categories: TicketCategory[];
    priorities: TicketPriority[];
    admins: User[];
    tableState: TableState;
};

const props = defineProps<Props>();

const page = usePage();
const currentUserId = computed((): number | null => {
    const u = page.props.auth?.user as { id?: number } | undefined;

    return u?.id ?? null;
});

function isAssignedToMe(ticket: Ticket): boolean {
    if (currentUserId.value == null) {
        return false;
    }
    const assignee = ticket.assigned_to;

    return assignee != null && assignee.id === currentUserId.value;
}

function ticketRowClass(item: Ticket | null, type: string): string | null {
    if (type !== 'row' || item == null) {
        return null;
    }

    return isAssignedToMe(item) ? 'ticket-row-assigned-to-me' : null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Admin', href: '/admin' },
    { title: 'Tickets', href: '#' },
];

const statusLabels: Record<string, string> = {
    open: 'Offen',
    in_progress: 'In Bearbeitung',
    waiting_customer: 'Warte auf Kunde',
    resolved: 'Erledigt',
    closed: 'Geschlossen',
};

const filters = reactive({
    search: props.tableState.search ?? '',
    include_archived: props.tableState.include_archived ?? false,
    sort: props.tableState.sort ?? 'updated_at',
    direction: (props.tableState.direction === 'asc' ? 'asc' : 'desc') as 'asc' | 'desc',
    status: props.tableState.status ?? '',
    ticket_category_id: props.tableState.ticket_category_id ?? '',
    ticket_priority_id: props.tableState.ticket_priority_id ?? '',
    user_id: props.tableState.user_id ?? '',
    assigned_to: props.tableState.assigned_to ?? '',
});

watch(
    () => props.tableState,
    (s) => {
        filters.search = s.search ?? '';
        filters.include_archived = s.include_archived ?? false;
        filters.sort = s.sort ?? 'updated_at';
        filters.direction = s.direction === 'asc' ? 'asc' : 'desc';
        filters.status = s.status ?? '';
        filters.ticket_category_id = s.ticket_category_id ?? '';
        filters.ticket_priority_id = s.ticket_priority_id ?? '';
        filters.user_id = s.user_id ?? '';
        filters.assigned_to = s.assigned_to ?? '';
    },
    { deep: true },
);

const sortByModel = computed({
    get() {
        return [{ key: filters.sort, order: filters.direction }];
    },
    set(val: { key: string; order?: 'asc' | 'desc' }[]) {
        const first = val?.[0];
        if (!first?.key || !first.order) {
            return;
        }
        if (filters.sort === first.key && filters.direction === first.order) {
            return;
        }
        filters.sort = first.key;
        filters.direction = first.order;
        applyFilters();
    },
});

function buildQueryParams(): Record<string, string | number> {
    const params: Record<string, string | number> = {};
    const q = filters.search.trim();
    if (q !== '') {
        params.search = q;
    }
    if (filters.include_archived) {
        params.include_archived = 1;
    }
    if (filters.status) {
        params.status = filters.status;
    }
    if (filters.ticket_category_id) {
        params.ticket_category_id = filters.ticket_category_id;
    }
    if (filters.ticket_priority_id) {
        params.ticket_priority_id = filters.ticket_priority_id;
    }
    if (filters.user_id) {
        params.user_id = filters.user_id;
    }
    if (filters.assigned_to) {
        params.assigned_to = filters.assigned_to;
    }
    params.sort = filters.sort;
    params.direction = filters.direction;

    return params;
}

function applyFilters(): void {
    router.get('/admin/tickets', buildQueryParams(), { preserveState: true, preserveScroll: true });
}

function onSearchSubmit(): void {
    applyFilters();
}

function onIncludeArchivedChange(): void {
    applyFilters();
}

const PRIORITY_FALLBACK_COLORS: Record<string, string> = {
    low: '#6b7280',
    niedrig: '#6b7280',
    normal: '#2563eb',
    medium: '#0ea5e9',
    mittel: '#0ea5e9',
    high: '#f97316',
    hoch: '#f97316',
    urgent: '#dc2626',
    kritisch: '#dc2626',
    critical: '#dc2626',
};

function priorityBadgeStyle(priority: NonNullable<TicketPriority>): Record<string, string> {
    const hex =
        priority.color?.trim() ||
        PRIORITY_FALLBACK_COLORS[priority.slug.toLowerCase()] ||
        '#64748b';

    return { backgroundColor: hex, color: '#fff', border: 'none' };
}

function formatUpdatedAt(iso: string): string {
    const d = new Date(iso);
    const date = d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const time = d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit', hour12: false });

    return `${date} ${time}`;
}

const statusOptions = [
    { value: '', text: 'Alle' },
    ...Object.entries(statusLabels).map(([value, text]) => ({ value, text })),
];

const tableFields = [
    { key: 'id', label: 'ID', sortable: true, thClass: 'text-nowrap' },
    { key: 'customer', label: 'Kunde', sortable: true },
    { key: 'subject', label: 'Betreff', sortable: true },
    { key: 'category', label: 'Kategorie', sortable: true },
    { key: 'priority', label: 'Priorität', sortable: true },
    { key: 'site_name', label: 'Site', sortable: true },
    { key: 'status_display', label: 'Status', sortable: true },
    { key: 'assigned_to_name', label: 'Zugewiesen', sortable: true },
    { key: 'created_at', label: 'Erstellt', sortable: true },
    { key: 'updated_at', label: 'Aktualisiert', sortable: true },
    { key: 'actions', label: 'Aktionen', sortable: false, thClass: 'text-end' },
];
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <Head title="Support-Tickets" />

        <BRow>
            <BCol>
                <div class="mb-3">
                    <h4 class="mb-1">Support-Tickets</h4>
                    <p class="text-muted small mb-0">Alle Support-Anfragen verwalten</p>
                </div>

                <BCard no-body class="mb-4">
                    <BCardHeader>
                        <BCardTitle class="mb-0">Suche &amp; Filter</BCardTitle>
                        <p class="text-muted small mb-0 mt-1">
                            Standardmäßig werden geschlossene Tickets nur angezeigt, wenn die Schließung weniger als 24&nbsp;Stunden her ist.
                        </p>
                    </BCardHeader>
                    <BCardBody>
                        <div class="d-flex flex-wrap align-items-end gap-3 mb-3">
                            <BFormGroup label="Suche" label-for="ticket_search" class="mb-0 flex-grow-1" style="min-width: 14rem">
                                <BFormInput
                                    id="ticket_search"
                                    v-model="filters.search"
                                    type="search"
                                    placeholder="ID, Betreff, Kunde, E-Mail, Kategorie, Zugewiesen…"
                                    class="form-control-sm"
                                    @keydown.enter.prevent="onSearchSubmit"
                                />
                            </BFormGroup>
                            <BButton variant="primary" size="sm" class="mb-0" @click="onSearchSubmit">
                                <Icon icon="search" class="me-1" />
                                Suchen
                            </BButton>
                        </div>
                        <BFormCheckbox
                            id="include_archived"
                            v-model="filters.include_archived"
                            class="mb-3"
                            switch
                            @update:model-value="onIncludeArchivedChange"
                        >
                            Alle Tickets durchsuchen (inkl. ältere geschlossene / archivierte)
                        </BFormCheckbox>
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <BFormGroup label="Status" label-for="filter_status" class="mb-0">
                                <BFormSelect
                                    id="filter_status"
                                    v-model="filters.status"
                                    :options="statusOptions"
                                    class="form-select-sm"
                                    style="min-width: 12rem"
                                />
                            </BFormGroup>
                            <BFormGroup label="Kategorie" label-for="filter_category" class="mb-0">
                                <BFormSelect
                                    id="filter_category"
                                    v-model="filters.ticket_category_id"
                                    :options="[{ value: '', text: 'Alle' }, ...categories.map((c) => ({ value: String(c.id), text: c.name }))]"
                                    class="form-select-sm"
                                    style="min-width: 12rem"
                                />
                            </BFormGroup>
                            <BFormGroup label="Priorität" label-for="filter_priority" class="mb-0">
                                <BFormSelect
                                    id="filter_priority"
                                    v-model="filters.ticket_priority_id"
                                    :options="[{ value: '', text: 'Alle' }, ...priorities.map((p) => ({ value: String(p.id), text: p.name }))]"
                                    class="form-select-sm"
                                    style="min-width: 12rem"
                                />
                            </BFormGroup>
                            <BButton variant="primary" @click="applyFilters">Filter anwenden</BButton>
                        </div>
                    </BCardBody>
                </BCard>

                <BCard no-body>
                    <BCardHeader>
                        <BCardTitle class="mb-0">Tickets</BCardTitle>
                        <p class="text-muted small mb-0 mt-1">Sortierung per Klick auf die Spaltenüberschriften (letzte Aktualisierung ist Standard).</p>
                    </BCardHeader>
                    <BCardBody class="p-0">
                        <BTable
                            v-model:sort-by="sortByModel"
                            :items="tickets.data"
                            :fields="tableFields"
                            :tbody-tr-class="ticketRowClass"
                            no-local-sorting
                            striped
                            responsive
                            class="mb-0"
                            show-empty
                            empty-text="Keine Tickets"
                        >
                            <template #cell(id)="row">
                                #{{ row.item.id }}
                            </template>
                            <template #cell(customer)="row">
                                <div v-if="row.item.user" class="d-flex align-items-center gap-2">
                                    <UserAvatarOrInitials
                                        :name="row.item.user.name"
                                        :src="row.item.user.avatar ?? null"
                                        :size="36"
                                        rounded-class="rounded-3"
                                        class="flex-shrink-0"
                                    />
                                    <div class="min-w-0">
                                        <span class="d-block text-truncate">{{ row.item.user.name }}</span>
                                        <span v-if="row.item.user.email" class="small text-muted d-block text-truncate">{{
                                            row.item.user.email
                                        }}</span>
                                    </div>
                                </div>
                                <span v-else>–</span>
                            </template>
                            <template #cell(category)="row">
                                {{ row.item.ticket_category?.name ?? '–' }}
                            </template>
                            <template #cell(priority)="row">
                                <BBadge v-if="row.item.ticket_priority" :style="priorityBadgeStyle(row.item.ticket_priority)">
                                    {{ row.item.ticket_priority.name }}
                                </BBadge>
                                <span v-else>–</span>
                            </template>
                            <template #cell(site_name)="row">
                                {{ row.item.service_display ?? '–' }}
                            </template>
                            <template #cell(status_display)="row">
                                {{ statusLabels[row.item.status] ?? row.item.status }}
                            </template>
                            <template #cell(assigned_to_name)="row">
                                <div class="d-flex align-items-center flex-wrap gap-1">
                                    <span :class="{ 'ticket-assignee-row-name': isAssignedToMe(row.item) }">{{
                                        row.item.assigned_to?.name ?? '–'
                                    }}</span>
                                    <span v-if="isAssignedToMe(row.item)" class="ticket-index-dir-mark">Dir</span>
                                </div>
                            </template>
                            <template #cell(created_at)="row">
                                {{ new Date(row.item.created_at).toLocaleDateString('de-DE') }}
                            </template>
                            <template #cell(updated_at)="row">
                                {{ formatUpdatedAt(row.item.updated_at) }}
                            </template>
                            <template #cell(actions)="row">
                                <Link :href="adminTickets.show(row.item.uuid ?? row.item.id).url">
                                    <BButton variant="outline-primary" size="sm">
                                        <Icon icon="message-circle" />
                                    </BButton>
                                </Link>
                            </template>
                        </BTable>
                        <nav v-if="tickets.links.length > 3" class="d-flex justify-content-center p-3">
                            <ul class="pagination pagination-sm mb-0">
                                <li
                                    v-for="(link, idx) in tickets.links"
                                    :key="idx"
                                    class="page-item"
                                    :class="{ active: link.active, disabled: !link.url }"
                                >
                                    <a v-if="link.url" class="page-link" href="#" @click.prevent="link.url && (window.location.href = link.url)" v-html="link.label" />
                                    <span v-else class="page-link" v-html="link.label" />
                                </li>
                            </ul>
                        </nav>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>
    </AdminLayout>
</template>

<style scoped>
.ticket-row-assigned-to-me td {
    background-color: rgba(79, 70, 229, 0.06);
    box-shadow: inset 3px 0 0 0 #4f46e5;
}

:root.dark .ticket-row-assigned-to-me td {
    background-color: rgba(129, 140, 248, 0.1);
    box-shadow: inset 3px 0 0 0 #818cf8;
}

.ticket-assignee-row-name {
    color: #3730a3;
    font-weight: 700;
}

:root.dark .ticket-assignee-row-name {
    color: #c7d2fe;
}

.ticket-index-dir-mark {
    display: inline-block;
    padding: 0.12rem 0.5rem;
    border-radius: 0.3rem;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    line-height: 1.2;
    text-transform: uppercase;
    background: #4f46e5;
    color: #fff;
}

:root.dark .ticket-index-dir-mark {
    background: #a5b4fc;
    color: #1e1b4b;
}
</style>
