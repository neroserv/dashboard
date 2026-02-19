<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Heading, Text } from '@/components/ui/typography';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/table';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { Server, ExternalLink } from 'lucide-vue-next';

type HostingPlan = { id: number; name: string };

type WebspaceAccount = {
    id: number;
    domain: string;
    status: string;
    current_period_ends_at: string | null;
    hosting_plan: HostingPlan;
};

type Props = {
    webspaceAccounts: WebspaceAccount[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Meine Webspace-Accounts', href: '/webspace-accounts' },
];

const formatDate = (d: string | null) => (d ? new Date(d).toLocaleDateString('de-DE') : '-');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Meine Webspace-Accounts" />

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <Heading level="h1">Meine Webspace-Accounts</Heading>
                    <Text class="mt-2" muted>
                        Ihre Plesk-Webspace-Accounts
                    </Text>
                </div>
                <Link href="/webspace">
                    <Button>
                        <Server class="mr-2 h-4 w-4" />
                        Webspace buchen
                    </Button>
                </Link>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Alle Accounts</CardTitle>
                    <CardDescription>Domain, Paket, Status und Abo-Ende</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Domain</TableHead>
                                <TableHead>Paket</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Verlängerung</TableHead>
                                <TableHead class="text-right">Aktionen</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="acc in props.webspaceAccounts" :key="acc.id">
                                <TableCell>
                                    <code class="rounded bg-gray-100 px-2 py-1 text-sm dark:bg-gray-800">{{ acc.domain }}</code>
                                </TableCell>
                                <TableCell>{{ acc.hosting_plan.name }}</TableCell>
                                <TableCell>
                                    <Badge variant="secondary">{{ acc.status }}</Badge>
                                </TableCell>
                                <TableCell>{{ formatDate(acc.current_period_ends_at) }}</TableCell>
                                <TableCell class="text-right">
                                    <Link :href="`/webspace-accounts/${acc.id}`">
                                        <Button variant="ghost" size="sm">
                                            <ExternalLink class="h-4 w-4" />
                                        </Button>
                                    </Link>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="props.webspaceAccounts.length === 0">
                                <TableCell colspan="5" class="text-center text-gray-500 dark:text-gray-400">
                                    Sie haben noch keine Webspace-Accounts.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
