<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Heading, Text } from '@/components/ui/typography';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { ref, computed } from 'vue';
import { Eye, EyeOff, Copy, ExternalLink, Mail } from 'lucide-vue-next';

type WebspaceAccount = {
    id: number;
    domain: string;
    plesk_username: string;
    status: string;
    current_period_ends_at: string | null;
    cancel_at_period_end: boolean;
    hosting_plan: { name: string };
};

type Props = {
    webspaceAccount: WebspaceAccount;
    pleskPassword: string | null;
    webmailUrl: string;
};

const props = defineProps<Props>();

const showPassword = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Meine Webspace-Accounts', href: '/webspace-accounts' },
    { title: props.webspaceAccount.domain, href: '#' },
];

const formatDate = (d: string | null) => (d ? new Date(d).toLocaleDateString('de-DE') : '-');

const displayPassword = computed(() =>
    props.pleskPassword
        ? showPassword.value
            ? props.pleskPassword
            : '••••••••••••••••••••'
        : '—'
);

function copyToClipboard(text: string) {
    navigator.clipboard.writeText(text);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="webspaceAccount.domain" />

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <Heading level="h1">{{ webspaceAccount.domain }}</Heading>
                    <Text class="mt-2" muted>
                        {{ webspaceAccount.hosting_plan.name }} · Status: {{ webspaceAccount.status }}
                    </Text>
                </div>
                <Link href="/billing/portal">
                    <Button variant="outline">Abo verwalten</Button>
                </Link>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Zugangsdaten (Plesk / FTP)</CardTitle>
                    <CardDescription>
                        Nutzername und Passwort für Plesk-Kundenpanel und FTP. Mail-Postfächer können eigene Zugänge haben.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Nutzername</label>
                        <div class="flex gap-2">
                            <Input
                                :model-value="webspaceAccount.plesk_username"
                                readonly
                                class="font-mono"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                title="Kopieren"
                                @click="copyToClipboard(webspaceAccount.plesk_username)"
                            >
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Passwort</label>
                        <div class="flex gap-2">
                            <Input
                                :model-value="displayPassword"
                                readonly
                                class="font-mono"
                                :type="showPassword ? 'text' : 'password'"
                            />
                            <Button
                                v-if="pleskPassword"
                                type="button"
                                variant="outline"
                                size="icon"
                                :title="showPassword ? 'Verbergen' : 'Anzeigen'"
                                @click="showPassword = !showPassword"
                            >
                                <Eye v-if="!showPassword" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </Button>
                            <Button
                                v-if="pleskPassword"
                                type="button"
                                variant="outline"
                                size="icon"
                                title="Kopieren"
                                @click="copyToClipboard(pleskPassword)"
                            >
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Zugang</CardTitle>
                    <CardDescription>Direkt in Plesk einloggen oder Webmail öffnen</CardDescription>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-3">
                    <Link :href="`/webspace-accounts/${webspaceAccount.id}/plesk-login`">
                        <Button>
                            <ExternalLink class="mr-2 h-4 w-4" />
                            In Plesk anmelden
                        </Button>
                    </Link>
                    <a
                        :href="webmailUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <Button variant="outline">
                            <Mail class="mr-2 h-4 w-4" />
                            Webmail öffnen
                        </Button>
                    </a>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Abo</CardTitle>
                    <CardDescription>Verlängerung und Kündigung</CardDescription>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div class="flex justify-between py-2 border-b">
                        <span class="text-muted-foreground">Nächste Verlängerung</span>
                        <span>{{ formatDate(webspaceAccount.current_period_ends_at) }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-muted-foreground">Kündigung zum Periodenende</span>
                        <Badge v-if="webspaceAccount.cancel_at_period_end" variant="secondary">Ja</Badge>
                        <span v-else>Nein</span>
                    </div>
                    <Link href="/billing/portal" class="inline-block mt-2">
                        <Button variant="outline">Abo im Kundenbereich verwalten</Button>
                    </Link>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
