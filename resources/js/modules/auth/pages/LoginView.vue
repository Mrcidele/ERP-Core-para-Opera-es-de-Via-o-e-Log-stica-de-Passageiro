<script setup lang="ts">
import { computed, defineAsyncComponent, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '@/core/components/AppIcon.vue';
import { loginSchema, type LoginField } from '../schemas/loginSchema';
import { useAuthStore } from '../stores/authStore';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = reactive({ email: '', password: '' });
const clientErrors = ref<Partial<Record<LoginField, string>>>({});

// Atalho dos perfis de teste: o componente (e a senha do seeder que ele contém) só é
// importado no build de desenvolvimento; em produção o import nem é gerado.
const TestProfiles = import.meta.env.DEV ? defineAsyncComponent(() => import('../components/TestProfiles.vue')) : null;

/** Erro exibido por campo: validação local primeiro, depois a resposta da API. */
const errors = computed<Record<LoginField, string | null>>(() => ({
    email: clientErrors.value.email ?? auth.validationErrors.email?.[0] ?? null,
    password: clientErrors.value.password ?? auth.validationErrors.password?.[0] ?? null,
}));

function fill(email: string, password: string): void {
    form.email = email;
    form.password = password;
    clientErrors.value = {};
}

/** Só aceita caminhos internos como destino depois do login. */
function destination(): string {
    const redirect = route.query.redirect;

    return typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/';
}

async function submit(): Promise<void> {
    const result = loginSchema.safeParse(form);

    if (!result.success) {
        const fieldErrors: Partial<Record<LoginField, string>> = {};

        for (const issue of result.error.issues) {
            fieldErrors[issue.path[0] as LoginField] ??= issue.message;
        }

        clientErrors.value = fieldErrors;

        return;
    }

    clientErrors.value = {};

    if (await auth.login(result.data)) {
        await router.push(destination());
    }
}
</script>

<template>
    <div class="min-h-screen lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
        <aside class="relative overflow-hidden bg-ink-900 px-6 py-8 text-ink-200 sm:px-10 lg:flex lg:flex-col lg:justify-between lg:py-12">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-md bg-signal-500 text-ink-900">
                    <AppIcon name="bus" />
                </span>
                <span class="font-display text-xl font-semibold tracking-tight text-white">ERP Viação</span>
            </div>

            <div class="mt-8 hidden lg:block">
                <p class="max-w-sm font-display text-4xl font-semibold leading-tight tracking-tight text-white">
                    Frota, tripulação, viagens e oficina na mesma escala.
                </p>
                <!-- Faixa da pista: o mesmo âmbar do item ativo da navegação. -->
                <div class="mt-8 flex gap-3" aria-hidden="true">
                    <span v-for="dash in 6" :key="dash" class="h-1.5 w-12 rounded-full bg-signal-500" :class="dash > 3 ? 'opacity-30' : ''" />
                </div>
            </div>

            <p class="mt-6 hidden text-sm text-ink-300 lg:block">Acesso restrito à equipe da operação.</p>
        </aside>

        <main class="flex items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-sm">
                <h1 class="font-display text-3xl font-semibold tracking-tight">Entrar</h1>
                <p class="mt-1 text-ink-500">Use o e-mail e a senha da sua conta.</p>

                <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
                    <p v-if="auth.loginError" role="alert" data-test="error" class="rounded-md bg-brake-100 px-4 py-3 text-sm font-medium text-brake-700">
                        {{ auth.loginError }}
                    </p>

                    <div>
                        <label class="field-label" for="login-email">E-mail</label>
                        <input
                            id="login-email"
                            v-model="form.email"
                            data-test="email"
                            class="field-input"
                            type="email"
                            autocomplete="username"
                            :aria-invalid="Boolean(errors.email)"
                        />
                        <p v-if="errors.email" data-test="email-error" class="field-error">{{ errors.email }}</p>
                    </div>

                    <div>
                        <label class="field-label" for="login-password">Senha</label>
                        <input
                            id="login-password"
                            v-model="form.password"
                            data-test="password"
                            class="field-input"
                            type="password"
                            autocomplete="current-password"
                            :aria-invalid="Boolean(errors.password)"
                        />
                        <p v-if="errors.password" data-test="password-error" class="field-error">{{ errors.password }}</p>
                    </div>

                    <button type="submit" class="btn btn-primary w-full" data-test="submit" :disabled="auth.loginStatus === 'loading'">
                        {{ auth.loginStatus === 'loading' ? 'Entrando…' : 'Entrar' }}
                    </button>
                </form>

                <component :is="TestProfiles" v-if="TestProfiles" @fill="fill" />
            </div>
        </main>
    </div>
</template>
