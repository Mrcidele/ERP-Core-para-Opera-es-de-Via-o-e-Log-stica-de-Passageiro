<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router';

const route = useRoute();
</script>

<template>
    <header>
        <h1 class="font-display text-3xl font-semibold tracking-tight">{{ route.meta.title }}</h1>
    </header>

    <section class="mt-8 max-w-xl rounded-xl border border-dashed border-ink-200 bg-white p-8">
        <h2 class="font-display text-lg font-semibold">Este módulo ainda não está disponível</h2>
        <p class="mt-2 text-ink-600">{{ route.meta.upcoming }}</p>
        <RouterLink :to="{ name: 'fleet' }" class="btn btn-quiet mt-6">Ir para a frota</RouterLink>
    </section>
</template>
