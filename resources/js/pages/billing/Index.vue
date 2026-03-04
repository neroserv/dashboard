<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { watch, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { notify } from '@/composables/useNotify';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card';
import { Heading, Text } from '@/components/ui/typography';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import billing from '@/routes/billing';
import type { BreadcrumbItem } from '@/types';
import { ExternalLink, Sparkles, Wallet } from 'lucide-vue-next';
import { ref } from 'vue';
import PaymentMethodLogo from '@/components/PaymentMethodLogo.vue';

type Invoice = {
    id: number;
    number: string;
    amount: string;
    status: string;
    invoice_date: string;
    pdf_path: string | null;
    invoice_xml_path: string | null;
};

type PaymentMethodSummary = {
    brand: string;
    last4: string;
};

type AiTokenPackage = {
    amount: number;
    label: string;
};

type BalanceTransactionItem = {
    id: number;
    amount: number;
    type: string;
    description: string | null;
    created_at: string;
};

type Props = {
    invoices: Invoice[];
    billingPortalUrl: string;
    paymentMethodSummary: PaymentMethodSummary | null;
    aiTokensEnabled: boolean;
    aiTokenBalance: number;
    aiTokenPackages: AiTokenPackage[];
    prepaidEnabled?: boolean;
    customerBalance?: number;
    balanceTransactions?: BalanceTransactionItem[];
    balanceTopUpEnabled?: boolean;
    balanceTopUpMinAmount?: number;
    balanceCheckoutUrl?: string;
};

const props = defineProps<Props>();

const balanceTopUpAmount = ref<number>(props.balanceTopUpMinAmount ?? 5);
const balanceTopUpSubmitting = ref(false);
const selectedPaymentMethod = ref<string>('');

function checkoutAiTokens(amount: number): void {
    router.post(billing.aiTokens.checkout.url(), { token_amount: amount }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function submitBalanceTopUp(): void {
    const url = props.balanceCheckoutUrl;
    const min = props.balanceTopUpMinAmount ?? 5;
    if (!url || balanceTopUpAmount.value < min) return;
    balanceTopUpSubmitting.value = true;
    const payload: { amount: number; method?: string } = { amount: balanceTopUpAmount.value };
    if (selectedPaymentMethod.value) {
        payload.method = selectedPaymentMethod.value;
    }
    router.post(url, payload, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { balanceTopUpSubmitting.value = false; },
    });
}

const page = usePage();
// Zahlungsmethoden vom Server (Mollie API GET /v2/methods)
const molliePaymentMethods = computed(
    () => (page.props.molliePaymentMethods as { type: string; label: string }[]) ?? [],
);
watch(
    molliePaymentMethods,
    (methods) => {
        if (methods.length && !selectedPaymentMethod.value) {
            selectedPaymentMethod.value = methods[0].type;
        }
    },
    { immediate: true },
);

watch(
    () => (page.props.flash as { error?: string; success?: string })?.error,
    (message) => {
        if (message) notify.error(message);
    },
    { immediate: true },
);
watch(
    () => (page.props.flash as { error?: string; success?: string })?.success,
    (message) => {
        if (message) notify.success(message);
    },
    { immediate: true },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Meine Rechnungen', href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Meine Rechnungen" />

        <div class="space-y-6">
            <div>
                <Heading level="h1">Meine Rechnungen</Heading>
                <Text class="mt-2" muted>
                    Ihre letzten Rechnungen mit PDF- und E-Rechnung-Download sowie Ihre Zahlungsart
                </Text>
            </div>

            <!-- Meine Rechnungen -->
            <Card>
                <CardHeader>
                    <CardTitle>Meine Rechnungen</CardTitle>
                    <CardDescription>Ihre letzten Rechnungen mit PDF- und E-Rechnung-Download</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table v-if="invoices?.length">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nummer</TableHead>
                                <TableHead>Betrag</TableHead>
                                <TableHead>Datum</TableHead>
                                <TableHead class="text-right">Aktionen</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="inv in invoices" :key="inv.id">
                                <TableCell>
                                    <code class="text-sm">{{ inv.number }}</code>
                                </TableCell>
                                <TableCell>{{ inv.amount }} €</TableCell>
                                <TableCell>{{ inv.invoice_date }}</TableCell>
                                <TableCell class="text-right">
                                    <a
                                        :href="`/invoices/${inv.id}`"
                                        class="text-primary hover:underline font-medium"
                                    >
                                        Rechnung anzeigen
                                    </a>
                                    <template v-if="inv.pdf_path || inv.invoice_xml_path">
                                        <span class="text-muted-foreground mx-1">·</span>
                                        <a
                                            v-if="inv.pdf_path"
                                            :href="`/invoices/${inv.id}/pdf`"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-muted-foreground hover:underline text-sm"
                                        >
                                            PDF
                                        </a>
                                        <a
                                            v-if="inv.invoice_xml_path"
                                            :href="`/invoices/${inv.id}/xml`"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-muted-foreground hover:underline text-sm ml-1"
                                        >
                                            XML
                                        </a>
                                    </template>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <p v-else class="text-muted text-sm">Noch keine Rechnungen vorhanden.</p>
                </CardContent>
            </Card>

            <!-- Guthaben (Prepaid) -->
            <Card v-if="props.prepaidEnabled">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Wallet class="h-5 w-5" />
                        Guthaben
                    </CardTitle>
                    <CardDescription>
                        Aktueller Stand: {{ (props.customerBalance ?? 0).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} €
                        <template v-if="props.balanceTopUpEnabled">
                            – Guthaben können Sie per Karte aufladen (Mindestbetrag {{ (props.balanceTopUpMinAmount ?? 5).toLocaleString('de-DE', { minimumFractionDigits: 2 }) }} €).
                        </template>
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <Table v-if="props.balanceTransactions?.length">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Datum</TableHead>
                                <TableHead>Betrag</TableHead>
                                <TableHead>Beschreibung</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="tx in props.balanceTransactions" :key="tx.id">
                                <TableCell>{{ tx.created_at }}</TableCell>
                                <TableCell>
                                    <span :class="tx.amount >= 0 ? 'text-green-600' : 'text-red-600'">
                                        {{ tx.amount >= 0 ? '+' : '' }}{{ tx.amount.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} €
                                    </span>
                                </TableCell>
                                <TableCell>{{ tx.description || tx.type }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <p v-else class="text-muted text-sm">Noch keine Transaktionen.</p>
                    <div v-if="props.balanceTopUpEnabled && props.balanceCheckoutUrl" class="space-y-4 pt-4">
                        <div>
                            <h5 class="mb-2 text-sm font-medium">Zahlungsmethode</h5>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                                <label
                                    v-for="method in molliePaymentMethods"
                                    :key="method.type"
                                    class="flex cursor-pointer flex-col"
                                >
                                    <input
                                        v-model="selectedPaymentMethod"
                                        type="radio"
                                        :value="method.type"
                                        name="balance_topup_method"
                                        class="sr-only"
                                    />
                                    <div
                                        class="flex flex-col items-center gap-2 rounded-lg border bg-muted/30 p-3 transition-colors hover:bg-muted/50"
                                        :class="selectedPaymentMethod === method.type ? 'border-primary ring-2 ring-primary/20' : 'border-border'"
                                    >
                                        <PaymentMethodLogo :type="method.type" :size="28" />
                                        <span class="text-xs font-medium">{{ method.label }}</span>
                                    </div>
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Wählen Sie eine Zahlungsmethode – Sie werden direkt dorthin weitergeleitet.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-end gap-2">
                            <label class="flex flex-col gap-1 text-sm">
                                <span>Betrag (€)</span>
                                <Input
                                    v-model.number="balanceTopUpAmount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-28"
                                />
                            </label>
                            <Button
                                :disabled="balanceTopUpSubmitting || balanceTopUpAmount < (props.balanceTopUpMinAmount ?? 5)"
                                size="sm"
                                @click="submitBalanceTopUp"
                            >
                                {{ balanceTopUpSubmitting ? 'Wird weitergeleitet…' : 'Guthaben aufladen' }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- AI Tokens (nur wenn für diese Marke aktiviert) -->
            <Card v-if="props.aiTokensEnabled && props.aiTokenPackages?.length">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Sparkles class="h-5 w-5" />
                        AI Tokens
                    </CardTitle>
                    <CardDescription>
                        Aktueller Stand: {{ props.aiTokenBalance }} Tokens. Für KI-SEO und KI-Author im Page Designer.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="pkg in props.aiTokenPackages"
                            :key="pkg.amount"
                            variant="outline"
                            size="sm"
                            @click="checkoutAiTokens(pkg.amount)"
                        >
                            {{ pkg.label }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Zahlungsart -->
            <Card>
                <CardHeader>
                    <CardTitle>Zahlungsart</CardTitle>
                    <CardDescription>
                        Verwalten Sie Ihre Zahlungsmethode und Abrechnungen
                    </CardDescription>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <div
                            v-for="method in molliePaymentMethods"
                            :key="method.type"
                            class="flex items-center gap-2 rounded-md border bg-muted/30 px-3 py-1.5"
                        >
                            <PaymentMethodLogo :type="method.type" :size="18" />
                            <span class="text-xs">{{ method.label }}</span>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <p v-if="paymentMethodSummary" class="text-sm">
                        Aktuelle Zahlungsmethode:
                        <strong>{{ paymentMethodSummary.brand }} ****{{ paymentMethodSummary.last4 }}</strong>
                    </p>
                    <p v-else class="text-sm text-muted">
                        Noch keine Zahlungsmethode hinterlegt (wird beim ersten Abo-Abschluss angelegt).
                    </p>
                    <a
                        :href="billingPortalUrl"
                        class="mt-3 inline-block"
                        target="_self"
                        rel="noopener"
                    >
                        <Button variant="outline" size="sm">
                            Zahlungsart verwalten
                            <ExternalLink class="ml-2 h-3 w-3" />
                        </Button>
                    </a>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
