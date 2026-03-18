<!-- Kundenpanel: Produkt teilen – Freigaben und Einladungen verwalten -->
<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import {
    BBadge,
    BCard,
    BCardBody,
    BCardHeader,
    BButton,
    BForm,
    BFormInput,
    BFormCheckbox,
    BModal,
} from 'bootstrap-vue-next';
import Icon from '@/components/wrappers/Icon.vue';
import InputError from '@/components/InputError.vue';

type ShareUser = { id: number; name: string; email: string } | null;

type ProductShareItem = {
    id: number;
    user: ShareUser;
    permissions: string[];
    update_url: string;
    destroy_url: string;
};

type ProductInvitationItem = {
    id: number;
    email: string;
    permissions: string[];
    expires_at: string | null;
    destroy_url: string;
};

const props = withDefaults(
    defineProps<{
        productShares: ProductShareItem[];
        productInvitations: ProductInvitationItem[];
        allowedSharePermissions: string[];
        storeInvitationUrl: string;
    }>(),
    {
        productShares: () => [],
        productInvitations: () => [],
        allowedSharePermissions: () => [],
        storeInvitationUrl: '',
    }
);

const inviteModalOpen = ref(false);
const editingShare = ref<ProductShareItem | null>(null);

const inviteForm = useForm({
    email: '',
    permissions: [] as string[],
});

const editForm = useForm({
    permissions: [] as string[],
});

const isEditModalOpen = computed({
    get: () => editingShare.value !== null,
    set: (open: boolean) => {
        if (!open) editingShare.value = null;
    },
});

function inviteSubmit() {
    if (!props.storeInvitationUrl) return;
    inviteForm.post(props.storeInvitationUrl, {
        preserveScroll: true,
        onSuccess: () => {
            inviteModalOpen.value = false;
            inviteForm.reset('email', 'permissions');
        },
    });
}

function openEditShare(share: ProductShareItem) {
    editingShare.value = share;
    editForm.permissions = [...(share.permissions ?? [])];
    editForm.clearErrors();
}

function submitEditShare() {
    const share = editingShare.value;
    if (!share || editForm.processing) return;
    editForm.permissions = editForm.permissions.filter((p) =>
        props.allowedSharePermissions.includes(p),
    );
    if (editForm.permissions.length === 0) return;
    editForm.patch(share.update_url, {
        preserveScroll: true,
        onSuccess: () => {
            editingShare.value = null;
        },
    });
}

function removeShare(destroyUrl: string) {
    if (confirm('Zugriff für diese Person wirklich entfernen?')) {
        router.delete(destroyUrl, { preserveScroll: true });
    }
}

function removeInvitation(destroyUrl: string) {
    if (confirm('Einladung wirklich zurückziehen?')) {
        router.delete(destroyUrl, { preserveScroll: true });
    }
}

const permissionLabels: Record<string, string> = {
    view: 'Ansehen',
    renew: 'Verlängern',
    cancel_subscription: 'Abo kündigen',
    plesk_login: 'Plesk-Login',
    manage_auto_renew: 'Auto-Verlängerung',
    manage_tokens: 'Token verwalten',
    panel_login: 'Panel-Login',
    backups: 'Backups',
    schedules: 'Zeitpläne',
    databases: 'Datenbanken',
    create_server: 'Server anlegen',
    manage_servers: 'Server verwalten',
    authcode: 'Auth-Code',
    contact: 'Kontakt',
    whois: 'WHOIS',
    nameserver: 'Nameserver',
    dns: 'DNS',
    dnssec: 'DNSSEC',
    autorenew: 'Auto-Verlängerung',
};

function labelFor(perm: string): string {
    return permissionLabels[perm] ?? perm;
}

function userInitial(share: ProductShareItem): string {
    const u = share.user;
    if (!u) return '–';
    if (u.name?.trim()) return u.name.trim().charAt(0).toUpperCase();
    if (u.email?.trim()) return u.email.trim().charAt(0).toUpperCase();
    return '?';
}
</script>

<template>
    <BCard no-body>
        <BCardHeader class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0">Teilen</h6>
        </BCardHeader>
        <BCardBody>
            <p class="text-muted small mb-3">Zugriff auf dieses Produkt an andere Nutzer vergeben.</p>

            <div v-if="!productShares?.length && !productInvitations?.length" class="py-4 text-center text-muted small">
                Noch niemandem Zugriff erteilt.
            </div>
            <div v-else class="d-flex flex-column gap-2">
                <div
                    v-for="share in productShares"
                    :key="share.id"
                    class="d-flex align-items-center justify-content-between rounded p-2 bg-light"
                >
                    <div class="d-flex align-items-center gap-2">
                        <span
                            class="rounded-circle bg-secondary bg-opacity-25 d-inline-flex align-items-center justify-content-center text-secondary fw-semibold small"
                            style="width: 2rem; height: 2rem"
                        >
                            {{ userInitial(share) }}
                        </span>
                        <div>
                            <span class="fw-medium">{{ share.user?.name ?? share.user?.email ?? '–' }}</span>
                            <span v-if="share.user?.email" class="d-block text-muted small">{{ share.user.email }}</span>
                            <div v-if="share.permissions?.length" class="mt-1 d-flex flex-wrap gap-1">
                                <BBadge
                                    v-for="p in share.permissions"
                                    :key="p"
                                    variant="secondary"
                                    class="small"
                                >
                                    {{ labelFor(p) }}
                                </BBadge>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-1">
                        <BButton
                            variant="outline-secondary"
                            size="sm"
                            class="py-1 px-2"
                            title="Berechtigungen anpassen"
                            @click="openEditShare(share)"
                        >
                            <Icon icon="pencil" class="me-0" />
                        </BButton>
                        <BButton
                            variant="outline-danger"
                            size="sm"
                            class="py-1 px-2"
                            title="Zugriff entfernen"
                            @click="removeShare(share.destroy_url)"
                        >
                            <Icon icon="x" class="me-0" />
                        </BButton>
                    </div>
                </div>
                <div
                    v-for="inv in productInvitations"
                    :key="inv.id"
                    class="d-flex align-items-center justify-content-between rounded p-2 border border-warning bg-warning bg-opacity-10"
                >
                    <div class="d-flex align-items-center gap-2">
                        <Icon icon="mail" class="text-warning" />
                        <div>
                            <span class="fw-medium">{{ inv.email }}</span>
                            <span class="d-block text-muted small">Einladung ausstehend</span>
                        </div>
                    </div>
                    <BButton
                        variant="outline-danger"
                        size="sm"
                        class="py-1 px-2"
                        @click="removeInvitation(inv.destroy_url)"
                    >
                        <Icon icon="x" class="me-0" />
                    </BButton>
                </div>
            </div>

            <BButton
                variant="outline-primary"
                size="sm"
                class="mt-3 w-100"
                @click="inviteModalOpen = true"
            >
                <Icon icon="user-plus" class="me-1" />
                Einladen
            </BButton>
        </BCardBody>
    </BCard>

    <BModal v-model="inviteModalOpen" title="Zugriff einladen" no-footer>
        <p class="text-muted small mb-3">
            E-Mail-Adresse und Berechtigungen festlegen. Der Eingeladene erhält eine E-Mail zur Annahme.
        </p>
        <BForm @submit.prevent="inviteSubmit">
            <div class="mb-3">
                <label class="form-label small" for="share-email">E-Mail-Adresse</label>
                <BFormInput
                    id="share-email"
                    v-model="inviteForm.email"
                    type="email"
                    placeholder="nutzer@example.com"
                    :aria-invalid="!!inviteForm.errors.email"
                />
                <InputError :message="inviteForm.errors.email" />
            </div>
            <div class="mb-3">
                <label class="form-label small">Berechtigungen</label>
                <div class="d-flex flex-wrap gap-3">
                    <BFormCheckbox
                        v-for="perm in allowedSharePermissions"
                        :key="perm"
                        v-model="inviteForm.permissions"
                        :value="perm"
                    >
                        <span class="small">{{ labelFor(perm) }}</span>
                    </BFormCheckbox>
                </div>
                <InputError :message="inviteForm.errors.permissions" />
            </div>
            <div class="d-flex justify-content-end gap-2">
                <BButton variant="secondary" @click="inviteModalOpen = false">Abbrechen</BButton>
                <BButton
                    type="submit"
                    variant="primary"
                    :disabled="inviteForm.processing || inviteForm.permissions.length === 0"
                >
                    Einladung senden
                </BButton>
            </div>
        </BForm>
    </BModal>

    <BModal v-model="isEditModalOpen" title="Berechtigungen anpassen" no-footer>
        <p v-if="editingShare" class="text-muted small mb-3">
            Berechtigungen für {{ editingShare.user?.name ?? editingShare.user?.email ?? '–' }} ändern.
        </p>
        <BForm v-if="editingShare" @submit.prevent="submitEditShare">
            <div class="mb-3">
                <label class="form-label small">Berechtigungen</label>
                <div class="d-flex flex-wrap gap-3">
                    <BFormCheckbox
                        v-for="perm in allowedSharePermissions"
                        :key="perm"
                        v-model="editForm.permissions"
                        :value="perm"
                    >
                        <span class="small">{{ labelFor(perm) }}</span>
                    </BFormCheckbox>
                </div>
                <InputError :message="editForm.errors.permissions" />
            </div>
            <div class="d-flex justify-content-end gap-2">
                <BButton variant="secondary" @click="editingShare = null">Abbrechen</BButton>
                <BButton
                    type="submit"
                    variant="primary"
                    :disabled="editForm.processing || editForm.permissions.length === 0"
                >
                    Speichern
                </BButton>
            </div>
        </BForm>
    </BModal>
</template>
