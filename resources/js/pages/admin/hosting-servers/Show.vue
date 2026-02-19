<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Heading, Text } from '@/components/ui/typography';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { Edit } from 'lucide-vue-next';

type HostingServer = {
    id: number;
    name: string | null;
    hostname: string;
    ip_address: string | null;
    is_active: boolean;
    webspace_accounts_count: number;
};

type Props = {
    hostingServer: HostingServer;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Hosting-Server', href: '/admin/hosting-servers' },
    { title: props.hostingServer.name ?? props.hostingServer.hostname, href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="hostingServer.name ?? hostingServer.hostname" />

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <Heading level="h1">{{ hostingServer.name ?? hostingServer.hostname }}</Heading>
                    <Text class="mt-2" muted>
                        Plesk-Hosting-Server
                    </Text>
                </div>
                <Link :href="`/admin/hosting-servers/${hostingServer.id}/edit`">
                    <Button>
                        <Edit class="mr-2 h-4 w-4" />
                        Bearbeiten
                    </Button>
                </Link>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                    <CardDescription>Server-Informationen</CardDescription>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div class="flex justify-between py-2 border-b">
                        <span class="text-muted-foreground">Hostname</span>
                        <code class="rounded bg-gray-100 px-2 py-1 text-sm dark:bg-gray-800">{{ hostingServer.hostname }}</code>
                    </div>
                    <div class="flex justify-between py-2 border-b">
                        <span class="text-muted-foreground">IP-Adresse</span>
                        <span>{{ hostingServer.ip_address ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b">
                        <span class="text-muted-foreground">Status</span>
                        <Badge :variant="hostingServer.is_active ? 'success' : 'error'">
                            {{ hostingServer.is_active ? 'Aktiv' : 'Inaktiv' }}
                        </Badge>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-muted-foreground">Webspace-Accounts</span>
                        <span>{{ hostingServer.webspace_accounts_count }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
