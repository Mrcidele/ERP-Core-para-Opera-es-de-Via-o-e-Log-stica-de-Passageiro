<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from '@/core/ui/AppIcon.vue';
import { formatDuration } from '@/core/utils/format';
import { useLineForm } from '../composables/useLineForm';
import { MAX_LINE_STOPS } from '../schemas/lineSchema';
import type { Line } from '../types/trip';
import RouteStrip from './RouteStrip.vue';

const props = withDefaults(defineProps<{ line?: Line | null }>(), { line: null });
const emit = defineEmits<{ saved: [line: Line]; cancel: [] }>();

const { form, errors, canAddStop, addStop, removeStop, moveStop, submit, store } = useLineForm(props.line, (saved) => emit('saved', saved));

const distance = computed(() => (typeof form.distance_km === 'number' ? form.distance_km : 0));

// Prévia do itinerário com as paradas já preenchidas.
const previewStops = computed(() =>
    form.stops.flatMap((stop) =>
        stop.city_name.trim() !== '' && typeof stop.cumulative_km === 'number' ? [{ city_name: stop.city_name, cumulative_km: stop.cumulative_km }] : [],
    ),
);

const stopError = (index: number, field: 'city_name' | 'cumulative_km' | 'estimated_minutes'): string | undefined => errors.value[`stops.${index}.${field}`];
const rowError = (index: number): string | undefined =>
    stopError(index, 'city_name') ?? stopError(index, 'cumulative_km') ?? stopError(index, 'estimated_minutes');
</script>

<template>
    <form class="space-y-5" novalidate @submit.prevent="submit">
        <p v-if="store.saveError" role="alert" data-test="error" class="rounded-md bg-brake-100 px-4 py-3 text-sm font-medium text-brake-700">
            {{ store.saveError }}
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="field-label" for="line-origin">Origem</label>
                <input id="line-origin" v-model="form.origin" data-test="origin" class="field-input" maxlength="120" autocomplete="off" :aria-invalid="Boolean(errors.origin)" />
                <p v-if="errors.origin" data-test="origin-error" class="field-error">{{ errors.origin }}</p>
            </div>
            <div>
                <label class="field-label" for="line-destination">Destino</label>
                <input
                    id="line-destination"
                    v-model="form.destination"
                    data-test="destination"
                    class="field-input"
                    maxlength="120"
                    autocomplete="off"
                    :aria-invalid="Boolean(errors.destination)"
                />
                <p v-if="errors.destination" data-test="destination-error" class="field-error">{{ errors.destination }}</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="field-label" for="line-distance">Distância total (km)</label>
                <input
                    id="line-distance"
                    v-model.number="form.distance_km"
                    data-test="distance_km"
                    class="field-input num"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    :aria-invalid="Boolean(errors.distance_km)"
                />
                <p v-if="errors.distance_km" data-test="distance_km-error" class="field-error">{{ errors.distance_km }}</p>
                <p v-else class="field-hint">Somada à quilometragem do veículo a cada viagem concluída.</p>
            </div>
            <div>
                <label class="field-label" for="line-duration">Duração estimada (min)</label>
                <input
                    id="line-duration"
                    v-model.number="form.estimated_duration_minutes"
                    data-test="estimated_duration_minutes"
                    class="field-input num"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    :aria-invalid="Boolean(errors.estimated_duration_minutes)"
                />
                <p v-if="errors.estimated_duration_minutes" data-test="estimated_duration_minutes-error" class="field-error">
                    {{ errors.estimated_duration_minutes }}
                </p>
                <p v-else-if="typeof form.estimated_duration_minutes === 'number' && form.estimated_duration_minutes > 0" class="field-hint num">
                    {{ formatDuration(form.estimated_duration_minutes) }} de viagem
                </p>
            </div>
        </div>

        <fieldset>
            <legend class="field-label">Paradas</legend>
            <p class="field-hint !mt-0.5">Coletas, entregas e passagens, na ordem em que o caminhão passa. O km e o tempo contam a partir da origem.</p>

            <div v-if="distance > 0" class="mt-3 rounded-lg border border-ink-200 bg-ink-50/60 px-4 py-3" data-test="route-preview">
                <RouteStrip :distance-km="distance" :stops="previewStops" />
                <div class="mt-1.5 flex justify-between gap-4 text-xs text-ink-500">
                    <span class="truncate">{{ form.origin.trim() || 'Origem' }}</span>
                    <span class="truncate text-right">{{ form.destination.trim() || 'Destino' }}</span>
                </div>
            </div>

            <p v-if="errors.stops" role="alert" data-test="stops-error" class="field-error">{{ errors.stops }}</p>

            <!-- Cabeçalho das colunas em telas largas; no celular cada campo mostra o próprio rótulo. -->
            <div v-if="form.stops.length > 0" class="mt-3 hidden items-end gap-3 px-3 text-xs font-medium text-ink-500 sm:flex" aria-hidden="true">
                <span class="w-5 shrink-0" />
                <div class="grid min-w-0 flex-1 grid-cols-[minmax(0,1fr)_6rem_6rem] gap-2">
                    <span>Cidade</span>
                    <span>Km acumulado</span>
                    <span>Min. de viagem</span>
                </div>
                <span class="w-[7.25rem] shrink-0" />
            </div>

            <ol v-if="form.stops.length > 0" class="mt-1.5 space-y-2">
                <li
                    v-for="(stop, index) in form.stops"
                    :key="stop.key"
                    data-test="stop-row"
                    class="rounded-lg border bg-white p-3"
                    :class="rowError(index) ? 'border-brake-600/40' : 'border-ink-200'"
                >
                    <div class="flex items-start gap-3">
                        <span class="num mt-7 w-5 shrink-0 text-right text-sm font-semibold text-ink-400 sm:mt-2" aria-hidden="true">{{ index + 1 }}</span>

                        <div class="grid min-w-0 flex-1 grid-cols-2 gap-2 sm:grid-cols-[minmax(0,1fr)_6rem_6rem]">
                            <div class="col-span-2 sm:col-span-1">
                                <label class="stop-label" :for="`stop-city-${stop.key}`">Cidade<span class="sr-only"> da parada {{ index + 1 }}</span></label>
                                <input
                                    :id="`stop-city-${stop.key}`"
                                    v-model="stop.city_name"
                                    data-test="stop-city"
                                    class="field-input !mt-0"
                                    maxlength="120"
                                    autocomplete="off"
                                    :aria-invalid="Boolean(stopError(index, 'city_name'))"
                                />
                            </div>
                            <div>
                                <label class="stop-label" :for="`stop-km-${stop.key}`">Km<span class="sr-only"> acumulado da parada {{ index + 1 }}</span></label>
                                <input
                                    :id="`stop-km-${stop.key}`"
                                    v-model.number="stop.cumulative_km"
                                    data-test="stop-km"
                                    class="field-input num !mt-0"
                                    type="number"
                                    inputmode="numeric"
                                    min="0"
                                    :aria-invalid="Boolean(stopError(index, 'cumulative_km'))"
                                />
                            </div>
                            <div>
                                <label class="stop-label" :for="`stop-minutes-${stop.key}`">Minutos<span class="sr-only"> de viagem até a parada {{ index + 1 }}</span></label>
                                <input
                                    :id="`stop-minutes-${stop.key}`"
                                    v-model.number="stop.estimated_minutes"
                                    data-test="stop-minutes"
                                    class="field-input num !mt-0"
                                    type="number"
                                    inputmode="numeric"
                                    min="0"
                                    :aria-invalid="Boolean(stopError(index, 'estimated_minutes'))"
                                />
                            </div>
                        </div>

                        <div class="flex shrink-0 gap-0.5">
                            <button
                                type="button"
                                class="icon-btn disabled:cursor-not-allowed disabled:opacity-30"
                                data-test="stop-up"
                                :disabled="index === 0"
                                :aria-label="`Subir a parada ${index + 1}`"
                                @click="moveStop(index, -1)"
                            >
                                <AppIcon name="arrow-up" />
                            </button>
                            <button
                                type="button"
                                class="icon-btn disabled:cursor-not-allowed disabled:opacity-30"
                                data-test="stop-down"
                                :disabled="index === form.stops.length - 1"
                                :aria-label="`Descer a parada ${index + 1}`"
                                @click="moveStop(index, 1)"
                            >
                                <AppIcon name="arrow-down" />
                            </button>
                            <button
                                type="button"
                                class="icon-btn hover:!bg-brake-50 hover:!text-brake-700"
                                data-test="stop-remove"
                                :aria-label="`Remover a parada ${index + 1}`"
                                @click="removeStop(index)"
                            >
                                <AppIcon name="trash" />
                            </button>
                        </div>
                    </div>
                    <p v-if="rowError(index)" data-test="stop-error" class="field-error ml-8">{{ rowError(index) }}</p>
                </li>
            </ol>
            <p v-else class="mt-3 rounded-lg border border-dashed border-ink-200 px-4 py-3 text-sm text-ink-500" data-test="no-stops">
                Rota direta. Adicione as cidades em que o caminhão para para coletar ou entregar.
            </p>

            <button type="button" class="btn btn-quiet btn-sm mt-3" data-test="add-stop" :disabled="!canAddStop" @click="addStop">
                <AppIcon name="plus" />
                Adicionar parada
            </button>
            <p v-if="!canAddStop" class="field-hint">Limite de {{ MAX_LINE_STOPS }} paradas por rota.</p>
        </fieldset>

        <div class="drawer-actions">
            <button type="button" class="btn btn-quiet" data-test="cancel" @click="emit('cancel')">Cancelar</button>
            <button type="submit" class="btn btn-primary" data-test="submit" :disabled="store.saveStatus === 'loading'">
                {{ store.saveStatus === 'loading' ? 'Salvando…' : line ? 'Salvar alterações' : 'Cadastrar rota' }}
            </button>
        </div>
    </form>
</template>
