import { computed, reactive, ref } from 'vue';
import { scheduleTripSchema, type TripField } from '../schemas/scheduleTripSchema';
import { useTripStore } from '../stores/tripStore';
import type { TripPrefill } from '../types/timeline';
import type { Trip } from '../types/trip';

interface TripFormState {
    line_id: string;
    vehicle_id: string;
    driver_id: string;
    departure_time: string;
    arrival_time: string;
}

type FieldErrors = Partial<Record<TripField, string>>;

/** `initial` vem da grade de escala: veículo ou motorista da linha clicada e o horário escolhido. */
export function useTripForm(onScheduled: (trip: Trip) => void, initial: TripPrefill = {}) {
    const store = useTripStore();
    const form = reactive<TripFormState>({
        line_id: '',
        vehicle_id: initial.vehicle_id ?? '',
        driver_id: initial.driver_id ?? '',
        departure_time: initial.departure_time ?? '',
        arrival_time: '',
    });
    const clientErrors = ref<FieldErrors>({});

    store.resetSaveState();
    void store.loadOptions();

    const selectedLine = computed(() => store.lines.find((line) => line.id === form.line_id) ?? null);

    /** Erro exibido por campo: validação local primeiro, depois a resposta da API. */
    const errors = computed<FieldErrors>(() => {
        const merged = { ...clientErrors.value };

        for (const [field, messages] of Object.entries(store.validationErrors)) {
            const message = messages[0];

            if (message !== undefined && !(field in merged)) {
                merged[field as TripField] = message;
            }
        }

        return merged;
    });

    async function submit(): Promise<void> {
        const result = scheduleTripSchema.safeParse(form);

        if (!result.success) {
            const fieldErrors: FieldErrors = {};

            for (const issue of result.error.issues) {
                fieldErrors[issue.path[0] as TripField] ??= issue.message;
            }

            clientErrors.value = fieldErrors;

            return;
        }

        clientErrors.value = {};

        const trip = await store.schedule(result.data);

        if (trip) {
            onScheduled(trip);
        }
    }

    return { form, errors, selectedLine, submit, store };
}
