<script setup lang="ts">
import { USER_ROLE_LABELS, type UserRole } from '../types/auth';

// Usuários criados pelo DatabaseSeeder, um por papel. Este componente só é carregado
// em desenvolvimento (ver LoginView): nada daqui entra no build de produção.
const emit = defineEmits<{ fill: [email: string, password: string] }>();

const TEST_PASSWORD = 'Password@123';

const profiles: { role: UserRole; email: string }[] = [
    { role: 'admin', email: 'admin@erp.test' },
    { role: 'dispatcher', email: 'despachante@erp.test' },
    { role: 'fleet_manager', email: 'frota@erp.test' },
    { role: 'mechanic_supervisor', email: 'oficina@erp.test' },
];
</script>

<template>
    <section data-test="test-profiles" class="mt-10 border-t border-ink-200 pt-5">
        <h2 class="text-xs font-medium text-ink-500">Perfis de teste (só em desenvolvimento)</h2>
        <div class="mt-2.5 flex flex-wrap gap-2">
            <button
                v-for="profile in profiles"
                :key="profile.role"
                type="button"
                class="rounded-md border border-ink-200 bg-white px-2.5 py-1 text-xs font-medium text-ink-600 transition-colors hover:border-ink-300 hover:bg-ink-50 hover:text-ink-900"
                :data-test="`fill-${profile.role}`"
                @click="emit('fill', profile.email, TEST_PASSWORD)"
            >
                {{ USER_ROLE_LABELS[profile.role] }}
            </button>
        </div>
    </section>
</template>
