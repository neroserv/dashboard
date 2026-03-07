<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Pagination } from '@/components/ui/pagination';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/components/ui/table';
import { Heading, Text } from '@/components/ui/typography';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type User = { id: number; name: string; email: string };
type HostingServer = { id: number; name: string | null; hostname: string } | null;
type GameserverCloudPlan = { id: number; name: string };
type GameserverCloudSubscription = {
    current_period_ends_at: string | null;
    gameserver_cloud_plan: GameserverCloudPlan;
};

type GameServerAccount = {
    id: number;
    name: string;
    identifier: string | null;
    status: string;
    user: User;
    hosting_server: HostingServer;
    gameserver_cloud_subscription: GameserverCloudSubscription;
};

type Props = {
    gameServerAccounts: {
        data: GameServerAccount[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    brandHasGameserverCloud: boolean;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Gameserver-Cloud-Accounts', href: '/admin/gameserver-cloud-accounts' },
];

const handlePagination = (url: string) => {
    window.location.href = url;
};

const formatDate = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('de-DE', { timeZone: 'UTC' }) : '–';

const planName = (acc: GameServerAccount) =>
    acc.gameserver_cloud_subscription?.gameserver_cloud_plan?.name ?? '–';

const periodEnd = (acc: GameServerAccount) =>
    acc.gameserver_cloud_subscription?.current_period_ends_at ?? null;
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <Head title="Gameserver-Cloud-Accounts" />

        <div class="space-y-6">
            <div>
                <Heading level="h1">Gameserver-Cloud-Accounts</Heading>
                <Text class="mt-2" muted>
                    Game-Server, die aus einem Gameserver-Cloud-Abo angelegt wurden
                </Text>
            </div>

            <Card v-if="!brandHasGameserverCloud">
                <CardContent class="py-8 text-center text-muted-foreground">
                    Für diese Marke ist das Feature „Gameserver Cloud“ nicht aktiviert.
                </CardContent>
            </Card>

            <Card v-else>
                <CardHeader>
                    <CardTitle>Alle Cloud-Accounts</CardTitle>
                    <CardDescription>Kunde, Server-Name, Cloud-Plan, Status, Abo-Ende</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Kunde</TableHead>
                                <TableHead>Server-Name</TableHead>
                                <TableHead>Identifier</TableHead>
                                <TableHead>Cloud-Plan</TableHead>
                                <TableHead>Server</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Abo-Ende</TableHead>
                                <TableHead class="text-right">Aktionen</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="acc in props.gameServerAccounts.data"
                                :key="acc.id"
                            >
                                <TableCell>
                                    <div class="font-medium">{{ acc.user.name }}</div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ acc.user.email }}
                                    </div>
                                </TableCell>
                                <TableCell>{{ acc.name }}</TableCell>
                                <TableCell>
                                    <code
                                        v-if="acc.identifier"
                                        class="rounded bg-gray-100 px-2 py-1 text-sm dark:bg-gray-800"
                                    >
                                        {{ acc.identifier }}
                                    </code>
                                    <span v-else class="text-muted-foreground">–</span>
                                </TableCell>
                                <TableCell>{{ planName(acc) }}</TableCell>
                                <TableCell>
                                    {{ acc.hosting_server?.name ?? acc.hosting_server?.hostname ?? '–' }}
                                </TableCell>
                                <TableCell>
                                    <Badge variant="secondary">{{ acc.status }}</Badge>
                                </TableCell>
                                <TableCell>{{ formatDate(periodEnd(acc)) }}</TableCell>
                                <TableCell class="text-right">
                                    <Link :href="`/admin/gaming-accounts/${acc.id}`">
                                        <Button variant="ghost" size="sm">
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                    </Link>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="props.gameServerAccounts.data.length === 0">
                                <TableCell
                                    colspan="8"
                                    class="text-center text-gray-500 dark:text-gray-400"
                                >
                                    Keine Gameserver-Cloud-Accounts vorhanden
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <div
                v-if="
                    brandHasGameserverCloud &&
                    props.gameServerAccounts.links &&
                    props.gameServerAccounts.links.length > 3
                "
                class="flex justify-center"
            >
                <Pagination
                    :links="props.gameServerAccounts.links"
                    @navigate="handlePagination"
                />
            </div>
        </div>
    </AdminLayout>
</template>
