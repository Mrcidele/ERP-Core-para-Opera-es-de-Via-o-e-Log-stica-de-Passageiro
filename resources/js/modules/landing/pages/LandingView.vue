<script setup lang="ts">
import { RouterLink } from 'vue-router';
import AppIcon, { type IconName } from '@/core/ui/AppIcon.vue';
import { CONTACT, WHATSAPP_MESSAGE } from '../contact';

// O texto fixo identifica, na conversa do WhatsApp, o lead que veio por esta página.
const whatsappUrl = `https://wa.me/${CONTACT.whatsapp}?text=${encodeURIComponent(WHATSAPP_MESSAGE)}`;
// Arquivos servidos de public/media: ligados por variável para o Vite não tentar empacotá-los.
const media = { video: '/media/erp-frotacarga-demo.mp4', poster: '/media/demo-capa.jpg' };
const emailUrl = `mailto:${CONTACT.email}?subject=${encodeURIComponent('Demonstração do ERP FrotaCarga')}`;

// Cada bloco mostra uma tela real com a regra que ela aplica, na ordem em que a dor aparece no dia a dia.
const features: { title: string; text: string; image: string; alt: string }[] = [
    {
        title: 'Diesel por caminhão, comparado com a meta dele',
        text: 'Cada abastecimento registra hodômetro, litros e valor, e o km/L do trecho é calculado na hora. Cavalo mecânico, bitruck e VUC têm metas diferentes, e cada caminhão pode ter a sua. Abaixo da meta, o alerta de consumo alto aparece na lista e fica gravado na auditoria.',
        image: '/media/tela-abastecimento.jpg',
        alt: 'Tela de abastecimento com um trecho de 1,50 km/L marcado como consumo alto, abaixo da meta de 2,0 km/L.',
    },
    {
        title: 'Escala que respeita a oficina e o descanso do motorista',
        text: 'Caminhão com ordem de serviço aberta não entra em viagem até a oficina concluir. Antes de gravar cada viagem, o sistema confere a CNH válida até a chegada e as 11 horas de descanso desde a viagem anterior.',
        image: '/media/tela-escala.jpg',
        alt: 'Formulário de agendamento recusando um motorista sem 11 horas de descanso entre viagens.',
    },
    {
        title: 'Frota organizada por tipo, carga útil e revisão',
        text: 'Utilitário, VUC, toco, truck, bitruck e cavalo mecânico, cada um com modelo, carga útil e meta de km/L. A revisão preventiva é programada por quilometragem e aparece como vencida quando o hodômetro passa do ponto.',
        image: '/media/tela-frota.jpg',
        alt: 'Lista da frota com tipo de caminhão, carga útil em quilos, meta de km/L e barra de revisão preventiva.',
    },
    {
        title: 'Auditoria que ninguém apaga',
        text: 'Quem fez, quando e o que mudou em abastecimentos, viagens, ordens de serviço e cadastros. O banco de dados recusa editar ou apagar esse histórico, o que dá base para cobrar consumo e prazos.',
        image: '/media/tela-auditoria.jpg',
        alt: 'Detalhe de auditoria de um abastecimento, com rendimento, meta e alerta de consumo registrados.',
    },
];

const segments: { icon: IconName; title: string; text: string }[] = [
    { icon: 'truck', title: 'Carga lotação', text: 'Cavalos e bitrucks em viagens longas, onde a média de diesel decide a margem.' },
    { icon: 'route', title: 'Distribuição regional', text: 'VUCs, tocos e trucks com escala diária e muitas saídas por dia.' },
    { icon: 'wrench', title: 'Frota própria', text: 'Indústrias e cooperativas que controlam os próprios caminhões em planilhas.' },
];

const notIncluded = [
    'Emissão de CT-e, MDF-e e CIOT: continua no emissor fiscal que você já usa.',
    'Controle de pneus por eixo e gestão separada de carreta.',
    'Integração com rastreador, telemetria ou cartão-combustível.',
];
</script>

<template>
    <div class="min-h-screen bg-white text-ink-900">
        <header class="bg-ink-950">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                <span class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-signal-500 text-ink-950"><AppIcon name="truck" /></span>
                    <span class="font-display text-lg font-semibold tracking-tight text-white">ERP FrotaCarga</span>
                </span>
                <RouterLink :to="{ name: 'login' }" data-test="sign-in" class="text-sm font-medium text-ink-300 hover:text-white">Entrar</RouterLink>
            </div>
        </header>

        <section class="bg-ink-950 pb-20 text-ink-300">
            <div class="mx-auto grid max-w-6xl items-center gap-12 px-6 pt-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
                <div>
                    <h1 class="font-display text-4xl font-semibold leading-[1.1] tracking-tight text-white sm:text-5xl">
                        O diesel, a oficina e a escala da sua transportadora no mesmo controle.
                    </h1>
                    <p class="mt-6 max-w-xl text-lg text-ink-400">
                        O ERP FrotaCarga compara o km/L de cada caminhão com a meta dele, tira da escala o caminhão que está na oficina e confere CNH e
                        descanso do motorista antes de cada viagem.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a :href="whatsappUrl" data-test="cta-whatsapp" class="btn btn-primary !bg-signal-500 !text-ink-950 hover:!bg-signal-400" target="_blank" rel="noopener">
                            Agendar demonstração
                        </a>
                        <a href="#video" class="btn btn-quiet !border-white/15 !bg-transparent !text-white hover:!bg-white/10">Ver o vídeo (1 min)</a>
                    </div>
                </div>
                <figure id="video" class="overflow-hidden rounded-xl ring-1 ring-white/10">
                    <video
                        data-test="demo-video"
                        class="block aspect-video w-full bg-ink-900"
                        :src="media.video"
                        :poster="media.poster"
                        controls
                        preload="none"
                        playsinline
                    >
                        <track kind="captions" />
                    </video>
                </figure>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-6 py-20">
            <h2 class="max-w-2xl font-display text-3xl font-semibold tracking-tight text-ink-950">O que o gestor de frota controla no dia a dia</h2>
            <div class="mt-14 space-y-20">
                <article
                    v-for="(feature, index) in features"
                    :key="feature.title"
                    data-test="feature"
                    class="grid items-center gap-8 lg:grid-cols-2"
                >
                    <div :class="index % 2 === 1 ? 'lg:order-2' : ''">
                        <h3 class="font-display text-2xl font-semibold tracking-tight text-ink-950">{{ feature.title }}</h3>
                        <p class="mt-4 text-ink-600">{{ feature.text }}</p>
                    </div>
                    <img :src="feature.image" :alt="feature.alt" loading="lazy" class="w-full rounded-xl shadow-sm ring-1 ring-ink-200" />
                </article>
            </div>
        </section>

        <section class="border-y border-ink-200 bg-ink-50">
            <div class="mx-auto grid max-w-6xl gap-10 px-6 py-16 lg:grid-cols-3">
                <div v-for="segment in segments" :key="segment.title" data-test="segment">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-ink-700 ring-1 ring-ink-200"><AppIcon :name="segment.icon" /></span>
                    <h3 class="mt-4 font-display text-lg font-semibold text-ink-950">{{ segment.title }}</h3>
                    <p class="mt-2 text-sm text-ink-600">{{ segment.text }}</p>
                </div>
            </div>
        </section>

        <section class="mx-auto grid max-w-6xl gap-12 px-6 py-20 lg:grid-cols-2">
            <div>
                <h2 class="font-display text-2xl font-semibold tracking-tight text-ink-950">O que ele não faz</h2>
                <p class="mt-3 text-ink-600">O FrotaCarga cuida da operação e complementa os sistemas que a transportadora já tem.</p>
                <ul class="mt-6 space-y-3" data-test="not-included">
                    <li v-for="item in notIncluded" :key="item" class="flex gap-3 text-ink-700">
                        <span class="mt-0.5 text-ink-400"><AppIcon name="close" /></span>
                        {{ item }}
                    </li>
                </ul>
            </div>
            <div class="rounded-xl bg-ink-950 p-8 text-ink-300">
                <h2 class="font-display text-2xl font-semibold tracking-tight text-white">Piloto de 30 dias com parte da sua frota</h2>
                <p class="mt-3">
                    Começamos com 5 a 10 caminhões. Eu cadastro a frota junto com você e, no fim, olhamos juntos o km/L de cada caminhão contra a meta, as
                    ordens de serviço e as viagens recusadas.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a :href="whatsappUrl" data-test="cta-whatsapp" class="btn btn-primary !bg-signal-500 !text-ink-950 hover:!bg-signal-400" target="_blank" rel="noopener">Falar pelo WhatsApp</a>
                    <a :href="emailUrl" data-test="cta-email" class="btn btn-quiet !border-white/15 !bg-transparent !text-white hover:!bg-white/10">Mandar e-mail</a>
                </div>
            </div>
        </section>

        <footer class="border-t border-ink-200">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-8 text-sm text-ink-500">
                <p>
                    {{ CONTACT.name }}, criador do ERP FrotaCarga ·
                    <a :href="emailUrl" class="hover:text-ink-900">{{ CONTACT.email }}</a> ·
                    <span class="num">{{ CONTACT.phone }}</span>
                </p>
                <RouterLink :to="{ name: 'login' }" class="hover:text-ink-900">Entrar no sistema</RouterLink>
            </div>
        </footer>
    </div>
</template>
