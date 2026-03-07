<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Copy,
    ExternalLink,
    KeyRound,
    Pencil,
    LayoutDashboard,
    Terminal,
    FolderOpen,
    Archive,
    Calendar,
    Power,
    PowerOff,
    RotateCcw,
    Loader2,
    Cpu,
    HardDrive,
    MemoryStick,
    Network,
} from 'lucide-vue-next';
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import AutoRenewModal from '@/components/AutoRenewModal.vue';
import PaymentMethodModal from '@/components/PaymentMethodModal.vue';
import GamingAccountSidebar from '@/components/gaming-accounts/GamingAccountSidebar.vue';
import GamingAccountOverviewTab from '@/components/gaming-accounts/GamingAccountOverviewTab.vue';
import GamingAccountAccessTab from '@/components/gaming-accounts/GamingAccountAccessTab.vue';
import GamingAccountPasswordTab from '@/components/gaming-accounts/GamingAccountPasswordTab.vue';
import GamingAccountRenameTab from '@/components/gaming-accounts/GamingAccountRenameTab.vue';
import GamingAccountConsoleTab from '@/components/gaming-accounts/GamingAccountConsoleTab.vue';
import GamingAccountFilesTab from '@/components/gaming-accounts/GamingAccountFilesTab.vue';
import GamingAccountBackupsTab from '@/components/gaming-accounts/GamingAccountBackupsTab.vue';
import GamingAccountSchedulesTab from '@/components/gaming-accounts/GamingAccountSchedulesTab.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Text } from '@/components/ui/typography';
import { notify } from '@/composables/useNotify';
import {
    copyToClipboard as copyToClipboardUtil,
    formatBytes,
    formatCpu,
    displayStatus,
    statusVariant,
} from '@/composables/useGamingAccountFormatters';
import type { ServerOverview } from '@/composables/useGamingAccountFormatters';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import gamingAccounts, { autoRenewBalance, autoRenewMollieSubscription } from '@/routes/gaming-accounts';
import type { BreadcrumbItem } from '@/types';

type GameServerAccount = {
    id: number;
    name: string;
    status: string;
    identifier: string | null;
    current_period_ends_at: string | null;
    cancel_at_period_end: boolean;
    hosting_plan?: { name: string };
};

type GameserverCloudSubscription = {
    id: number;
    current_period_ends_at: string | null;
    cancel_at_period_end?: boolean;
    plan: { name: string };
};

type Props = {
    gameServerAccount: GameServerAccount;
    loginUrl: string | null;
    userEmail: string;
    serverOverview: ServerOverview | null;
    canRenew?: boolean;
    renewalAmount?: number;
    canPayWithBalance?: boolean;
    customerBalance?: number;
    renewUrl?: string;
    isSuspendedOrExpired?: boolean;
    auto_renew_with_balance?: boolean;
    has_mollie_subscription?: boolean;
    gameserverCloudSubscription?: GameserverCloudSubscription | null;
    cloudSubscriptionUrl?: string | null;
};

const props = withDefaults(defineProps<Props>(), {
    canRenew: false,
    renewalAmount: 0,
    canPayWithBalance: false,
    customerBalance: 0,
    renewUrl: '',
    isSuspendedOrExpired: false,
    auto_renew_with_balance: false,
    has_mollie_subscription: false,
    gameserverCloudSubscription: null,
    cloudSubscriptionUrl: null,
});

const powerLoading = ref<string | null>(null);
const liveOverview = ref<ServerOverview | null>(props.serverOverview ?? null);
const renewModalOpen = ref(false);
const autoRenewModalOpen = ref(false);

const renewalPeriodOptions: { months: 1 | 3 | 6 | 12; label: string }[] = [
    { months: 1, label: '30 Tage (1 Monat)' },
    { months: 3, label: '3 Monate' },
    { months: 6, label: '6 Monate' },
    { months: 12, label: '12 Monate' },
];

const displayOverview = computed(() => liveOverview.value ?? props.serverOverview ?? null);

const isCloudAccount = computed(() => !!props.gameserverCloudSubscription);
const planLabel = computed(() =>
    isCloudAccount.value && props.gameserverCloudSubscription
        ? props.gameserverCloudSubscription.plan.name
        : props.gameServerAccount.hosting_plan?.name ?? '',
);
const periodEnd = computed(() =>
    isCloudAccount.value && props.gameserverCloudSubscription
        ? props.gameserverCloudSubscription.current_period_ends_at
        : props.gameServerAccount.current_period_ends_at,
);
const cancelAtPeriodEnd = computed(
    () =>
        (isCloudAccount.value && props.gameserverCloudSubscription?.cancel_at_period_end) ||
        props.gameServerAccount.cancel_at_period_end,
);

const page = usePage();
const brandFeatures = computed(() => (page.props.brandFeatures as Record<string, boolean> | undefined) ?? {});
const showAboVerwalten = computed(
    () =>
        !isCloudAccount.value &&
        !brandFeatures.value?.prepaid_balance &&
        !props.canRenew,
);
const showRenewButton = computed(
    () =>
        !isCloudAccount.value &&
        !!props.renewUrl &&
        (props.canRenew || brandFeatures.value?.prepaid_balance === true),
);
const showAutoRenewButton = computed(
    () => showRenewButton.value && brandFeatures.value?.prepaid_balance === true,
);

let overviewPollInterval: ReturnType<typeof setInterval> | null = null;

function fetchOverview() {
    if (!props.gameServerAccount.identifier) return;
    fetch(`/gaming-accounts/${props.gameServerAccount.id}/overview`, {
        method: 'GET',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    })
        .then((res) => res.json())
        .then((data) => {
            if (data.serverOverview !== undefined) {
                liveOverview.value = data.serverOverview;
            }
        })
        .catch(() => {});
}

onMounted(() => {
    fetchOverview();
    overviewPollInterval = setInterval(fetchOverview, 3000);
});
onUnmounted(() => {
    if (overviewPollInterval) clearInterval(overviewPollInterval);
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Meine Game Server', href: '/gaming-accounts' },
    { title: props.gameServerAccount.name, href: '#' },
];

function copyToClipboard(text: string) {
    copyToClipboardUtil(text);
    notify.success('In die Zwischenablage kopiert.');
}

watch(
    () => (usePage().props.flash as { error?: string; success?: string })?.error,
    (message) => {
        if (message) notify.error(message);
    },
    { immediate: true },
);
watch(
    () => (usePage().props.flash as { error?: string; success?: string })?.success,
    (message) => {
        if (message) notify.success(message);
    },
    { immediate: true },
);

function sendPower(action: 'start' | 'stop' | 'restart' | 'kill') {
    powerLoading.value = action;
    const previousOverview = liveOverview.value ?? props.serverOverview;
    if (previousOverview && (action === 'stop' || action === 'restart' || action === 'kill')) {
        liveOverview.value = { ...previousOverview, status: 'stopping' };
    } else if (previousOverview && action === 'start') {
        liveOverview.value = { ...previousOverview, status: 'starting' };
    }
    router.post(gamingAccounts.power.url(props.gameServerAccount.id), { action }, {
        preserveScroll: true,
        onSuccess: () => {
            notify.success('Befehl gesendet.');
            powerLoading.value = null;
            fetchOverview();
        },
        onError: (errors) => {
            notify.error(errors?.action ?? 'Aktion fehlgeschlagen.');
            powerLoading.value = null;
            liveOverview.value = props.serverOverview ?? null;
        },
        onFinish: () => {
            powerLoading.value = null;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="gameServerAccount.name" />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <div class="lg:col-span-1">
                <GamingAccountSidebar
                    :game-server-account="gameServerAccount"
                    :plan-label="planLabel"
                    :period-end="periodEnd"
                    :login-url="loginUrl"
                    :display-overview="displayOverview"
                    :is-suspended-or-expired="isSuspendedOrExpired"
                    :show-renew-button="showRenewButton"
                    :show-auto-renew-button="showAutoRenewButton"
                    :show-abo-verwalten="showAboVerwalten"
                    :is-cloud-account="isCloudAccount"
                    :cloud-subscription-url="cloudSubscriptionUrl"
                    :cancel-at-period-end="cancelAtPeriodEnd"
                    @renew-click="renewModalOpen = true"
                    @auto-renew-click="autoRenewModalOpen = true"
                />
            </div>

            <!-- Hauptbereich: Header, Stats, Tabs -->
            <div class="lg:col-span-3">
                <Card
                    v-if="!loginUrl && gameServerAccount.status === 'pending'"
                    class="mb-4 border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30"
                >
                    <CardContent class="pt-6">
                        <Text>
                            Ihr Game-Server wird eingerichtet, oder für das zugehörige Hosting-Paket ist im Admin kein
                            Pterodactyl-Panel-Server hinterlegt. Bitte im Admin unter Hosting-Pakete beim betreffenden
                            Paket einen Panel-Server angeben bzw. uns kontaktieren.
                        </Text>
                    </CardContent>
                </Card>

                <!-- Header Card -->
                <Card class="mb-4">
                    <CardContent class="pt-6">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/15"
                                >
                                    <Power class="h-5 w-5 text-primary" />
                                </div>
                                <div class="min-w-0">
                                    <h2 class="truncate font-semibold">
                                        {{ displayOverview?.name ?? gameServerAccount.name }}
                                    </h2>
                                    <p class="text-sm text-muted-foreground">{{ planLabel || 'Game Server' }}</p>
                                    <Badge
                                        :variant="statusVariant(displayOverview, gameServerAccount.status)"
                                        class="mt-1"
                                    >
                                        {{ displayStatus(displayOverview, gameServerAccount.status) }}
                                    </Badge>
                                </div>
                            </div>
                            <div
                                v-if="displayOverview?.allocation"
                                class="flex flex-1 min-w-[200px] flex-col items-center justify-center text-center"
                            >
                                <p class="text-xs text-muted-foreground">Server-Adresse</p>
                                <div class="mt-1 flex items-center gap-2">
                                    <code class="rounded bg-muted px-2 py-1 text-sm">
                                        {{ displayOverview.allocation }}
                                    </code>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 shrink-0"
                                        @click="copyToClipboard(displayOverview!.allocation!)"
                                    >
                                        <Copy class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <div class="inline-flex overflow-hidden rounded-lg border">
                                    <Button
                                        variant="default"
                                        size="sm"
                                        class="rounded-none border-0"
                                        :disabled="!!powerLoading"
                                        @click="sendPower('start')"
                                    >
                                        <Loader2
                                            v-if="powerLoading === 'start'"
                                            class="mr-1 h-4 w-4 animate-spin"
                                        />
                                        <Power v-else class="mr-1 h-4 w-4" />
                                        Start
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="rounded-none border-l"
                                        :disabled="!!powerLoading"
                                        @click="sendPower('restart')"
                                    >
                                        <Loader2
                                            v-if="powerLoading === 'restart'"
                                            class="mr-1 h-4 w-4 animate-spin"
                                        />
                                        <RotateCcw v-else class="mr-1 h-4 w-4" />
                                        Restart
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="rounded-none border-l"
                                        :disabled="!!powerLoading"
                                        @click="sendPower('stop')"
                                    >
                                        <Loader2
                                            v-if="powerLoading === 'stop'"
                                            class="mr-1 h-4 w-4 animate-spin"
                                        />
                                        <PowerOff v-else class="mr-1 h-4 w-4" />
                                        Stop
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        class="rounded-none border-l"
                                        :disabled="!!powerLoading"
                                        @click="sendPower('kill')"
                                    >
                                        <Loader2
                                            v-if="powerLoading === 'kill'"
                                            class="mr-1 h-4 w-4 animate-spin"
                                        />
                                        Kill
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Stats Grid -->
                <div
                    v-if="displayOverview"
                    class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <Card>
                        <CardContent class="pt-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-xs text-muted-foreground">CPU</p>
                                    <p class="text-lg font-semibold">
                                        {{ formatCpu(displayOverview.usage?.cpu_absolute ?? 0) }}
                                    </p>
                                </div>
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/15"
                                >
                                    <Cpu class="h-4 w-4 text-primary" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-xs text-muted-foreground">Memory</p>
                                    <p class="text-lg font-semibold">
                                        {{ formatBytes(displayOverview.usage?.memory_bytes ?? 0) }}
                                        <span
                                            v-if="displayOverview.limits?.memory"
                                            class="text-muted-foreground"
                                        >
                                            / {{ displayOverview.limits.memory }} MB
                                        </span>
                                    </p>
                                </div>
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15"
                                >
                                    <MemoryStick class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-xs text-muted-foreground">Disk</p>
                                    <p class="text-lg font-semibold">
                                        {{ formatBytes(displayOverview.usage?.disk_bytes ?? 0) }}
                                        <span
                                            v-if="displayOverview.limits?.disk"
                                            class="text-muted-foreground"
                                        >
                                            / {{ (displayOverview.limits.disk / 1024).toFixed(1) }} GB
                                        </span>
                                    </p>
                                </div>
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/15"
                                >
                                    <HardDrive class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-xs text-muted-foreground">Network</p>
                                    <p class="text-sm font-semibold">
                                        <span class="text-emerald-600 dark:text-emerald-400">↓</span>
                                        {{ formatBytes(displayOverview.usage?.network_rx_bytes ?? 0) }}
                                        <span class="text-muted-foreground">/</span>
                                        <span class="text-sky-600 dark:text-sky-400">↑</span>
                                        {{ formatBytes(displayOverview.usage?.network_tx_bytes ?? 0) }}
                                    </p>
                                </div>
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-500/15"
                                >
                                    <Network class="h-4 w-4 text-sky-600 dark:text-sky-400" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Tabs default-tab="console" class="w-full">
                    <TabsList class="mb-4 flex h-auto flex-wrap justify-start gap-1 rounded-lg bg-white p-1 dark:bg-muted/50">
                        <TabsTrigger value="console" class="gap-2 px-3 py-2">
                            <Terminal class="h-4 w-4" />
                            Konsole
                        </TabsTrigger>
                        <TabsTrigger value="files" class="gap-2 px-3 py-2">
                            <FolderOpen class="h-4 w-4" />
                            Dateien
                        </TabsTrigger>
                        <TabsTrigger value="backups" class="gap-2 px-3 py-2">
                            <Archive class="h-4 w-4" />
                            Backups
                        </TabsTrigger>
                        <TabsTrigger value="schedules" class="gap-2 px-3 py-2">
                            <Calendar class="h-4 w-4" />
                            Schedules
                        </TabsTrigger>
                        <TabsTrigger value="overview" class="gap-2 px-3 py-2">
                            <LayoutDashboard class="h-4 w-4" />
                            <span class="hidden sm:inline">Übersicht</span>
                        </TabsTrigger>
                        <TabsTrigger value="access" class="gap-2 px-3 py-2">
                            <ExternalLink class="h-4 w-4" />
                            <span class="hidden sm:inline">Zugang</span>
                        </TabsTrigger>
                        <TabsTrigger value="password" class="gap-2 px-3 py-2">
                            <KeyRound class="h-4 w-4" />
                            <span class="hidden sm:inline">Passwort</span>
                        </TabsTrigger>
                        <TabsTrigger value="rename" class="gap-2 px-3 py-2">
                            <Pencil class="h-4 w-4" />
                            <span class="hidden sm:inline">Umbenennen</span>
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="console" class="mt-0">
                        <GamingAccountConsoleTab :game-server-account-id="gameServerAccount.id" />
                    </TabsContent>

                    <TabsContent value="files" class="mt-0">
                        <GamingAccountFilesTab :game-server-account-id="gameServerAccount.id" />
                    </TabsContent>

                    <TabsContent value="backups" class="mt-0">
                        <GamingAccountBackupsTab :game-server-account-id="gameServerAccount.id" />
                    </TabsContent>

                    <TabsContent value="schedules" class="mt-0">
                        <GamingAccountSchedulesTab :game-server-account-id="gameServerAccount.id" />
                    </TabsContent>

                    <TabsContent value="overview" class="mt-0">
                        <GamingAccountOverviewTab
                            :game-server-account="gameServerAccount"
                            :display-overview="displayOverview"
                            :is-suspended-or-expired="isSuspendedOrExpired"
                            :can-renew="canRenew"
                            :power-loading="powerLoading"
                            :period-end="periodEnd"
                            :cancel-at-period-end="cancelAtPeriodEnd"
                            @send-power="sendPower"
                            @copy-to-clipboard="copyToClipboard"
                        />
                    </TabsContent>

                    <TabsContent value="access" class="mt-4">
                        <GamingAccountAccessTab
                            :game-server-account="gameServerAccount"
                            :user-email="userEmail"
                            :login-url="loginUrl"
                            :is-suspended-or-expired="isSuspendedOrExpired"
                            @copy-to-clipboard="copyToClipboard"
                        />
                    </TabsContent>

                    <TabsContent value="password" class="mt-4">
                        <GamingAccountPasswordTab
                            :login-url="loginUrl"
                            :is-suspended-or-expired="isSuspendedOrExpired"
                        />
                    </TabsContent>

                    <TabsContent value="rename" class="mt-4">
                        <GamingAccountRenameTab
                            :game-server-account="gameServerAccount"
                            :login-url="loginUrl"
                            :is-suspended-or-expired="isSuspendedOrExpired"
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </div>

        <PaymentMethodModal
            v-if="showRenewButton"
            :open="renewModalOpen"
            :amount="renewalAmount"
            title="Verlängerung bezahlen"
            description="Game-Server verlängern."
            :can-pay-with-balance="canPayWithBalance"
            :customer-balance="customerBalance"
            :submit-url="renewUrl"
            :period-options="renewalPeriodOptions"
            :base-amount-per-month="renewalAmount"
            @update:open="renewModalOpen = $event"
        />
        <AutoRenewModal
            v-if="showAutoRenewButton"
            :open="autoRenewModalOpen"
            :balance-url="autoRenewBalance.url(props.gameServerAccount.id)"
            :mollie-url="autoRenewMollieSubscription.url(props.gameServerAccount.id)"
            :mollie-cancel-url="gamingAccounts.subscription.cancel.url(props.gameServerAccount.id)"
            :auto-renew-with-balance="props.auto_renew_with_balance"
            :has-mollie-subscription="props.has_mollie_subscription"
            @update:open="autoRenewModalOpen = $event"
        />
    </AppLayout>
</template>
