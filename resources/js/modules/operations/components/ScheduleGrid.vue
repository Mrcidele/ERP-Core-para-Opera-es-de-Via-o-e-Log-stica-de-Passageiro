<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from '@/core/ui/AppIcon.vue';
import MercosulPlate from '@/core/ui/MercosulPlate.vue';
import { toDateTimeInput } from '@/core/utils/datetime';
import { MAINTENANCE_TYPE_LABELS } from '@/modules/maintenance/types/maintenance';
import type { ScheduleTimeline, TimelineTrip, TripPrefill } from '../types/timeline';
import { TRIP_STATUS_LABELS } from '../types/trip';

// Grade de escala: uma linha por veículo e por motorista, com o tempo na horizontal.
// Mostra de `start` (meia-noite local do dia escolhido) até o fim da janela que a API devolveu.
const props = withDefaults(
    defineProps<{
        timeline: ScheduleTimeline;
        start: Date;
        /** Agora, para marcar o instante atual e não oferecer horários que já passaram. */
        now?: Date;
        /** Com permissão de agendar, um horário livre abre o formulário já preenchido. */
        canSchedule: boolean;
    }>(),
    { now: () => new Date() },
);

const emit = defineEmits<{ schedule: [prefill: TripPrefill] }>();

const HOUR = 3_600_000;
const SLOT_MINUTES = 30;

const startMs = computed(() => props.start.getTime());
const endMs = computed(() => new Date(props.timeline.to).getTime());
const spanMs = computed(() => Math.max(endMs.value - startMs.value, HOUR));

const time = new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit' });
const dayLabel = new Intl.DateTimeFormat('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' });

interface Block {
    key: string;
    kind: 'trip' | 'rest' | 'maintenance';
    left: string;
    width: string;
    label: string;
    title: string;
    tone: string;
}

/** Posição de um intervalo na faixa, recortado ao que está visível; null se fica todo fora. */
function place(fromMs: number, toMs: number): { left: string; width: string } | null {
    const from = Math.max(fromMs, startMs.value);
    const to = Math.min(toMs, endMs.value);

    if (to <= from) {
        return null;
    }

    return { left: `${((from - startMs.value) / spanMs.value) * 100}%`, width: `${((to - from) / spanMs.value) * 100}%` };
}

const TRIP_TONES: Record<TimelineTrip['status'], string> = {
    scheduled: 'bg-ink-800 text-white',
    in_progress: 'bg-signal-500 text-ink-950',
    completed: 'bg-ink-200 text-ink-600',
};

const period = (fromIso: string, toIso: string): string => `${time.format(new Date(fromIso))} às ${time.format(new Date(toIso))}`;

function tripBlock(trip: TimelineTrip, detail: string): Block | null {
    const position = place(new Date(trip.departure_time).getTime(), new Date(trip.arrival_time).getTime());

    return position === null
        ? null
        : {
              key: `trip-${trip.id}`,
              kind: 'trip',
              ...position,
              label: `${trip.line.origin} → ${trip.line.destination}`,
              title: `${trip.line.origin} → ${trip.line.destination}, ${period(trip.departure_time, trip.arrival_time)}. ${detail}. ${TRIP_STATUS_LABELS[trip.status]}.`,
              tone: TRIP_TONES[trip.status],
          };
}

const vehicleRows = computed(() =>
    props.timeline.vehicles.map((vehicle) => ({
        vehicle,
        // Só veículo ativo pode ser escalado.
        schedulable: props.canSchedule && vehicle.status === 'active',
        blocks: [
            ...vehicle.maintenance_orders.flatMap((order): Block[] => {
                // Sem previsão de término: o bloco vai até o fim da grade.
                const position = place(new Date(order.opened_at).getTime(), endMs.value);

                return position === null
                    ? []
                    : [
                          {
                              key: `order-${order.id}`,
                              kind: 'maintenance',
                              ...position,
                              label: `Oficina: ${MAINTENANCE_TYPE_LABELS[order.type].toLowerCase()}`,
                              title: `Ordem de serviço ${MAINTENANCE_TYPE_LABELS[order.type].toLowerCase()} pendente, sem previsão de término.`,
                              tone: 'timeline-maintenance text-brake-700',
                          },
                      ];
            }),
            ...vehicle.trips.flatMap((trip): Block[] => {
                const block = tripBlock(trip, `Motorista: ${trip.driver.name}`);

                return block ? [block] : [];
            }),
        ],
    })),
);

const driverRows = computed(() =>
    props.timeline.drivers.map((driver) => ({
        driver,
        schedulable: props.canSchedule,
        blocks: driver.trips.flatMap((trip): Block[] => {
            const blocks: Block[] = [];
            const rest = place(new Date(trip.arrival_time).getTime(), new Date(trip.rest_until).getTime());

            if (rest) {
                blocks.push({
                    key: `rest-${trip.id}`,
                    kind: 'rest',
                    ...rest,
                    label: 'Descanso',
                    title: `Descanso obrigatório de ${props.timeline.driver_rest_hours} horas, até ${time.format(new Date(trip.rest_until))}.`,
                    tone: 'timeline-rest text-ink-500',
                });
            }

            const block = tripBlock(trip, `Veículo: ${trip.vehicle.plate}`);

            if (block) {
                blocks.push(block);
            }

            return blocks;
        }),
    })),
);

/** Marcas a cada 3 horas a partir da meia-noite local; as de meia-noite levam o dia. */
const ticks = computed(() => {
    const marks: { key: number; left: string; hour: string; day: string | null }[] = [];

    for (let at = startMs.value; at < endMs.value; at += 3 * HOUR) {
        const moment = new Date(at);

        marks.push({
            key: at,
            left: `${((at - startMs.value) / spanMs.value) * 100}%`,
            hour: `${String(moment.getHours()).padStart(2, '0')}h`,
            day: moment.getHours() === 0 ? dayLabel.format(moment) : null,
        });
    }

    return marks;
});

/** Faixa do passado (não agendável) e posição do instante atual, quando ele está na grade. */
const pastWidth = computed(() => `${Math.min(Math.max((props.now.getTime() - startMs.value) / spanMs.value, 0), 1) * 100}%`);
const nowLeft = computed(() => (props.now.getTime() > startMs.value && props.now.getTime() < endMs.value ? pastWidth.value : null));

/** Instante correspondente a um ponto da faixa, arredondado para baixo em meia hora. */
function slotAt(event: MouseEvent): Date {
    const track = event.currentTarget as HTMLElement;
    const box = track.getBoundingClientRect();
    const ratio = box.width > 0 ? Math.min(Math.max((event.clientX - box.left) / box.width, 0), 1) : 0;
    const slot = SLOT_MINUTES * 60_000;

    return new Date(Math.floor((startMs.value + ratio * spanMs.value) / slot) * slot);
}

function pick(event: MouseEvent, target: Pick<TripPrefill, 'vehicle_id' | 'driver_id'>): void {
    const departure = slotAt(event);

    // Horário que já passou não abre o formulário: a API recusaria a viagem.
    if (departure.getTime() > props.now.getTime()) {
        emit('schedule', { ...target, departure_time: toDateTimeInput(departure) });
    }
}
</script>

<template>
    <div class="overflow-x-auto" data-test="schedule-grid">
        <div class="min-w-[1080px]">
            <!-- Régua de horas -->
            <div class="flex border-b border-ink-200 bg-ink-50/70">
                <div class="w-44 shrink-0 px-4 py-2 text-xs font-semibold text-ink-500">Horário deste computador</div>
                <div class="relative h-10 flex-1" data-test="time-axis">
                    <div v-for="tick in ticks" :key="tick.key" class="absolute inset-y-0 border-l border-ink-200 pl-1.5" :style="{ left: tick.left }" data-test="tick">
                        <span v-if="tick.day" class="block pt-1 text-[11px] font-semibold leading-none text-ink-900">{{ tick.day }}</span>
                        <span class="num absolute bottom-1 text-[11px] leading-none text-ink-500">{{ tick.hour }}</span>
                    </div>
                </div>
            </div>

            <template v-for="group in [{ key: 'vehicles', title: 'Veículos', rows: vehicleRows }, { key: 'drivers', title: 'Motoristas', rows: driverRows }]" :key="group.key">
                <h3 class="border-b border-ink-200 bg-white px-4 py-2 text-xs font-semibold text-ink-500">
                    {{ group.title }} <span class="num font-normal text-ink-400">{{ group.rows.length }}</span>
                </h3>

                <p v-if="group.rows.length === 0" class="border-b border-ink-100 px-4 py-4 text-sm text-ink-500" :data-test="`no-${group.key}`">
                    {{ group.key === 'vehicles' ? 'Nenhum veículo ativo.' : 'Nenhum motorista ativo.' }}
                </p>

                <div
                    v-for="row in group.rows"
                    :key="'vehicle' in row ? row.vehicle.id : row.driver.id"
                    class="flex border-b border-ink-100"
                    :data-test="'vehicle' in row ? 'vehicle-row' : 'driver-row'"
                >
                    <div class="flex w-44 shrink-0 items-center justify-between gap-2 px-4 py-2">
                        <MercosulPlate v-if="'vehicle' in row" :plate="row.vehicle.plate" size="sm" />
                        <span v-else class="truncate text-sm font-medium text-ink-900" :title="row.driver.name">{{ row.driver.name }}</span>

                        <button
                            v-if="row.schedulable"
                            type="button"
                            class="icon-btn !h-7 !w-7 shrink-0"
                            data-test="schedule-row"
                            :aria-label="'vehicle' in row ? `Agendar viagem com o veículo ${row.vehicle.plate}` : `Agendar viagem com ${row.driver.name}`"
                            @click="emit('schedule', 'vehicle' in row ? { vehicle_id: row.vehicle.id } : { driver_id: row.driver.id })"
                        >
                            <AppIcon name="plus" />
                        </button>
                    </div>

                    <div
                        class="relative h-12 flex-1"
                        :class="row.schedulable ? 'cursor-copy' : ''"
                        data-test="track"
                        @click="row.schedulable && pick($event, 'vehicle' in row ? { vehicle_id: row.vehicle.id } : { driver_id: row.driver.id })"
                    >
                        <span v-for="tick in ticks" :key="tick.key" class="absolute inset-y-0 border-l" :class="tick.day ? 'border-ink-200' : 'border-ink-100'" :style="{ left: tick.left }" />
                        <!-- O que já passou não pode mais ser agendado. -->
                        <span class="absolute inset-y-0 left-0 cursor-default bg-ink-50/80" :style="{ width: pastWidth }" data-test="past" @click.stop />

                        <span
                            v-for="block in row.blocks"
                            :key="block.key"
                            class="absolute top-2 flex h-8 cursor-default items-center overflow-hidden rounded-md px-2 text-xs font-medium"
                            :class="[block.tone, block.kind === 'trip' ? 'z-[2] shadow-card' : 'z-[1]']"
                            :style="{ left: block.left, width: block.width }"
                            :title="block.title"
                            :data-test="`block-${block.kind}`"
                            @click.stop
                        >
                            <span class="truncate">{{ block.label }}</span>
                        </span>

                        <span v-if="nowLeft" class="absolute inset-y-0 z-[3] w-px bg-brake-600" :style="{ left: nowLeft }" data-test="now" />
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
