<template>
    <div v-if="attachments.length" class="ticket-message-attachments d-flex flex-wrap gap-2">
        <button
            v-for="att in attachments"
            :key="att.id"
            type="button"
            class="ticket-attachment-chip btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2 text-start"
            @click="openPreview(att)"
        >
            <span class="ticket-attachment-thumb flex-shrink-0 rounded overflow-hidden bg-body-secondary d-flex align-items-center justify-content-center">
                <img
                    v-if="att.preview === 'image'"
                    :src="inlineUrl(att.download_url)"
                    :alt="att.name"
                    class="ticket-attachment-thumb-img"
                    loading="lazy"
                />
                <Icon v-else-if="att.preview === 'pdf'" icon="file-text" width="22" height="22" class="text-danger" />
                <Icon v-else icon="file-text" width="22" height="22" class="text-body-secondary" />
            </span>
            <span class="text-truncate small" style="max-width: 11rem">{{ att.name }}</span>
        </button>

        <BModal v-model="modalOpen" :title="active?.name ?? 'Anhang'" size="lg" scrollable no-footer body-class="p-0">
            <template v-if="active">
                <div v-if="active.preview === 'image'" class="p-3 text-center bg-dark bg-opacity-10">
                    <img
                        :src="inlineUrl(active.download_url)"
                        :alt="active.name"
                        class="img-fluid rounded shadow-sm"
                        style="max-height: 75vh"
                    />
                </div>
                <div v-else-if="active.preview === 'pdf'" class="bg-body-secondary" style="min-height: 70vh">
                    <iframe
                        :title="active.name"
                        :src="inlineUrl(active.download_url)"
                        class="w-100 border-0 d-block"
                        style="min-height: 70vh"
                    />
                </div>
                <div v-else class="p-4 text-center">
                    <Icon icon="file-text" width="40" height="40" class="text-body-secondary mb-2" />
                    <p class="small text-muted mb-3">Für diesen Dateityp gibt es keine Vorschau.</p>
                </div>
                <div class="border-top p-3 d-flex flex-wrap justify-content-end gap-2 bg-body">
                    <BButton variant="outline-secondary" size="sm" @click="modalOpen = false">Schließen</BButton>
                    <a
                        class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 text-decoration-none"
                        :href="active.download_url"
                        :download="active.name"
                    >
                        <Icon icon="download" width="16" height="16" />
                        Herunterladen
                    </a>
                </div>
            </template>
        </BModal>
    </div>
</template>

<script setup lang="ts">
import { BModal, BButton } from 'bootstrap-vue-next';
import { ref } from 'vue';
import Icon from '@/components/wrappers/Icon.vue';

export type TicketMessageAttachmentItem = {
    id: number;
    name: string;
    download_url: string;
    preview?: 'image' | 'pdf' | null;
};

defineProps<{
    attachments: TicketMessageAttachmentItem[];
}>();

const modalOpen = ref(false);
const active = ref<TicketMessageAttachmentItem | null>(null);

function inlineUrl(downloadUrl: string): string {
    const sep = downloadUrl.includes('?') ? '&' : '?';

    return `${downloadUrl}${sep}inline=1`;
}

function openPreview(att: TicketMessageAttachmentItem): void {
    active.value = att;
    modalOpen.value = true;
}
</script>

<style scoped>
.ticket-attachment-thumb {
    width: 2.5rem;
    height: 2.5rem;
}

.ticket-attachment-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ticket-attachment-chip {
    max-width: 100%;
}
</style>
