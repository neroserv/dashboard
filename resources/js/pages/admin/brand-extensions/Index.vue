<!-- Admin: Marken-Erweiterungen -->
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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
    BFormCheckbox,
    BFormTextarea,
    BAlert,
    BModal,
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
    default_nameservers: string[];
    has_api_token: boolean;
};

type InvoiceNinjaPayload = {
    base_url: string;
    has_api_token: boolean;
};

type ChatgptPayload = {
    has_api_key: boolean;
};

type DiscordPayload = {
    guild_id: string;
    customer_role_id: string;
    invite_url: string;
};

type CloudflarePayload = {
    zone_id: string;
    zone_domain: string;
    has_api_token: boolean;
};

type RealtimeRegisterPayload = {
    api_url: string;
    customer_handle: string;
    timeout: number;
    margin_type: string;
    margin_value: number;
    sandbox: boolean;
    has_api_key: boolean;
    has_customer_handle: boolean;
    is_configured: boolean;
    configuration_issues: string[];
    default_nameservers: string[];
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
    chatgpt?: ChatgptPayload;
    discord?: DiscordPayload;
    cloudflare?: CloudflarePayload;
    realtimeregister?: RealtimeRegisterPayload;
};

type PterodactylProductFlags = {
    gaming: boolean;
    gameserver_cloud: boolean;
};

type Props = {
    extension_brand: { id: number; name: string; key: string; logo_url?: string | null };
    brand_extensions: ExtensionItem[];
    pterodactyl_product_flags: PterodactylProductFlags;
    canUpdate: boolean;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Admin', href: '/admin' },
    { title: 'Erweiterungen', href: '#' },
];

const openSkrime = ref(false);
const openRealtimeRegister = ref(false);
const openInvoiceNinja = ref(false);
const openChatgpt = ref(false);
const openDiscord = ref(false);
const openCloudflare = ref(false);
const openPterodactylProducts = ref(false);

const skrimeExt = computed(() => props.brand_extensions.find((e) => e.key === 'skrime') ?? null);
const realtimeRegisterExt = computed(() => props.brand_extensions.find((e) => e.key === 'realtimeregister') ?? null);
const invoiceNinjaExt = computed(() => props.brand_extensions.find((e) => e.key === 'invoice_ninja') ?? null);
const discordExt = computed(() => props.brand_extensions.find((e) => e.key === 'discord') ?? null);
const cloudflareExt = computed(() => props.brand_extensions.find((e) => e.key === 'cloudflare') ?? null);

const installForm = useForm({ extension: '' });
const uninstallForm = useForm({ extension: '' });

const skrimeForm = useForm({
    api_url: '',
    api_token: '',
    timeout: 30,
    margin_type: 'fixed',
    margin_value: 0,
    default_nameservers_text: '',
});

const invoiceNinjaForm = useForm({
    base_url: '',
    api_token: '',
});

const chatgptForm = useForm({
    api_key: '',
});

const discordForm = useForm({
    guild_id: '',
    customer_role_id: '',
    invite_url: '',
});

const cloudflareForm = useForm({
    zone_id: '',
    api_token: '',
    zone_domain: '',
});

const realtimeRegisterForm = useForm({
    api_url: '',
    api_key: '',
    customer_handle: '',
    timeout: 30,
    margin_type: 'fixed',
    margin_value: 0,
    sandbox: false,
    default_nameservers_text: '',
});

const pterodactylProductForm = useForm({
    gaming: true,
    gameserver_cloud: false,
});

function syncSkrimeFormFromProps(): void {
    const sk = props.brand_extensions.find((e) => e.key === 'skrime' && e.installed && e.skrime);
    if (!sk?.skrime) {
        return;
    }
    skrimeForm.api_url = sk.skrime.api_url;
    skrimeForm.timeout = sk.skrime.timeout;
    skrimeForm.margin_type = sk.skrime.margin_type;
    skrimeForm.margin_value = sk.skrime.margin_value;
    skrimeForm.default_nameservers_text = (sk.skrime.default_nameservers ?? []).join('\n');
    skrimeForm.api_token = '';
}

function syncInvoiceNinjaFormFromProps(): void {
    const row = props.brand_extensions.find((e) => e.key === 'invoice_ninja' && e.installed && e.invoice_ninja);
    if (!row?.invoice_ninja) {
        return;
    }
    invoiceNinjaForm.base_url = row.invoice_ninja.base_url;
    invoiceNinjaForm.api_token = '';
}

function syncChatgptFormFromProps(): void {
    chatgptForm.api_key = '';
}

function syncDiscordFormFromProps(): void {
    const row = props.brand_extensions.find((e) => e.key === 'discord' && e.installed && e.discord);
    if (!row?.discord) {
        return;
    }
    discordForm.guild_id = row.discord.guild_id;
    discordForm.customer_role_id = row.discord.customer_role_id;
    discordForm.invite_url = row.discord.invite_url;
}

function syncCloudflareFormFromProps(): void {
    const row = props.brand_extensions.find((e) => e.key === 'cloudflare' && e.installed && e.cloudflare);
    if (!row?.cloudflare) {
        return;
    }
    cloudflareForm.zone_id = row.cloudflare.zone_id;
    cloudflareForm.zone_domain = row.cloudflare.zone_domain;
    cloudflareForm.api_token = '';
}

function syncRealtimeRegisterFormFromProps(): void {
    const row = props.brand_extensions.find((e) => e.key === 'realtimeregister' && e.installed && e.realtimeregister);
    if (!row?.realtimeregister) {
        return;
    }
    realtimeRegisterForm.api_url = row.realtimeregister.api_url;
    realtimeRegisterForm.customer_handle = row.realtimeregister.customer_handle;
    realtimeRegisterForm.timeout = row.realtimeregister.timeout;
    realtimeRegisterForm.margin_type = row.realtimeregister.margin_type;
    realtimeRegisterForm.margin_value = row.realtimeregister.margin_value;
    realtimeRegisterForm.sandbox = row.realtimeregister.sandbox;
    realtimeRegisterForm.api_key = '';
    realtimeRegisterForm.default_nameservers_text = (row.realtimeregister.default_nameservers ?? []).join('\n');
}

function syncPterodactylProductFormFromProps(): void {
    pterodactylProductForm.gaming = props.pterodactyl_product_flags.gaming;
    pterodactylProductForm.gameserver_cloud = props.pterodactyl_product_flags.gameserver_cloud;
}

watch(
    () => props.brand_extensions,
    () => {
        syncSkrimeFormFromProps();
        syncInvoiceNinjaFormFromProps();
        syncChatgptFormFromProps();
        syncDiscordFormFromProps();
        syncCloudflareFormFromProps();
        syncRealtimeRegisterFormFromProps();
    },
    { immediate: true, deep: true },
);

watch(
    () => props.pterodactyl_product_flags,
    () => syncPterodactylProductFormFromProps(),
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
    skrimeForm
        .transform((data) => ({
            api_url: data.api_url,
            api_token: data.api_token,
            timeout: data.timeout,
            margin_type: data.margin_type,
            margin_value: data.margin_value,
            default_nameservers: data.default_nameservers_text
                .split('\n')
                .map((s) => s.trim())
                .filter(Boolean),
        }))
        .put(brandExtensions.skrime.update.url(), { preserveScroll: true });
}

function submitInvoiceNinja(): void {
    invoiceNinjaForm.put(brandExtensions.invoiceNinja.update.url(), { preserveScroll: true });
}

function submitChatgpt(): void {
    chatgptForm.put(brandExtensions.chatgpt.update.url(), { preserveScroll: true });
}

function submitDiscord(): void {
    discordForm.put(brandExtensions.discord.update.url(), { preserveScroll: true });
}

function submitCloudflare(): void {
    cloudflareForm.put(brandExtensions.cloudflare.update.url(), { preserveScroll: true });
}

function submitRealtimeRegister(): void {
    realtimeRegisterForm
        .transform((data) => ({
            api_url: data.api_url,
            api_key: data.api_key,
            customer_handle: data.customer_handle,
            timeout: data.timeout,
            margin_type: data.margin_type,
            margin_value: data.margin_value,
            sandbox: data.sandbox,
            default_nameservers: data.default_nameservers_text
                .split('\n')
                .map((s) => s.trim())
                .filter(Boolean),
        }))
        .put(brandExtensions.realtimeregister.update.url(), { preserveScroll: true });
}

function submitPterodactylProducts(): void {
    pterodactylProductForm.put(brandExtensions.pterodactylProductFlags.update.url(), { preserveScroll: true });
}
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <Head title="Erweiterungen" />

        <BRow>
            <BCol>
                <div class="mb-3 d-flex flex-wrap align-items-center gap-3">
                    <img
                        v-if="extension_brand.logo_url"
                        :src="extension_brand.logo_url"
                        :alt="extension_brand.name"
                        class="rounded border bg-body-secondary flex-shrink-0"
                        width="48"
                        height="48"
                        style="object-fit: contain"
                    />
                    <div>
                        <h4 class="mb-1">Erweiterungen</h4>
                        <p class="text-muted small mb-0">
                            Installierte Erweiterungen für die Marke
                            <strong>{{ extension_brand.name }}</strong>
                            ({{ extension_brand.key }}). Die Auswahl gilt für diesen Admin-Host.
                        </p>
                    </div>
                </div>

                <BRow class="g-3 align-items-stretch">
                    <BCol v-for="ext in brand_extensions" :key="ext.key" cols="12" md="6" lg="4" class="d-flex">
                        <BCard class="shadow-sm h-100 w-100 d-flex flex-column">
                            <BCardHeader class="d-flex align-items-start gap-2 py-3 flex-shrink-0">
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
                                    <span
                                        v-if="
                                            ext.key === 'realtimeregister' &&
                                            ext.installed &&
                                            ext.realtimeregister &&
                                            !ext.realtimeregister.is_configured
                                        "
                                        class="badge bg-warning-subtle text-dark ms-1"
                                    >API-Zugang offen</span>
                                </div>
                            </BCardHeader>
                            <BCardBody class="pt-2 d-flex flex-column flex-grow-1">
                                <p class="text-muted small mb-0">{{ ext.description }}</p>
                                <div class="d-flex flex-wrap gap-2 pt-2">
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
                                        @click="openSkrime = true"
                                    >
                                        <Icon icon="settings" class="me-1" />
                                        Skrime-Einstellungen
                                    </BButton>
                                    <BButton
                                        v-if="ext.installed && ext.key === 'realtimeregister' && canUpdate"
                                        variant="outline-secondary"
                                        size="sm"
                                        @click="openRealtimeRegister = true"
                                    >
                                        <Icon icon="settings" class="me-1" />
                                        Realtime Register
                                    </BButton>
                                    <BButton
                                        v-if="ext.installed && ext.key === 'invoice_ninja' && canUpdate"
                                        variant="outline-secondary"
                                        size="sm"
                                        @click="openInvoiceNinja = true"
                                    >
                                        <Icon icon="settings" class="me-1" />
                                        Einstellungen
                                    </BButton>
                                    <BButton
                                        v-if="ext.installed && ext.key === 'chatgpt' && canUpdate"
                                        variant="outline-secondary"
                                        size="sm"
                                        @click="openChatgpt = !openChatgpt"
                                    >
                                        <Icon icon="settings" class="me-1" />
                                        Einstellungen
                                    </BButton>
                                    <BButton
                                        v-if="ext.installed && ext.key === 'discord' && canUpdate"
                                        variant="outline-secondary"
                                        size="sm"
                                        @click="openDiscord = true"
                                    >
                                        <Icon icon="settings" class="me-1" />
                                        Einstellungen
                                    </BButton>
                                    <BButton
                                        v-if="ext.installed && ext.key === 'cloudflare' && canUpdate"
                                        variant="outline-secondary"
                                        size="sm"
                                        @click="openCloudflare = true"
                                    >
                                        <Icon icon="settings" class="me-1" />
                                        Einstellungen
                                    </BButton>
                                    <BButton
                                        v-if="ext.installed && ext.key === 'pterodactyl' && canUpdate"
                                        variant="outline-secondary"
                                        size="sm"
                                        @click="openPterodactylProducts = true"
                                    >
                                        <Icon icon="settings" class="me-1" />
                                        Pterodactyl-Produkte
                                    </BButton>
                                </div>

                                <div
                                    v-if="ext.key === 'chatgpt' && ext.installed"
                                    v-show="openChatgpt"
                                    class="mt-3 pt-3 border-top"
                                >
                                    <p class="small text-muted mb-2">
                                        OpenAI-API-Schlüssel für diese Marke. Leer lassen, um den bestehenden Schlüssel zu behalten.
                                    </p>
                                    <BFormGroup label="API-Schlüssel" label-for="chatgpt-api-key">
                                        <BFormInput
                                            id="chatgpt-api-key"
                                            v-model="chatgptForm.api_key"
                                            type="password"
                                            autocomplete="new-password"
                                            :placeholder="ext.chatgpt?.has_api_key ? '•••••••• (gesetzt)' : 'Erforderlich für KI-Funktionen'"
                                        />
                                    </BFormGroup>
                                    <BButton
                                        variant="primary"
                                        class="mt-3 px-4 py-2"
                                        :disabled="chatgptForm.processing"
                                        @click="submitChatgpt"
                                    >
                                        Speichern
                                    </BButton>
                                </div>

                            </BCardBody>
                        </BCard>
                    </BCol>
                </BRow>

                <BModal
                    v-model="openSkrime"
                    title="Skrime-Einstellungen"
                    size="xl"
                    centered
                    scrollable
                    no-footer
                >
                    <div class="p-2">
                        <p class="small text-muted mb-3">
                            API-Token leer lassen, um den bestehenden Wert zu behalten. Fallback: .env / globale Konfiguration, wenn nichts gesetzt ist.
                        </p>

                        <BFormGroup label="API-URL" label-for="skrime-api-url">
                            <BFormInput
                                id="skrime-api-url"
                                v-model="skrimeForm.api_url"
                                type="url"
                                autocomplete="off"
                            />
                        </BFormGroup>
                        <BFormGroup label="API-Token" label-for="skrime-api-token">
                            <BFormInput
                                id="skrime-api-token"
                                v-model="skrimeForm.api_token"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="skrimeExt?.skrime?.has_api_token ? '•••••••• (gesetzt)' : 'Optional'"
                            />
                        </BFormGroup>
                        <BFormGroup label="Timeout (Sek.)" label-for="skrime-timeout">
                            <BFormInput
                                id="skrime-timeout"
                                v-model.number="skrimeForm.timeout"
                                type="number"
                                min="1"
                                max="300"
                            />
                        </BFormGroup>
                        <BFormGroup label="Marge-Typ" label-for="skrime-margin-type">
                            <BFormSelect id="skrime-margin-type" v-model="skrimeForm.margin_type">
                                <option value="fixed">Festbetrag (EUR)</option>
                                <option value="percent">Prozent</option>
                            </BFormSelect>
                        </BFormGroup>
                        <BFormGroup label="Marge-Wert" label-for="skrime-margin-value">
                            <BFormInput
                                id="skrime-margin-value"
                                v-model.number="skrimeForm.margin_value"
                                type="number"
                                min="0"
                                step="0.01"
                            />
                        </BFormGroup>
                        <BFormGroup label="Standard-Nameserver (eine Zeile pro Host)" label-for="skrime-default-ns">
                            <BFormTextarea
                                id="skrime-default-ns"
                                v-model="skrimeForm.default_nameservers_text"
                                rows="4"
                                placeholder="ns1.example.com&#10;ns2.example.com"
                            />
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
                </BModal>

                <BModal
                    v-model="openRealtimeRegister"
                    title="Realtime Register"
                    size="xl"
                    centered
                    scrollable
                    no-footer
                >
                    <div class="p-2">
                        <p class="small text-muted mb-3">
                            API-Key leer lassen, um den bestehenden Wert zu behalten (oder global
                            <code class="rounded bg-dark bg-opacity-10 px-1">REALTIMEREGISTER_API_KEY</code> in der .env). Kunden-Handle
                            ebenfalls hier oder per
                            <code class="rounded bg-dark bg-opacity-10 px-1">REALTIMEREGISTER_CUSTOMER_HANDLE</code>. Mit „Sandbox“ wird
                            die Sandbox-Base-URL aus der Konfiguration genutzt (sofern keine eigene API-URL gesetzt ist). Registrierte
                            Sandbox-Domains sind im Portal gekennzeichnet.
                        </p>

                        <BAlert
                            v-if="realtimeRegisterExt?.realtimeregister && !realtimeRegisterExt.realtimeregister.is_configured"
                            variant="warning"
                            show
                            class="small"
                        >
                            <strong>API-Zugang unvollständig</strong> (Pricelist, Domain-Check, Sync u. a.):
                            <ul class="mb-0 ps-3 mt-1">
                                <li
                                    v-for="(issue, idx) in realtimeRegisterExt.realtimeregister.configuration_issues"
                                    :key="idx"
                                >
                                    {{ issue }}
                                </li>
                            </ul>
                        </BAlert>
                        <BAlert
                            v-else-if="realtimeRegisterExt?.realtimeregister?.is_configured"
                            variant="success"
                            show
                            class="small py-2"
                        >
                            Zugangsdaten vollständig für
                            <code class="rounded bg-dark bg-opacity-10 px-1">isConfigured()</code>
                            / API-Aufrufe.
                        </BAlert>

                        <BFormGroup label="API-URL (optional, überschreibt Sandbox/Live-URL)" label-for="rr-api-url">
                            <BFormInput id="rr-api-url" v-model="realtimeRegisterForm.api_url" type="url" autocomplete="off" />
                        </BFormGroup>
                        <BFormGroup label="API-Key (Secret, Header ApiKey …)" label-for="rr-api-key">
                            <BFormInput
                                id="rr-api-key"
                                v-model="realtimeRegisterForm.api_key"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="
                                    realtimeRegisterExt?.realtimeregister?.has_api_key
                                        ? '•••••••• (gesetzt)'
                                        : 'Erforderlich für Live/Sandbox'
                                "
                            />
                        </BFormGroup>
                        <BFormGroup label="Kunden-Handle (Realtime Register)" label-for="rr-customer-handle">
                            <BFormInput
                                id="rr-customer-handle"
                                v-model="realtimeRegisterForm.customer_handle"
                                autocomplete="off"
                                :placeholder="
                                    realtimeRegisterExt?.realtimeregister?.has_customer_handle
                                        ? 'Überschreiben oder leer lassen'
                                        : 'z. B. aus dem RR-Portal oder .env REALTIMEREGISTER_CUSTOMER_HANDLE'
                                "
                            />
                        </BFormGroup>
                        <BFormGroup label="Timeout (Sek.)" label-for="rr-timeout">
                            <BFormInput id="rr-timeout" v-model.number="realtimeRegisterForm.timeout" type="number" min="1" max="300" />
                        </BFormGroup>
                        <BFormGroup label="Marge-Typ" label-for="rr-margin-type">
                            <BFormSelect id="rr-margin-type" v-model="realtimeRegisterForm.margin_type">
                                <option value="fixed">Festbetrag (EUR)</option>
                                <option value="percent">Prozent</option>
                            </BFormSelect>
                        </BFormGroup>
                        <BFormGroup label="Marge-Wert" label-for="rr-margin-value">
                            <BFormInput
                                id="rr-margin-value"
                                v-model.number="realtimeRegisterForm.margin_value"
                                type="number"
                                min="0"
                                step="0.01"
                            />
                        </BFormGroup>
                        <BFormGroup class="mb-2">
                            <BFormCheckbox id="rr-sandbox" v-model="realtimeRegisterForm.sandbox"> Sandbox-API verwenden </BFormCheckbox>
                        </BFormGroup>
                        <BFormGroup label="Standard-Nameserver (eine Zeile pro Host)" label-for="rr-default-ns">
                            <BFormTextarea
                                id="rr-default-ns"
                                v-model="realtimeRegisterForm.default_nameservers_text"
                                rows="4"
                                placeholder="ns1.example.com&#10;ns2.example.com"
                            />
                        </BFormGroup>

                        <BButton
                            variant="primary"
                            class="mt-3 px-4 py-2"
                            :disabled="realtimeRegisterForm.processing"
                            @click="submitRealtimeRegister"
                        >
                            Speichern
                        </BButton>
                    </div>
                </BModal>

                <BModal
                    v-model="openInvoiceNinja"
                    title="Invoice Ninja-Einstellungen"
                    size="xl"
                    centered
                    scrollable
                    no-footer
                >
                    <div class="p-2">
                        <p class="small text-muted mb-3">Nur Speicherung; keine API-Anbindung in dieser Phase.</p>

                        <BFormGroup label="Basis-URL" label-for="in-base-url">
                            <BFormInput id="in-base-url" v-model="invoiceNinjaForm.base_url" type="url" autocomplete="off" />
                        </BFormGroup>
                        <BFormGroup label="API-Token" label-for="in-api-token">
                            <BFormInput
                                id="in-api-token"
                                v-model="invoiceNinjaForm.api_token"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="invoiceNinjaExt?.invoice_ninja?.has_api_token ? '•••••••• (gesetzt)' : 'Optional'"
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
                </BModal>

                <BModal
                    v-model="openDiscord"
                    title="Discord-Einstellungen"
                    size="xl"
                    centered
                    scrollable
                    no-footer
                >
                    <div class="p-2">
                        <p class="small text-muted mb-3">Guild-ID, Kunden-Rollen-ID und Einladungs-URL (OAuth/Bot weiter in .env).</p>

                        <BFormGroup label="Guild-ID" label-for="discord-guild-id">
                            <BFormInput id="discord-guild-id" v-model="discordForm.guild_id" autocomplete="off" />
                        </BFormGroup>
                        <BFormGroup label="Kunden-Rollen-ID" label-for="discord-role-id">
                            <BFormInput id="discord-role-id" v-model="discordForm.customer_role_id" autocomplete="off" />
                        </BFormGroup>
                        <BFormGroup label="Einladungs-URL" label-for="discord-invite-url">
                            <BFormInput id="discord-invite-url" v-model="discordForm.invite_url" type="url" autocomplete="off" />
                        </BFormGroup>

                        <BButton
                            variant="primary"
                            class="mt-3 px-4 py-2"
                            :disabled="discordForm.processing"
                            @click="submitDiscord"
                        >
                            Speichern
                        </BButton>
                    </div>
                </BModal>

                <BModal
                    v-model="openCloudflare"
                    title="Cloudflare-Einstellungen"
                    size="xl"
                    centered
                    scrollable
                    no-footer
                >
                    <div class="p-2">
                        <p class="small text-muted mb-3">
                            Zone-ID, API-Token und Zonendomain für SRV-Subdomains. Token leer lassen, um den bestehenden Wert zu
                            behalten.
                        </p>

                        <BFormGroup label="Zone-ID" label-for="cf-zone-id">
                            <BFormInput id="cf-zone-id" v-model="cloudflareForm.zone_id" autocomplete="off" />
                        </BFormGroup>
                        <BFormGroup label="Zonendomain" label-for="cf-zone-domain">
                            <BFormInput id="cf-zone-domain" v-model="cloudflareForm.zone_domain" autocomplete="off" placeholder="example.com" />
                        </BFormGroup>
                        <BFormGroup label="API-Token" label-for="cf-api-token">
                            <BFormInput
                                id="cf-api-token"
                                v-model="cloudflareForm.api_token"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="cloudflareExt?.cloudflare?.has_api_token ? '•••••••• (gesetzt)' : 'Erforderlich'"
                            />
                        </BFormGroup>

                        <BButton
                            variant="primary"
                            class="mt-3 px-4 py-2"
                            :disabled="cloudflareForm.processing"
                            @click="submitCloudflare"
                        >
                            Speichern
                        </BButton>
                    </div>
                </BModal>

                <BModal
                    v-model="openPterodactylProducts"
                    title="Pterodactyl-Produkte"
                    size="xl"
                    centered
                    scrollable
                    no-footer
                >
                    <div class="p-2">
                        <p class="small text-muted mb-3">
                            Sichtbarkeit im Kundenportal: klassische Game-Server und Gameserver Cloud getrennt schaltbar.
                        </p>

                        <BFormGroup class="mb-2">
                            <BFormCheckbox id="ptero-gaming" v-model="pterodactylProductForm.gaming">
                                Gaming (Game-Server / Pterodactyl)
                            </BFormCheckbox>
                        </BFormGroup>
                        <BFormGroup class="mb-2">
                            <BFormCheckbox id="ptero-cloud" v-model="pterodactylProductForm.gameserver_cloud">
                                Gameserver Cloud
                            </BFormCheckbox>
                        </BFormGroup>

                        <BButton
                            variant="primary"
                            class="mt-3 px-4 py-2"
                            :disabled="pterodactylProductForm.processing"
                            @click="submitPterodactylProducts"
                        >
                            Speichern
                        </BButton>
                    </div>
                </BModal>
            </BCol>
        </BRow>
    </AdminLayout>
</template>
