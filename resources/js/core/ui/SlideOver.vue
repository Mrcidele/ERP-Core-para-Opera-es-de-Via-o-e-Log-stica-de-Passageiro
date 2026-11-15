<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        /** Linha de apoio sob o título. */
        subtitle?: string;
        /** `lg` para conteúdo lado a lado, como o antes e depois da auditoria. */
        size?: 'md' | 'lg';
    }>(),
    { size: 'md' },
);
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
            <div class="absolute inset-0 bg-ink-950/40 backdrop-blur-xs" data-test="slide-over-backdrop" @click="emit('close')" />
            <section
                ref="panel"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                tabindex="-1"
                class="relative flex h-full w-full flex-col bg-white shadow-drawer focus:outline-none"
                :class="size === 'lg' ? 'max-w-3xl' : 'max-w-md'"
            >
                <header class="flex shrink-0 items-start justify-between gap-4 border-b border-ink-200 px-6 py-4">
                    <div class="min-w-0">
                        <h2 class="font-display text-lg font-semibold text-ink-950">{{ title }}</h2>
                        <p v-if="subtitle" class="mt-0.5 text-sm text-ink-500">{{ subtitle }}</p>
                    </div>
                    <button type="button" class="icon-btn -mr-2 shrink-0" aria-label="Fechar" @click="emit('close')">
                        <AppIcon name="close" />
                    </button>
                </header>

                <!-- Área com rolagem. Formulários prendem as próprias ações à base com a classe drawer-actions. -->
                <div class="flex-1 overflow-y-auto px-6 py-6">
                    <slot />
                </div>

                <!-- Rodapé fixo para painéis que não são formulário. -->
                <footer v-if="$slots.footer" class="flex shrink-0 justify-end gap-3 border-t border-ink-200 bg-white px-6 py-4">
                    <slot name="footer" />
                </footer>
            </section>
        </div>
    </Transition>
</template>
