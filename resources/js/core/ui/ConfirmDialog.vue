<script setup lang="ts">
defineProps<{
    open: boolean;
    title: string;
    message: string;
    confirmLabel: string;
    /** Texto do botão que fecha sem confirmar. */
    dismissLabel?: string;
    busy?: boolean;
    error?: string | null;
}>();

const emit = defineEmits<{ confirm: []; cancel: [] }>();
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" @keydown.esc="emit('cancel')">
        <div class="absolute inset-0 bg-ink-900/40" @click="emit('cancel')" />
        <section
            role="alertdialog"
            aria-modal="true"
            :aria-label="title"
            class="relative w-full max-w-sm rounded-xl bg-white p-6 shadow-panel"
        >
            <h2 class="font-display text-lg font-semibold">{{ title }}</h2>
            <p class="mt-2 text-ink-600">{{ message }}</p>
            <p v-if="error" role="alert" class="field-error" data-test="confirm-error">{{ error }}</p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" class="btn btn-quiet" data-test="confirm-cancel" @click="emit('cancel')">{{ dismissLabel ?? 'Cancelar' }}</button>
                <button type="button" class="btn btn-danger" data-test="confirm-ok" :disabled="busy" @click="emit('confirm')">
                    {{ confirmLabel }}
                </button>
            </div>
        </section>
    </div>
</template>
