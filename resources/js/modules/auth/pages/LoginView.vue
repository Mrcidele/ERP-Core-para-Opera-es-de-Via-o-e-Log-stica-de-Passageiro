<script setup lang="ts">
import { computed, defineAsyncComponent, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon, { type IconName } from '@/core/ui/AppIcon.vue';
import { loginSchema, type LoginField } from '../schemas/loginSchema';
import { useAuthStore } from '../stores/authStore';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = reactive({ email: '', password: '' });

// Resumo do que o sistema controla, no painel de identidade.
const highlights: { icon: IconName; title: string; text: string }[] = [
    { icon: 'truck', title: 'Frota', text: 'Hodômetro, revisão preventiva e meta de km/L de cada caminhão.' },
    { icon: 'route', title: 'Viagens', text: 'Escala sem conflito de caminhão, com as 11 horas de descanso da Lei do Motorista.' },
    { icon: 'license', title: 'Motoristas', text: 'CNH válida até a chegada de cada viagem.' },
    { icon: 'wrench', title: 'Oficina', text: 'OS que tira o caminhão da escala e devolve quando a manutenção termina.' },
];
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
    <div class="min-h-screen lg:grid lg:grid-cols-[minmax(0,11fr)_minmax(0,13fr)]">
        <aside class="relative overflow-hidden bg-ink-950 px-6 py-8 text-ink-300 sm:px-10 lg:flex lg:flex-col lg:px-14 lg:py-12">
            <div class="relative flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-signal-500 text-ink-950">
                    <AppIcon name="truck" />
                </span>
                <span class="font-display text-lg font-semibold tracking-tight text-white">ERP FrotaCarga</span>
            </div>

            <div class="relative mt-10 hidden lg:my-auto lg:block">
                <p class="max-w-md font-display text-4xl font-semibold leading-[1.1] tracking-tight text-white">
                    A operação inteira na mesma escala.
                </p>
                <p class="mt-4 max-w-md text-ink-400">Do caminhão no pátio à entrega concluída, com as regras da transportadora conferidas a cada passo.</p>

                <ul class="mt-10 max-w-md space-y-5" data-test="operation-summary">
                    <li v-for="item in highlights" :key="item.title" class="flex gap-4">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/[0.06] text-signal-500 ring-1 ring-inset ring-white/10">
                            <AppIcon :name="item.icon" />
                        </span>
                        <div>
                            <p class="text-sm font-medium text-white">{{ item.title }}</p>
                            <p class="mt-0.5 text-sm text-ink-400">{{ item.text }}</p>
                        </div>
                    </li>
                </ul>
            </div>

            <p class="relative mt-6 hidden text-xs text-ink-500 lg:block">Acesso restrito à equipe da operação. Toda ação crítica fica registrada.</p>
        </aside>

        <main class="flex items-center justify-center bg-white px-4 py-10 sm:px-8">
            <div class="w-full max-w-sm">
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950">Entrar</h1>
                <p class="mt-1 text-sm text-ink-500">Use o e-mail e a senha da sua conta.</p>

                <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
                    <p v-if="auth.loginError" role="alert" data-test="error" class="rounded-lg border border-brake-600/20 bg-brake-50 px-4 py-3 text-sm font-medium text-brake-700">
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
