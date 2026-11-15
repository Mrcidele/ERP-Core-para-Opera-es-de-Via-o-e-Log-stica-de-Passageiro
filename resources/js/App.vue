<script setup lang="ts">
import { RouterView, useRoute } from 'vue-router';
import AppShell from '@/core/ui/AppShell.vue';
import ToastContainer from '@/core/ui/ToastContainer.vue';

// O login ocupa a tela inteira; as demais rotas ficam dentro da casca com a navegação.
const route = useRoute();
</script>

<template>
    <RouterView v-if="route.meta.public" />
    <AppShell v-else />
    <ToastContainer />
</template>
