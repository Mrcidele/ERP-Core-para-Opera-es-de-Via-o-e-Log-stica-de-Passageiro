<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps<{ open: boolean; title: string }>();
const emit = defineEmits<{ close: [] }>();

const panel = ref<HTMLElement | null>(null);

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        emit('close');
    }
}

watch(
    () => props.open,
    async (open) => {
        if (open) {
            document.addEventListener('keydown', onKeydown);
            await nextTick();
            panel.value?.focus();
        } else {
            document.removeEventListener('keydown', onKeydown);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
        leave-to-class="opacity-0"
    >
        <div v-if="open" class="fixed inset-0 z-40 flex justify-end">
            <div class="absolute inset-0 bg-ink-900/40" data-test="slide-over-backdrop" @click="emit('close')" />
            <section
                ref="panel"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                tabindex="-1"
                class="relative flex h-full w-full max-w-md flex-col bg-white shadow-drawer focus:outline-none"
            >
                <header class="flex items-center justify-between border-b border-ink-100 px-6 py-4">
                    <h2 class="font-display text-xl font-semibold">{{ title }}</h2>
                    <button type="button" class="icon-btn" aria-label="Fechar" @click="emit('close')">
                        <AppIcon name="close" />
                    </button>
                </header>
                <div class="flex-1 overflow-y-auto px-6 py-6">
                    <slot />
                </div>
            </section>
        </div>
    </Transition>
</template>
