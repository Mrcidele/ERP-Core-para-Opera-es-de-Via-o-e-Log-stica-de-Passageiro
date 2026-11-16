<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';

// Campo de busca de listagem. A busca roda no servidor, então o valor só é emitido quando a
// digitação para por um instante (ou no Enter), e não a cada tecla.
const props = withDefaults(defineProps<{ modelValue: string; label: string; delay?: number }>(), { delay: 350 });
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const text = ref(props.modelValue);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => props.modelValue,
    (value) => {
        // Limpeza vinda de fora (ex.: "Limpar filtros").
        if (value !== text.value) {
            text.value = value;
        }
    },
);

function publish(): void {
    clearTimeout(timer);

    if (text.value !== props.modelValue) {
        emit('update:modelValue', text.value);
    }
}

function onInput(): void {
    clearTimeout(timer);
    timer = setTimeout(publish, props.delay);
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <label class="relative block">
        <span class="sr-only">{{ label }}</span>
        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-ink-400"><AppIcon name="search" /></span>
        <input
            v-model="text"
            type="search"
            data-test="search"
            class="field-input !mt-0 !h-9 pl-10"
            :placeholder="label"
            @input="onInput"
            @keydown.enter.prevent="publish"
        />
    </label>
</template>
