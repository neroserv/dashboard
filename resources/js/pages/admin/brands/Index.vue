<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Heading, Text } from '@/components/ui/typography';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { Edit } from 'lucide-vue-next';

type Brand = {
    id: number;
    key: string;
    name: string;
    is_default: boolean;
    domains: string[] | null;
};

type Props = {
    brands: Brand[];
};

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Admin', href: '/admin' },
    { title: 'Marken', href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Marken" />

        <div class="space-y-6">
            <div>
                <Heading level="h1">Marken / Unternehmen</Heading>
                <Text class="mt-2" muted>
                    B2B und Privat/Gaming: Name, Domains, Farben, Features und E-Mail pro Marke
                </Text>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Marken</CardTitle>
                    <CardDescription>
                        Jede Marke hat eigene Domains, Branding und Feature-Flags (z. B. Webseiten-Editor nur für B2B).
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y divide-border">
                        <li
                            v-for="brand in brands"
                            :key="brand.id"
                            class="flex items-center justify-between py-3 first:pt-0 last:pb-0"
                        >
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ brand.name }}</span>
                                <Badge v-if="brand.is_default" variant="secondary">Standard</Badge>
                                <span class="text-muted-foreground text-sm">({{ brand.key }})</span>
                                <span v-if="brand.domains?.length" class="text-muted-foreground text-xs">
                                    {{ brand.domains.join(', ') }}
                                </span>
                            </div>
                            <Link :href="`/admin/brands/${brand.id}/edit`">
                                <Button variant="ghost" size="sm">
                                    <Edit class="mr-2 h-4 w-4" />Bearbeiten
                                </Button>
                            </Link>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
