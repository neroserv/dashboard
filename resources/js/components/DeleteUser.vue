<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import { BButton } from 'bootstrap-vue-next';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Konto löschen"
            description="Ihr Konto und alle zugehörigen Daten werden entfernt"
        />
        <div
            class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
        >
            <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                <p class="font-medium">Warnung</p>
                <p class="text-sm">
                    Bitte vorsichtig vorgehen – dies kann nicht rückgängig gemacht werden.
                </p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <BButton type="button" variant="danger" data-test="delete-user-button">
                        Konto löschen
                    </BButton>
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        @error="() => passwordInput?.$el?.focus()"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>
                                Möchten Sie Ihr Konto wirklich löschen?
                            </DialogTitle>
                            <DialogDescription>
                                Nach dem Löschen werden alle Ihre Daten und
                                Ressourcen dauerhaft entfernt. Geben Sie zur
                                Bestätigung Ihr Passwort ein.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only">Passwort</Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                ref="passwordInput"
                                placeholder="Passwort"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <BButton
                                    type="button"
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    Abbrechen
                                </BButton>
                            </DialogClose>

                            <BButton
                                type="submit"
                                variant="danger"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                            >
                                Konto löschen
                            </BButton>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
