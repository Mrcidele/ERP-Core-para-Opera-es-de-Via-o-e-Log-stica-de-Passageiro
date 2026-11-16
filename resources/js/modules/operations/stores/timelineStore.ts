import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { http } from '@/core/api/http';
import type { RequestStatus } from '@/core/api/pagination';
import { fromDateInput, toDateInput } from '@/core/utils/datetime';
import type { ScheduleTimeline } from '../types/timeline';

export const useTimelineStore = defineStore('operations/timeline', () => {
    /** Dia escolhido, no calendário deste computador (AAAA-MM-DD). */
    const date = ref(toDateInput(new Date()));
    const timeline = ref<ScheduleTimeline | null>(null);
    const status = ref<RequestStatus>('idle');
    const refreshing = ref(false);

    /** A grade começa à meia-noite local do dia escolhido. */
    const start = computed(() => fromDateInput(date.value));

    /**
     * A API devolve 48 horas a partir da meia-noite UTC da data pedida. Pedindo a data UTC em que cai a
     * meia-noite local, a janela sempre cobre o dia escolhido inteiro e boa parte do seguinte.
     */
    const requestDate = computed(() => start.value.toISOString().slice(0, 10));

    let latest = 0;

    async function fetch(): Promise<void> {
        const ticket = ++latest;

        if (status.value === 'success') {
            refreshing.value = true;
        } else {
            status.value = 'loading';
        }

        try {
            const response = await http.get<{ data: ScheduleTimeline }>('/operations/schedule-timeline', { params: { date: requestDate.value } });

            if (ticket === latest) {
                timeline.value = response.data.data;
                status.value = 'success';
            }
        } catch {
            if (ticket === latest) {
                status.value = 'error';
            }
        } finally {
            if (ticket === latest) {
                refreshing.value = false;
            }
        }
    }

    function showDate(value: string): Promise<void> {
        if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            date.value = value;
        }

        return fetch();
    }

    function shiftDays(days: number): Promise<void> {
        const next = fromDateInput(date.value);
        next.setDate(next.getDate() + days);

        return showDate(toDateInput(next));
    }

    const showToday = (): Promise<void> => showDate(toDateInput(new Date()));

    return { date, timeline, status, refreshing, start, requestDate, fetch, showDate, shiftDays, showToday };
});
