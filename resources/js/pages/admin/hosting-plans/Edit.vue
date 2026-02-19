<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Heading, Text } from '@/components/ui/typography';
import { Switch } from '@/components/ui/switch';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { ref } from 'vue';

type HostingPlan = {
    id: number;
    name: string;
    plesk_package_name: string;
    disk_gb: number;
    traffic_gb: number;
    domains: number;
    subdomains: number;
    mailboxes: number;
    databases: number;
    price: string;
    stripe_price_id: string | null;
    is_active: boolean;
    sort_order: number;
};

type Props = {
    hostingPlan: HostingPlan;
};

const props = defineProps<Props>();

const isActive = ref(props.hostingPlan.is_active);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Webspace-Pakete', href: '/admin/hosting-plans' },
    { title: props.hostingPlan.name, href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${hostingPlan.name} bearbeiten`" />

        <div class="space-y-6">
            <div>
                <Heading level="h1">{{ hostingPlan.name }} bearbeiten</Heading>
                <Text class="mt-2" muted>
                    Plesk-Paket: {{ hostingPlan.plesk_package_name }}
                </Text>
            </div>

            <Card class="max-w-2xl">
                <CardHeader>
                    <CardTitle>Paket-Details</CardTitle>
                    <CardDescription>Name, Plesk-Paketname und Limits</CardDescription>
                </CardHeader>
                <Form
                    :action="`/admin/hosting-plans/${hostingPlan.id}`"
                    method="post"
                    class="space-y-6"
                    v-slot="{ errors }"
                >
                    <CardContent class="space-y-4">
                        <input type="hidden" name="_method" value="PUT" />
                        <div class="space-y-2">
                            <Label for="name">Name *</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                :model-value="hostingPlan.name"
                                :aria-invalid="!!errors.name"
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="space-y-2">
                            <Label for="plesk_package_name">Plesk-Paketname (Paket-ID) *</Label>
                            <Input
                                id="plesk_package_name"
                                name="plesk_package_name"
                                required
                                :model-value="hostingPlan.plesk_package_name"
                                :aria-invalid="!!errors.plesk_package_name"
                            />
                            <InputError :message="errors.plesk_package_name" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="disk_gb">Disk (GB)</Label>
                                <Input id="disk_gb" name="disk_gb" type="number" min="0" :model-value="hostingPlan.disk_gb" />
                            </div>
                            <div class="space-y-2">
                                <Label for="traffic_gb">Traffic (GB/Monat)</Label>
                                <Input id="traffic_gb" name="traffic_gb" type="number" min="0" :model-value="hostingPlan.traffic_gb" />
                            </div>
                            <div class="space-y-2">
                                <Label for="domains">Domains</Label>
                                <Input id="domains" name="domains" type="number" min="0" :model-value="hostingPlan.domains" />
                            </div>
                            <div class="space-y-2">
                                <Label for="subdomains">Subdomains</Label>
                                <Input id="subdomains" name="subdomains" type="number" min="0" :model-value="hostingPlan.subdomains" />
                            </div>
                            <div class="space-y-2">
                                <Label for="mailboxes">Mailpostfächer</Label>
                                <Input id="mailboxes" name="mailboxes" type="number" min="0" :model-value="hostingPlan.mailboxes" />
                            </div>
                            <div class="space-y-2">
                                <Label for="databases">Datenbanken</Label>
                                <Input id="databases" name="databases" type="number" min="0" :model-value="hostingPlan.databases" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label for="price">Preis (€/Monat) *</Label>
                            <Input
                                id="price"
                                name="price"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                :model-value="hostingPlan.price"
                                :aria-invalid="!!errors.price"
                            />
                            <InputError :message="errors.price" />
                        </div>
                        <div class="space-y-2">
                            <Label for="stripe_price_id">Stripe Price ID (optional)</Label>
                            <Input
                                id="stripe_price_id"
                                name="stripe_price_id"
                                :model-value="hostingPlan.stripe_price_id ?? ''"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="sort_order">Sortierung</Label>
                            <Input id="sort_order" name="sort_order" type="number" min="0" :model-value="hostingPlan.sort_order" />
                        </div>
                        <div class="flex items-center space-x-2">
                            <Switch
                                id="is_active"
                                :checked="isActive"
                                @update:checked="isActive = $event"
                            />
                            <Label for="is_active">Aktiv</Label>
                        </div>
                        <input type="hidden" name="is_active" :value="isActive ? '1' : '0'" />
                    </CardContent>
                    <CardFooter class="flex gap-2">
                        <Button type="submit">Speichern</Button>
                        <Link :href="`/admin/hosting-plans/${hostingPlan.id}`">
                            <Button type="button" variant="outline">Abbrechen</Button>
                        </Link>
                    </CardFooter>
                </Form>
            </Card>
        </div>
    </AppLayout>
</template>
