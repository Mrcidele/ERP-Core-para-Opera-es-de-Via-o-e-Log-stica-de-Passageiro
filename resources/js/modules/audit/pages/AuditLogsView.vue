<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import EmptyState from '@/core/ui/EmptyState.vue';
import ListToolbar from '@/core/ui/ListToolbar.vue';
import PaginationBar from '@/core/ui/PaginationBar.vue';
import SlideOver from '@/core/ui/SlideOver.vue';
import TableSkeleton from '@/core/ui/TableSkeleton.vue';
import { formatDateTime } from '@/core/utils/format';
import { useAuditLogStore } from '../stores/auditLogStore';
import { auditActionLabel, auditEntityLabel, isSensitiveAction, type AuditLog, type AuditValues } from '../types/auditLog';

const store = useAuditLogStore();

const selected = ref<AuditLog | null>(null);

onMounted(() => store.fetchLogs());

/** JSON com recuo, como aparece no painel de antes e depois. */
const pretty = (values: AuditValues | null): string => (values === null ? '' : JSON.stringify(values, null, 2));

/** Chaves cujo valor mudou entre o antes e o depois (ou que só existem de um lado). */
const changedKeys = computed(() => {
    const before = selected.value?.old_values ?? {};
    const after = selected.value?.new_values ?? {};

    return [...new Set([...Object.keys(before), ...Object.keys(after)])].filter((key) => JSON.stringify(before[key]) !== JSON.stringify(after[key]));
});
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink-950">Auditoria</h1>
            <p class="mt-1 text-sm text-ink-500">Quem fez o quê nas operações críticas, do mais recente para o mais antigo.</p>
        </div>
        <button type="button" class="btn btn-quiet" data-test="refresh" :disabled="store.status === 'loading' || store.refreshing" @click="store.fetchLogs()">Atualizar</button>
    </header>

    <section class="card mt-6 overflow-hidden">
        <ListToolbar :search="store.search" search-label="Buscar por usuário ou IP" @update:search="store.applyFilters({ search: $event })" />

        <TableSkeleton v-if="store.status === 'idle' || store.status === 'loading'" data-test="loading" :columns="6" label="Carregando registros de auditoria" />

        <EmptyState
            v-else-if="store.status === 'error'"
            data-test="list-error"
            icon="alert"
            title="Não foi possível carregar a auditoria"
            description="Verifique a conexão e tente de novo."
        >
            <button type="button" class="btn btn-quiet" @click="store.fetchLogs()">Tentar novamente</button>
        </EmptyState>

        <EmptyState
            v-else-if="store.logs.length === 0 && !store.filtering"
            data-test="empty"
            icon="shield"
            title="Nenhum registro de auditoria"
            description="Exclusões de veículo, viagens concluídas ou canceladas, ordens de serviço e logins recusados aparecem aqui."
        />

        <EmptyState v-else-if="store.logs.length === 0" data-test="no-results" icon="search" title="Nenhum registro corresponde à busca">
            <button type="button" class="btn btn-quiet" data-test="clear-filters" @click="store.applyFilters({ search: '' })">Limpar busca</button>
        </EmptyState>

        <template v-else>
            <div class="overflow-x-auto" :class="{ 'opacity-60': store.refreshing }" :aria-busy="store.refreshing">
                <table class="data-table min-w-[860px]">
                    <thead>
                        <tr>
                            <th scope="col">Data e hora</th>
                            <th scope="col">Usuário</th>
                            <th scope="col">Ação</th>
                            <th scope="col">Entidade afetada</th>
                            <th scope="col">IP</th>
                            <th scope="col"><span class="sr-only">Detalhes</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in store.logs" :key="log.id" data-test="audit-row">
                            <td class="num whitespace-nowrap" data-test="created-at">{{ formatDateTime(log.created_at) }}</td>
                            <td class="whitespace-nowrap">
                                <span v-if="log.user" class="font-medium text-ink-900">{{ log.user.name }}</span>
                                <span v-else class="text-ink-400">Sem usuário</span>
                            </td>
                            <td class="whitespace-nowrap">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-md px-1.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                    :class="isSensitiveAction(log.action) ? 'bg-brake-50 text-brake-700 ring-brake-600/20' : 'bg-ink-100 text-ink-700 ring-ink-500/15'"
                                    data-test="action"
                                >
                                    {{ auditActionLabel(log.action) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap">
                                {{ auditEntityLabel(log.auditable_type) }}
                                <span v-if="log.auditable_id" class="num ml-1 text-xs text-ink-400" :title="log.auditable_id">{{ log.auditable_id.slice(0, 8) }}</span>
                            </td>
                            <td class="num whitespace-nowrap text-ink-600">{{ log.ip_address ?? '' }}</td>
                            <td class="text-right">
                                <button type="button" class="btn btn-sm btn-quiet" data-test="details" @click="selected = log">Ver alterações</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <PaginationBar :meta="store.meta" singular="registro" plural="registros" :busy="store.refreshing" @change="store.fetchLogs($event)" />
        </template>
    </section>

    <SlideOver
        :open="selected !== null"
        size="lg"
        :title="selected ? auditActionLabel(selected.action) : ''"
        :subtitle="selected ? `${formatDateTime(selected.created_at)}, por ${selected.user?.name ?? 'ninguém autenticado'}` : ''"
        @close="selected = null"
    >
        <template v-if="selected">
            <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3" data-test="audit-meta">
                <div>
                    <dt class="text-ink-500">Entidade</dt>
                    <dd class="mt-0.5 font-medium text-ink-900">{{ auditEntityLabel(selected.auditable_type) }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-ink-500">Identificador</dt>
                    <dd class="num mt-0.5 truncate text-xs text-ink-900" :title="selected.auditable_id ?? ''">{{ selected.auditable_id ?? 'Sem identificador' }}</dd>
                </div>
                <div>
                    <dt class="text-ink-500">IP</dt>
                    <dd class="num mt-0.5 text-ink-900">{{ selected.ip_address ?? 'Não registrado' }}</dd>
                </div>
            </dl>

            <p v-if="changedKeys.length > 0" class="mt-5 text-sm text-ink-600" data-test="changed-keys">
                Campos alterados:
                <span v-for="key in changedKeys" :key="key" class="num ml-1 rounded bg-signal-50 px-1.5 py-0.5 text-xs font-medium text-signal-700 ring-1 ring-inset ring-signal-600/25">{{ key }}</span>
            </p>

            <!-- Antes e depois, lado a lado. -->
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <section data-test="old-values">
                    <h3 class="text-sm font-medium text-ink-800">Antes</h3>
                    <pre v-if="selected.old_values" class="num mt-1.5 overflow-x-auto rounded-lg border border-ink-200 bg-ink-50 p-3 text-xs leading-relaxed text-ink-800">{{ pretty(selected.old_values) }}</pre>
                    <p v-else class="mt-1.5 rounded-lg border border-dashed border-ink-200 p-3 text-sm text-ink-500">Sem valores anteriores: o registro foi criado nesta ação.</p>
                </section>
                <section data-test="new-values">
                    <h3 class="text-sm font-medium text-ink-800">Depois</h3>
                    <pre v-if="selected.new_values" class="num mt-1.5 overflow-x-auto rounded-lg border border-ink-200 bg-ink-50 p-3 text-xs leading-relaxed text-ink-800">{{ pretty(selected.new_values) }}</pre>
                    <p v-else class="mt-1.5 rounded-lg border border-dashed border-ink-200 p-3 text-sm text-ink-500">Sem valores novos: o registro foi removido nesta ação.</p>
                </section>
            </div>
        </template>

        <template #footer>
            <button type="button" class="btn btn-quiet" data-test="close-details" @click="selected = null">Fechar</button>
        </template>
    </SlideOver>
</template>
