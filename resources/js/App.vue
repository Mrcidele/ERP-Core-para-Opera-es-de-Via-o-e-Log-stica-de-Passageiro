<script setup lang="ts">
import { RouterView, useRoute } from 'vue-router';
import AppToasts from '@/core/components/AppToasts.vue';
import AppShell from '@/core/layout/AppShell.vue';

const route = useRoute();
</script>

<template>
    <AppToasts />
    <!-- O login ocupa a tela inteira; as demais rotas ficam dentro da casca com a navegação. -->
    <RouterView v-if="route.meta.public" />
    <AppShell v-else />
</template>
