<template>
    <DefaultLayout>
        <Head title="Ticket erstellen" />
        <PageBreadcrumb
            title="Ticket erstellen"
            subtitle="Support Tickets"
            subtitle-url="/support"
        />

        <BRow>
            <BCol md="3" class="mb-4">
                <BCard class="mb-3 border-danger">
                    <BCardBody class="d-flex gap-3 align-items-start">
                        <div
                            class="support-aside-icon-wrap rounded-2 bg-danger bg-opacity-25 d-inline-flex flex-shrink-0 align-items-center justify-content-center text-danger"
                            aria-hidden="true"
                        >
                            <span class="d-inline-flex align-items-center justify-content-center lh-0">
                                <Icon icon="moon-stars" width="22" height="22" />
                            </span>
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <h6 class="fw-semibold text-danger">Verzögerter Support in der Nacht</h6>
                            <p class="small text-muted mb-0">
                                Bitte beachte, dass es in der Nacht länger dauern kann, bis wir dein Support-Ticket bearbeiten.
                            </p>
                        </div>
                    </BCardBody>
                </BCard>
                <BCard v-if="discordInviteUrl" class="border-warning">
                    <BCardBody class="d-flex gap-3 align-items-start">
                        <div
                            class="support-aside-icon-wrap rounded-2 bg-warning bg-opacity-25 d-inline-flex flex-shrink-0 align-items-center justify-content-center text-warning-emphasis"
                            aria-hidden="true"
                        >
                            <Icon icon="message-circle" width="22" height="22" />
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <h6 class="fw-semibold">Community-Discord</h6>
                            <p class="small text-muted mb-2">
                                Hier tauschst du dich mit anderen Nutzern aus: Viele helfen sich gegenseitig bei Fragen und
                                Problemen – oft bekommst du so schnell Tipps oder Lösungsideen. Für verbindliche Antworten
                                und alles rund um deinen Account bearbeitet weiterhin unser Support-Team dein Ticket hier.
                            </p>
                            <a
                                :href="discordInviteUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="support-discord-link small d-inline-flex align-items-center gap-1 fw-semibold text-warning-emphasis text-decoration-none"
                            >
                                <Icon icon="external-link" width="16" height="16" class="flex-shrink-0" />
                                Zum Community-Discord
                            </a>
                        </div>
                    </BCardBody>
                </BCard>
            </BCol>
            <BCol md="9">
                <BCard>
                    <BCardHeader class="d-flex align-items-center gap-2">
                        <span class="rounded-circle bg-primary bg-opacity-10 p-2">
                            <Icon icon="plus" class="text-primary" />
                        </span>
                        Neues Support-Ticket erstellen
                    </BCardHeader>
                    <BCardBody>
                        <BAlert v-if="props.hasPartnerPrioritizedSupport" variant="info" show class="mb-3">
                            Über deinen Partner-Status erhältst du für dieses neue Ticket automatisch
                            <strong>Priorisierten Support</strong> (zusätzlich zur gewählten Ticket-Priorität).
                        </BAlert>
                        <BForm @submit.prevent="form.post(store.url())">
                            <BRow>
                                <BCol md="8" class="mb-3">
                                    <BFormGroup label="Betreff" label-for="subject">
                                        <BFormInput
                                            id="subject"
                                            v-model="form.subject"
                                            placeholder="Beschreibe dein Anliegen möglichst genau"
                                            :class="{ 'is-invalid': form.errors.subject }"
                                        />
                                        <div v-if="form.errors.subject" class="invalid-feedback d-block">{{ form.errors.subject }}</div>
                                    </BFormGroup>
                                </BCol>
                                <BCol md="4" class="mb-3">
                                    <BFormGroup label="Kategorie" label-for="ticket_category_id">
                                        <BFormSelect
                                            id="ticket_category_id"
                                            v-model="form.ticket_category_id"
                                            :options="categoryOptions"
                                            :class="{ 'is-invalid': form.errors.ticket_category_id }"
                                        />
                                        <div v-if="form.errors.ticket_category_id" class="invalid-feedback d-block">{{ form.errors.ticket_category_id }}</div>
                                    </BFormGroup>
                                </BCol>
                            </BRow>

                            <BRow>
                                <BCol md="8" class="mb-3">
                                    <BFormGroup label="Betroffener Dienst" label-for="affected_services">
                                        <div ref="serviceSelectRef" class="position-relative support-service-field">
                                            <button
                                                id="affected_services"
                                                type="button"
                                                class="form-control d-flex align-items-center justify-content-between gap-2 text-start support-service-trigger"
                                                :class="{
                                                    'is-invalid': form.errors.affected_services,
                                                    'support-service-trigger--open': serviceSelectOpen,
                                                }"
                                                :aria-expanded="serviceSelectOpen"
                                                aria-haspopup="listbox"
                                                @click="serviceSelectOpen = !serviceSelectOpen"
                                            >
                                                <span class="text-truncate flex-grow-1 min-w-0 text-body">{{
                                                    serviceSelectLabel
                                                }}</span>
                                                <Icon
                                                    icon="chevron-down"
                                                    width="18"
                                                    height="18"
                                                    class="flex-shrink-0 text-body-secondary support-service-chevron"
                                                />
                                            </button>
                                            <div
                                                v-show="serviceSelectOpen"
                                                class="dropdown-menu show support-service-menu shadow-sm py-2 mt-1"
                                                role="listbox"
                                            >
                                                <template v-if="hasAnyServices">
                                                    <template v-for="group in serviceGroups" :key="group.key">
                                                        <h6 class="dropdown-header text-uppercase small fw-semibold mb-0 pb-0">
                                                            {{ group.title }}
                                                        </h6>
                                                        <div
                                                            v-for="item in group.items"
                                                            :key="`${item.type}-${item.id}`"
                                                            class="support-service-option px-2"
                                                        >
                                                            <BFormCheckbox
                                                                :id="`service-${item.type}-${item.id}`"
                                                                class="support-service-checkbox"
                                                                :checked="isSelected(item.type, item.id)"
                                                                @change="
                                                                    (e: Event) =>
                                                                        setService(
                                                                            item.type,
                                                                            item.id,
                                                                            (e.target as HTMLInputElement).checked,
                                                                        )
                                                                "
                                                            >
                                                                {{ item.label }}
                                                            </BFormCheckbox>
                                                        </div>
                                                    </template>
                                                </template>
                                                <p v-else class="dropdown-item-text small text-muted mb-0 px-3 py-2">
                                                    Keine Dienste zugeordnet
                                                </p>
                                            </div>
                                        </div>
                                        <div class="form-text">Optional: Ein oder mehrere Dienste wählen.</div>
                                        <div v-if="form.errors.affected_services" class="invalid-feedback d-block">{{ form.errors.affected_services }}</div>
                                    </BFormGroup>
                                </BCol>
                                <BCol v-if="priorities.length" md="4" class="mb-3">
                                    <BFormGroup label="Priorität" label-for="ticket_priority_id">
                                        <BFormSelect id="ticket_priority_id" v-model="form.ticket_priority_id" :options="priorityOptions" />
                                    </BFormGroup>
                                </BCol>
                            </BRow>

                            <BFormGroup label="Nachricht" label-for="body" class="mb-3">
                                <BFormTextarea
                                    id="body"
                                    v-model="form.body"
                                    rows="6"
                                    placeholder="Beschreibe dein Anliegen..."
                                    :class="{ 'is-invalid': form.errors.body }"
                                />
                                <div v-if="form.errors.body" class="invalid-feedback d-block">{{ form.errors.body }}</div>
                            </BFormGroup>

                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <Link :href="supportIndex.url()">
                                    <BButton type="button" variant="outline-secondary">Abbrechen</BButton>
                                </Link>
                                <BButton type="submit" variant="primary" :disabled="form.processing">
                                    Ticket erstellen
                                </BButton>
                            </div>
                        </BForm>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>
    </DefaultLayout>
</template>

<script setup lang="ts">
import { onClickOutside } from '@vueuse/core';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    BRow,
    BCol,
    BCard,
    BCardHeader,
    BCardBody,
    BForm,
    BFormGroup,
    BFormInput,
    BFormSelect,
    BFormCheckbox,
    BFormTextarea,
    BButton,
    BAlert,
} from 'bootstrap-vue-next';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import PageBreadcrumb from '@/components/PageBreadcrumb.vue';
import Icon from '@/components/wrappers/Icon.vue';
import { dashboard } from '@/routes';
import { index as supportIndex, store } from '@/routes/support';

type ServiceItem = { type: string; id: number; label: string };
type ServicesPayload = {
    websites: ServiceItem[];
    domains: ServiceItem[];
    webspaces: ServiceItem[];
    gameserver: ServiceItem[];
    teamspeak: ServiceItem[];
};
type Category = { id: number; name: string; slug: string };
type Priority = { id: number; name: string; slug: string; color: string | null };

const page = usePage();
const discordInviteUrl = computed(() => (page.props.discordInviteUrl as string | null) ?? null);

const props = defineProps<{
    services: ServicesPayload;
    categories: Category[];
    priorities: Priority[];
    hasPartnerPrioritizedSupport?: boolean;
}>();

type AffectedService = { type: string; id: number };
const form = useForm({
    subject: '',
    body: '',
    ticket_category_id: '' as string | number,
    ticket_priority_id: '' as string | number,
    affected_services: [] as AffectedService[],
});

function isSelected(type: string, id: number): boolean {
    return form.affected_services.some((s) => s.type === type && s.id === id);
}

function setService(type: string, id: number, checked: boolean): void {
    if (checked) {
        form.affected_services = [...form.affected_services, { type, id }];
    } else {
        form.affected_services = form.affected_services.filter((s) => !(s.type === type && s.id === id));
    }
}

const serviceSelectLabel = computed(() => {
    const n = form.affected_services.length;
    if (n === 0) return 'Allgemein / Kein Dienst';
    if (n === 1) {
        const [s] = form.affected_services;
        const group = serviceGroups.value.find((g) => g.items.some((i) => i.type === s.type && i.id === s.id));
        const item = group?.items.find((i) => i.type === s.type && i.id === s.id);
        return item?.label ?? `${n} Dienst`;
    }
    return `${n} Dienste ausgewählt`;
});

const hasAnyServices = computed(() => serviceGroups.value.length > 0);

const serviceSelectOpen = ref(false);
const serviceSelectRef = ref<HTMLElement | null>(null);
onClickOutside(serviceSelectRef, () => {
    serviceSelectOpen.value = false;
});

const serviceGroups = computed(() => {
    const groups: { title: string; key: keyof ServicesPayload; items: ServiceItem[] }[] = [];
    if (props.services.websites?.length) groups.push({ title: 'Websites', key: 'websites', items: props.services.websites });
    if (props.services.domains?.length) groups.push({ title: 'Domains', key: 'domains', items: props.services.domains });
    if (props.services.webspaces?.length) groups.push({ title: 'Webspaces', key: 'webspaces', items: props.services.webspaces });
    if (props.services.gameserver?.length) groups.push({ title: 'Gameserver', key: 'gameserver', items: props.services.gameserver });
    if (props.services.teamspeak?.length) groups.push({ title: 'TeamSpeak-Server', key: 'teamspeak', items: props.services.teamspeak });
    return groups;
});

const categoryOptions = computed(() => [
    { value: '', text: '-- Bitte wählen --' },
    ...props.categories.map((c) => ({ value: c.id, text: c.name })),
]);

const priorityOptions = computed(() => [
    { value: '', text: '-- Bitte wählen --' },
    ...props.priorities.map((p) => ({ value: p.id, text: p.name })),
]);
</script>

<style scoped>
/* Festes Quadrat: Icon-Spalte wächst nicht mit der Text-Höhe mit (kein „Hineinziehen“). */
.support-aside-icon-wrap {
    width: 2.75rem;
    height: 2.75rem;
}

.support-discord-link:hover {
    text-decoration: underline !important;
}

/* Mehrfachauswahl „Betroffener Dienst“: wie Formularfeld + Bootstrap-Dropdown (Hell/Dunkel). */
.support-service-field {
    z-index: 4;
}

.support-service-trigger {
    cursor: pointer;
    background-color: var(--bs-body-bg);
    color: var(--bs-body-color);
}

.support-service-trigger:focus-visible {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.2);
    outline: 0;
}

.support-service-trigger--open {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.15rem rgba(var(--bs-primary-rgb), 0.12);
}

.support-service-chevron {
    transition: transform 0.18s ease;
}

.support-service-trigger--open .support-service-chevron {
    transform: rotate(180deg);
}

.support-service-menu {
    width: 100%;
    max-height: 280px;
    overflow-y: auto;
    z-index: 1055;
}

.support-service-option {
    border-radius: var(--bs-border-radius-sm);
}

.support-service-option:hover {
    background-color: var(--bs-tertiary-bg);
}

.support-service-checkbox :deep(.form-check) {
    margin-bottom: 0;
    padding: 0.35rem 0.5rem;
    min-height: 0;
}

.support-service-checkbox :deep(.form-check-label) {
    font-size: 0.875rem;
    line-height: 1.35;
}
</style>
