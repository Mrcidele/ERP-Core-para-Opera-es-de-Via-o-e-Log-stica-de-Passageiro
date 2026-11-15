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
    <section data-test="test-profiles" class="mt-8 rounded-lg border border-dashed border-ink-200 p-4">
        <h2 class="text-sm font-medium text-ink-800">Perfis de teste</h2>
        <p class="mt-0.5 text-sm text-ink-500">Só aparecem em desenvolvimento. Preenchem o e-mail e a senha do seeder.</p>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <button
                v-for="profile in profiles"
                :key="profile.role"
                type="button"
                class="btn btn-sm btn-quiet"
                :data-test="`fill-${profile.role}`"
                @click="emit('fill', profile.email, TEST_PASSWORD)"
            >
                {{ USER_ROLE_LABELS[profile.role] }}
            </button>
        </div>
    </section>
</template>
