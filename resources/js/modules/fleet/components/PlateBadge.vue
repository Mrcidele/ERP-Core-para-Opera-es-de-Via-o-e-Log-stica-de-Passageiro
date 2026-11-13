<script setup lang="ts">
defineProps<{ plate: string }>();
</script>

<template>
    <!-- Lembra a placa Mercosul: faixa azul no topo, caracteres pretos sobre branco. -->
    <span class="plate">{{ plate }}</span>
</template>

<style scoped>
.plate {
    @apply inline-block rounded border border-ink-300 bg-white px-2.5 pb-0.5 pt-1.5 font-display text-base font-bold tabular-nums tracking-widest text-ink-900;
    background-image: linear-gradient(theme('colors.blue.700'), theme('colors.blue.700'));
    background-repeat: no-repeat;
    background-size: 100% 5px;
}
</style>
