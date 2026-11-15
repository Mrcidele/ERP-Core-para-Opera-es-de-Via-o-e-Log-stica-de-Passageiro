<script setup lang="ts">
// Placa no padrão Mercosul: faixa azul com BRASIL no topo e caracteres pretos sobre branco.
withDefaults(defineProps<{ plate: string; size?: 'sm' | 'md' }>(), { size: 'md' });
</script>

<template>
    <span
        class="inline-flex flex-col overflow-hidden rounded-[5px] border border-ink-900/80 bg-white align-middle shadow-card"
        :class="size === 'sm' ? 'w-[78px]' : 'w-[94px]'"
        :aria-label="`Placa ${plate}`"
    >
        <span
            class="flex items-center justify-between bg-plate px-1.5 font-sans font-bold leading-none text-white"
            :class="size === 'sm' ? 'h-[9px] text-[5.5px]' : 'h-[11px] text-[6.5px]'"
            aria-hidden="true"
        >
            <span class="tracking-[0.18em]">BRASIL</span>
            <span>BR</span>
        </span>
        <span
            class="plate block text-center font-mono font-semibold leading-none tracking-[0.08em] text-ink-950"
            :class="size === 'sm' ? 'py-[3px] text-[13px]' : 'py-1 text-[15px]'"
            >{{ plate }}</span
        >
    </span>
</template>
