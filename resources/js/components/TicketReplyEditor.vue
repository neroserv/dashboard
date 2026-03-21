<!-- Ticket-Antwort-Editor: Rich-Text mit Toolbar und Vorlagen (Admin-Panel) -->
<script setup lang="ts">
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount, ref, watch } from 'vue';
import {
    BButton,
    BModal,
} from 'bootstrap-vue-next';
import Icon from '@/components/wrappers/Icon.vue';

export type TicketMessageTemplateItem = {
    id: number;
    name: string;
    body: string | null;
};

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        placeholder?: string;
        minHeight?: string;
        id?: string;
        disabled?: boolean;
        showTemplates?: boolean;
        templates?: TicketMessageTemplateItem[];
        templateReplacements?: Record<string, string>;
    }>(),
    {
        modelValue: '',
        placeholder: 'Nachricht...',
        minHeight: '120px',
        disabled: false,
        showTemplates: false,
        templates: () => [],
        templateReplacements: () => ({}),
    },
);

const templatesModalOpen = ref(false);

function replacePlaceholders(text: string): string {
    if (!text) return '';
    const repl = props.templateReplacements ?? {};
    return text.replace(/\{\{(\w+)\}\}/g, (_, key) => repl[key] ?? '');
}

function insertTemplate(templateBody: string | null) {
    if (!templateBody || !editor.value) return;
    const html = replacePlaceholders(templateBody);
    editor.value.chain().focus().insertContent(html).run();
    templatesModalOpen.value = false;
}

function stripHtmlPreview(html: string | null): string {
    if (!html) return '';
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent?.trim().slice(0, 60) ?? '';
}

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

function toHtml(val: string | undefined): string {
    if (!val || typeof val !== 'string') return '';
    const trimmed = val.trim();
    if (!trimmed) return '';
    if (trimmed.startsWith('<') && trimmed.includes('>')) return val;
    return `<p>${trimmed.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>`;
}

const editor = useEditor({
    content: toHtml(props.modelValue),
    editable: !props.disabled,
    extensions: [
        StarterKit.configure({
            codeBlock: {},
            heading: false,
        }),
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
    ],
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
    editorProps: {
        attributes: {
            class:
                'min-h-[120px] w-full px-3 py-2 text-sm focus:outline-none prose prose-sm max-w-none dark:prose-invert [&_p]:mb-1 [&_p:last-child]:mb-0',
        },
    },
});

watch(
    () => props.modelValue,
    (val) => {
        if (editor.value) {
            const html = toHtml(val);
            if (html !== editor.value.getHTML()) {
                editor.value.commands.setContent(html, { emitUpdate: false });
            }
        }
    },
);

watch(
    () => props.disabled,
    (val) => {
        editor.value?.setEditable(!val);
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

function clearFormatting() {
    editor.value?.chain().focus().clearNodes().unsetAllMarks().run();
}
</script>

<template>
    <div
        class="rounded border"
        :class="disabled ? 'opacity-60 cursor-not-allowed' : ''"
        :style="{ minHeight }"
    >
        <!-- Toolbar: Formatierung und Vorlagen -->
        <div
            v-if="editor"
            role="toolbar"
            aria-label="Formatierung"
            class="d-flex flex-wrap align-items-center gap-1 border-bottom bg-light px-2 py-2"
        >
            <template v-if="showTemplates">
                <button
                    type="button"
                    class="rounded p-1.5 text-body hover-bg-light border-0"
                    aria-label="Vorlagen"
                    title="Vorlagen"
                    @click="templatesModalOpen = true"
                >
                    <Icon icon="template" class="fs-6" />
                </button>
                <span class="vr mx-1" />
            </template>
            <button
                type="button"
                class="rounded p-1.5 text-body hover-bg-light border-0"
                aria-label="Fett"
                :aria-pressed="editor.isActive('bold')"
                @click="editor.chain().focus().toggleBold().run()"
            >
                <Icon icon="bold" class="fs-6" />
            </button>
            <button
                type="button"
                class="rounded p-1.5 text-body hover-bg-light border-0"
                aria-label="Kursiv"
                :aria-pressed="editor.isActive('italic')"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                <Icon icon="italic" class="fs-6" />
            </button>
            <button
                type="button"
                class="rounded p-1.5 text-body hover-bg-light border-0"
                aria-label="Unterstrichen"
                :aria-pressed="editor.isActive('underline')"
                @click="editor.chain().focus().toggleUnderline().run()"
            >
                <Icon icon="underline" class="fs-6" />
            </button>
            <span class="vr mx-1" />
            <button
                type="button"
                class="rounded p-1.5 text-body hover-bg-light border-0"
                aria-label="Zitat"
                :aria-pressed="editor.isActive('blockquote')"
                @click="editor.chain().focus().toggleBlockquote().run()"
            >
                <Icon icon="blockquote" class="fs-6" />
            </button>
            <button
                type="button"
                class="rounded p-1.5 text-body hover-bg-light border-0"
                aria-label="Code"
                :aria-pressed="editor.isActive('code')"
                @click="editor.chain().focus().toggleCode().run()"
            >
                <Icon icon="code" class="fs-6" />
            </button>
            <span class="vr mx-1" />
            <button
                type="button"
                class="rounded p-1.5 text-body hover-bg-light border-0"
                aria-label="Formatierung entfernen"
                @click="clearFormatting"
            >
                <Icon icon="clear-formatting" class="fs-6" />
            </button>
        </div>
        <EditorContent
            v-if="editor"
            :editor="editor"
            :id="id"
            class="min-h-[100px] w-full"
        />

        <!-- Modal: Vorlagen auswählen -->
        <BModal
            v-if="showTemplates"
            v-model="templatesModalOpen"
            title="Vorlagen"
            no-footer
            size="lg"
        >
            <p class="text-muted small mb-3">Vorlage wählen – Platzhalter werden beim Einfügen ersetzt.</p>
            <div class="overflow-auto py-2" style="max-height: 60vh">
                <button
                    v-for="t in templates"
                    :key="t.id"
                    type="button"
                    class="d-flex flex-column w-100 rounded border px-3 py-2 text-start text-body text-decoration-none bg-transparent hover-bg-light small gap-1 mb-2"
                    @click="insertTemplate(t.body)"
                >
                    <span class="fw-medium">{{ t.name }}</span>
                    <span v-if="t.body" class="text-muted text-truncate" style="max-width: 100%">{{ stripHtmlPreview(t.body) }}</span>
                </button>
                <p v-if="templates.length === 0" class="text-center text-muted small py-4 mb-0">Keine Vorlagen vorhanden.</p>
            </div>
        </BModal>
    </div>
</template>
