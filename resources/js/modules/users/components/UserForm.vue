<script setup lang="ts">
import { USER_ROLE_LABELS, USER_ROLES } from '@/modules/auth/types/auth';
import { useUserForm } from '../composables/useUserForm';
import type { ManagedUser } from '../types/user';

const props = withDefaults(defineProps<{ user?: ManagedUser | null }>(), { user: null });
const emit = defineEmits<{ saved: [user: ManagedUser]; cancel: [] }>();

const { form, errors, submit, store } = useUserForm(props.user, (saved) => emit('saved', saved));

const ROLE_HINTS: Record<(typeof USER_ROLES)[number], string> = {
    admin: 'Acesso total, inclusive usuários e auditoria.',
    dispatcher: 'Escala, viagens e linhas.',
    fleet_manager: 'Frota, abastecimento e manutenção.',
    mechanic_supervisor: 'Ordens de serviço da oficina.',
};
</script>

<template>
    <form class="space-y-5" novalidate @submit.prevent="submit">
        <p v-if="store.saveError" role="alert" data-test="error" class="rounded-md bg-brake-100 px-4 py-3 text-sm font-medium text-brake-700">
            {{ store.saveError }}
        </p>

        <div>
            <label class="field-label" for="user-name">Nome</label>
            <input id="user-name" v-model="form.name" data-test="name" class="field-input" autocomplete="off" :aria-invalid="Boolean(errors.name)" />
            <p v-if="errors.name" data-test="name-error" class="field-error">{{ errors.name }}</p>
        </div>

        <div>
            <label class="field-label" for="user-email">E-mail</label>
            <input
                id="user-email"
                v-model="form.email"
                data-test="email"
                class="field-input"
                type="email"
                autocomplete="off"
                :aria-invalid="Boolean(errors.email)"
            />
            <p v-if="errors.email" data-test="email-error" class="field-error">{{ errors.email }}</p>
            <p v-else class="field-hint">É o login da pessoa no sistema.</p>
        </div>

        <fieldset>
            <legend class="field-label">Perfil de acesso</legend>
            <div class="mt-1.5 grid gap-2 sm:grid-cols-2">
                <label
                    v-for="role in USER_ROLES"
                    :key="role"
                    class="flex cursor-pointer flex-col rounded-md border px-3 py-2.5 transition-colors"
                    :class="form.role === role ? 'border-ink-900 bg-ink-900 text-white' : 'border-ink-200 bg-white text-ink-700 hover:border-ink-300 hover:bg-ink-50'"
                >
                    <input v-model="form.role" type="radio" name="user-role" class="sr-only" :value="role" :data-test="`role-${role}`" />
                    <span class="text-sm font-medium">{{ USER_ROLE_LABELS[role] }}</span>
                    <span class="text-xs" :class="form.role === role ? 'text-ink-200' : 'text-ink-500'">{{ ROLE_HINTS[role] }}</span>
                </label>
            </div>
            <p v-if="errors.role" data-test="role-error" class="field-error">{{ errors.role }}</p>
        </fieldset>

        <div>
            <label class="field-label" for="user-password">{{ user ? 'Nova senha' : 'Senha' }}</label>
            <input
                id="user-password"
                v-model="form.password"
                data-test="password"
                class="field-input"
                type="password"
                autocomplete="new-password"
                :aria-invalid="Boolean(errors.password)"
            />
            <p v-if="errors.password" data-test="password-error" class="field-error">{{ errors.password }}</p>
            <p v-else class="field-hint">
                {{ user ? 'Deixe em branco para manter a senha atual. Ao trocar, as sessões abertas da pessoa são encerradas.' : 'Mínimo de 12 caracteres, com maiúsculas, minúsculas e números.' }}
            </p>
        </div>

        <div v-if="!user || form.password !== ''">
            <label class="field-label" for="user-password-confirmation">Repita a senha</label>
            <input
                id="user-password-confirmation"
                v-model="form.password_confirmation"
                data-test="password_confirmation"
                class="field-input"
                type="password"
                autocomplete="new-password"
                :aria-invalid="Boolean(errors.password_confirmation)"
            />
            <p v-if="errors.password_confirmation" data-test="password_confirmation-error" class="field-error">{{ errors.password_confirmation }}</p>
        </div>

        <div class="drawer-actions">
            <button type="button" class="btn btn-quiet" data-test="cancel" @click="emit('cancel')">Cancelar</button>
            <button type="submit" class="btn btn-primary" data-test="submit" :disabled="store.saveStatus === 'loading'">
                {{ store.saveStatus === 'loading' ? 'Salvando…' : user ? 'Salvar alterações' : 'Criar usuário' }}
            </button>
        </div>
    </form>
</template>
