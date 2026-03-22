<!-- Admin: Marken-Erweiterungen -->
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import {
    BRow,
    BCol,
    BCard,
    BCardBody,
    BCardHeader,
    BCardTitle,
    BButton,
    BFormGroup,
    BFormInput,
    BFormSelect,
} from 'bootstrap-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Icon from '@/components/wrappers/Icon.vue';
import brandExtensions from '@/routes/admin/brand-extensions';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type SkrimePayload = {
    api_url: string;
    timeout: number;
    margin_type: string;
    margin_value: number;
    has_api_token: boolean;
};

type InvoiceNinjaPayload = {
    base_url: string;
    has_api_token: boolean;
};

type ExtensionItem = {
    key: string;
    label: string;
    description: string;
    icon: string;
    installed: boolean;
    installed_at: string | null;
    skrime?: SkrimePayload;
    invoice_ninja?: InvoiceNinjaPayload;
};

type Props = {
    brand: { id: number; name: string; key: string; logo_url?: string | null };
    extensions: ExtensionItem[];
    canUpdate: boolean;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Admin', href: '/admin' },
    { title: 'Erweiterungen', href: '#' },
];

const openSkrime = ref(false);
const openInvoiceNinja = ref(false);

const installForm = useForm({ extension: '' });
const uninstallForm = useForm({ extension: '' });

const skrimeForm = useForm({
    api_url: '',
    api_token: '',
    timeout: 30,
    margin_type: 'fixed',
    margin_value: 0,
});

const invoiceNinjaForm = useForm({
    base_url: '',
    api_token: '',
});

function syncSkrimeFormFromProps(): void {
    const sk = props.extensions.find((e) => e.key === 'skrime' && e.installed && e.skrime);
    if (!sk?.skrime) {
        return;
    }
    skrimeForm.api_url = sk.skrime.api_url;
    skrimeForm.timeout = sk.skrime.timeout;
    skrimeForm.margin_type = sk.skrime.margin_type;
    skrimeForm.margin_value = sk.skrime.margin_value;
    skrimeForm.api_token = '';
}

function syncInvoiceNinjaFormFromProps(): void {
    const row = props.extensions.find((e) => e.key === 'invoice_ninja' && e.installed && e.invoice_ninja);
    if (!row?.invoice_ninja) {
        return;
    }
    invoiceNinjaForm.base_url = row.invoice_ninja.base_url;
    invoiceNinjaForm.api_token = '';
}

watch(
    () => props.extensions,
    () => {
        syncSkrimeFormFromProps();
        syncInvoiceNinjaFormFromProps();
    },
    { immediate: true, deep: true },
);

function install(key: string): void {
    if (!props.canUpdate) {
        return;
    }
    installForm.extension = key;
    installForm.post(brandExtensions.install.url(), { preserveScroll: true });
}

function uninstall(key: string): void {
    if (!props.canUpdate) {
        return;
    }
    if (!confirm('Erweiterung wirklich deinstallieren?')) {
        return;
    }
    uninstallForm.extension = key;
    uninstallForm.post(brandExtensions.uninstall.url(), { preserveScroll: true });
}

function submitSkrime(): void {
    skrimeForm.put(brandExtensions.skrime.update.url(), { preserveScroll: true });
}

function submitInvoiceNinja(): void {
    invoiceNinjaForm.put(brandExtensions.invoiceNinja.update.url(), { preserveScroll: true });
}
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <Head title="Erweiterungen" />

        <BRow>
            <BCol>
                <div class="mb-3 d-flex flex-wrap align-items-center gap-3">
                    <img
                        v-if="brand.logo_url"
                        :src="brand.logo_url"
                        :alt="brand.name"
                        class="rounded border bg-body-secondary flex-shrink-0"
                        width="48"
                        height="48"
                        style="object-fit: contain"
                    />
                    <div>
                        <h4 class="mb-1">Erweiterungen</h4>
                        <p class="text-muted small mb-0">
                            Installierte Erweiterungen für die Marke
                            <strong>{{ brand.name }}</strong>
                            ({{ brand.key }}). Die Auswahl gilt für diesen Admin-Host.
                        </p>
                    </div>
                </div>

                <BRow class="g-3">
                    <BCol v-for="ext in extensions" :key="ext.key" cols="12" md="6" lg="4">
                        <BCard class="shadow-sm">
                            <BCardHeader class="d-flex align-items-start gap-2 py-3">
                                <img
                                    :src="ext.icon"
                                    :alt="ext.label"
                                    class="rounded flex-shrink-0 bg-body-secondary"
                                    width="48"
                                    height="48"
                                    style="object-fit: contain"
                                    loading="lazy"
                                />
                                <div class="flex-grow-1 min-w-0">
                                    <BCardTitle tag="h6" class="mb-1">{{ ext.label }}</BCardTitle>
                                    <span v-if="ext.installed" class="badge bg-success-subtle text-success">Installiert</span>
                                    <span v-else class="badge bg-secondary-subtle text-secondary">Nicht installiert</span>
                                </div>
                            </BCardHeader>
                            <BCardBody class="pt-0">
                                <p class="text-muted small mb-3">{{ ext.description }}</p>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <BButton
                                        v-if="!ext.installed && canUpdate"
                                        variant="primary"
                                        size="sm"
                                        :disabled="installForm.processing"
                                        @click="install(ext.key)"
                                    >
                                        <Icon icon="plus" class="me-1" />
                                        Installieren
                                    </BButton>
                                    <BButton
                                        v-if="ext.installed && canUpdate"
                                        variant="outline-danger"
                                        size="sm"
                                        :disabled="uninstallForm.processing"
                                        @click="uninstall(ext.key)"
                                    >
                                        Deinstallieren
                                    </BButton>
                                    <BButton
                                        v-if="ext.installed && ext.key === 'skrime' && canUpdate"
                                        variant="outline-secondary"
                                        size="sm"
                                        @click="openSkrime = !openSkrime"
                                    >
                                        <Icon icon="settings" class="me-1" />
                                        Skrime-Einstellungen
                                    </BButton>
                                    <BButton
                                        v-if="ext.installed && ext.key === 'invoice_ninja' && canUpdate"
                                        variant="outline-secondary"
                                        size="sm"
                                        @click="openInvoiceNinja = !openInvoiceNinja"
                                    >
                                        <Icon icon="settings" class="me-1" />
                                        Einstellungen
                                    </BButton>
                                </div>

                                <div
                                    v-if="ext.key === 'skrime' && ext.installed"
                                    v-show="openSkrime"
                                    class="mt-3 pt-3 border-top"
                                >
                                    <p class="small text-muted mb-2">
                                        API-Token leer lassen, um den bestehenden Wert zu behalten. Fallback: .env /
                                        globale Konfiguration, wenn nichts gesetzt ist.
                                    </p>
                                    <BFormGroup label="API-URL" label-for="skrime-api-url">
                                        <BFormInput id="skrime-api-url" v-model="skrimeForm.api_url" type="url" autocomplete="off" />
                                    </BFormGroup>
                                    <BFormGroup label="API-Token" label-for="skrime-api-token">
                                        <BFormInput
                                            id="skrime-api-token"
                                            v-model="skrimeForm.api_token"
                                            type="password"
                                            autocomplete="new-password"
                                            :placeholder="ext.skrime?.has_api_token ? '•••••••• (gesetzt)' : 'Optional'"
                                        />
                                    </BFormGroup>
                                    <BFormGroup label="Timeout (Sek.)" label-for="skrime-timeout">
                                        <BFormInput id="skrime-timeout" v-model.number="skrimeForm.timeout" type="number" min="1" max="300" />
                                    </BFormGroup>
                                    <BFormGroup label="Marge-Typ" label-for="skrime-margin-type">
                                        <BFormSelect id="skrime-margin-type" v-model="skrimeForm.margin_type">
                                            <option value="fixed">Festbetrag (EUR)</option>
                                            <option value="percent">Prozent</option>
                                        </BFormSelect>
                                    </BFormGroup>
                                    <BFormGroup label="Marge-Wert" label-for="skrime-margin-value">
                                        <BFormInput id="skrime-margin-value" v-model.number="skrimeForm.margin_value" type="number" min="0" step="0.01" />
                                    </BFormGroup>
                                    <BButton
                                        variant="primary"
                                        class="mt-3 px-4 py-2"
                                        :disabled="skrimeForm.processing"
                                        @click="submitSkrime"
                                    >
                                        Speichern
                                    </BButton>
                                </div>

                                <div
                                    v-if="ext.key === 'invoice_ninja' && ext.installed"
                                    v-show="openInvoiceNinja"
                                    class="mt-3 pt-3 border-top"
                                >
                                    <p class="small text-muted mb-2">Nur Speicherung; keine API-Anbindung in dieser Phase.</p>
                                    <BFormGroup label="Basis-URL" label-for="in-base-url">
                                        <BFormInput id="in-base-url" v-model="invoiceNinjaForm.base_url" type="url" autocomplete="off" />
                                    </BFormGroup>
                                    <BFormGroup label="API-Token" label-for="in-api-token">
                                        <BFormInput
                                            id="in-api-token"
                                            v-model="invoiceNinjaForm.api_token"
                                            type="password"
                                            autocomplete="new-password"
                                            :placeholder="ext.invoice_ninja?.has_api_token ? '•••••••• (gesetzt)' : 'Optional'"
                                        />
                                    </BFormGroup>
                                    <BButton
                                        variant="primary"
                                        class="mt-3 px-4 py-2"
                                        :disabled="invoiceNinjaForm.processing"
                                        @click="submitInvoiceNinja"
                                    >
                                        Speichern
                                    </BButton>
                                </div>
                            </BCardBody>
                        </BCard>
                    </BCol>
                </BRow>
            </BCol>
        </BRow>
    </AdminLayout>
</template>
