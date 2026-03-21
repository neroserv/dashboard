<script setup lang="ts">
import { computed } from 'vue';
import UserAvatarOrInitials from '@/components/UserAvatarOrInitials.vue';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const showAvatar = computed(
    () => Boolean(props.user.avatar && props.user.avatar !== ''),
);
</script>

<template>
    <div class="flex items-center gap-2">
        <UserAvatarOrInitials
            :name="user.name"
            :src="showAvatar ? user.avatar : null"
            :size="32"
            rounded-class="rounded-lg"
        />

        <div class="grid flex-1 text-left text-sm leading-tight">
            <span class="truncate font-medium">{{ user.name }}</span>
            <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{
                user.email
            }}</span>
        </div>
    </div>
</template>
