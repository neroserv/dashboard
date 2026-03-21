<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

type ButtonVariant = 'default' | 'secondary' | 'outline' | 'ghost' | 'destructive' | 'link' | 'orange';
type ButtonSize = 'sm' | 'md' | 'lg' | 'icon';

interface Props {
    variant?: ButtonVariant;
    size?: ButtonSize;
    as?: 'button' | 'a';
    class?: string;
    type?: 'button' | 'submit' | 'reset';
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'default',
    size: 'md',
    as: 'button',
    type: 'button',
    disabled: false,
});

const baseClasses = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg font-medium transition-modern disabled:pointer-events-none disabled:opacity-50 outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2';

const variantClasses: Record<ButtonVariant, string> = {
    default: 'gradient-primary text-white shadow-primary hover:shadow-primary/80 active:scale-[0.98] dark:shadow-primary/50',
    secondary: 'bg-secondary text-secondary-foreground shadow-modern hover:bg-secondary/90 active:scale-[0.98] dark:bg-secondary dark:hover:bg-secondary/90',
    outline:
        'border-2 border-border bg-background text-foreground shadow-modern hover:bg-muted/80 hover:border-primary',
    ghost: 'text-foreground hover:bg-muted/80',
    destructive: 'bg-red-600 text-white shadow-modern-md hover:bg-red-700 active:scale-[0.98] dark:bg-red-600 dark:hover:bg-red-700',
    link: 'text-primary underline-offset-4 hover:underline',
    orange: 'bg-orange-500 text-white shadow-modern hover:bg-orange-600 active:scale-[0.98] dark:bg-orange-600 dark:hover:bg-orange-700',
};

const sizeClasses: Record<ButtonSize, string> = {
    sm: 'h-8 px-3 text-xs',
    md: 'h-10 px-4 py-2 text-sm',
    lg: 'h-12 px-6 text-base',
    icon: 'h-10 w-10',
};

const classes = computed(() =>
    cn(baseClasses, variantClasses[props.variant], sizeClasses[props.size], props.class),
);
</script>

<template>
    <component
        :is="as"
        :class="classes"
        :type="as === 'button' ? type : undefined"
        :disabled="disabled"
    >
        <slot />
    </component>
</template>
