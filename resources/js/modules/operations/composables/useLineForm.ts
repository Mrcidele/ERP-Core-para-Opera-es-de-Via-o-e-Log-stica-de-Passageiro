import { computed, reactive, ref } from 'vue';
import { lineSchema, MAX_LINE_STOPS } from '../schemas/lineSchema';
import { useLineStore } from '../stores/lineStore';
import type { Line } from '../types/trip';

interface StopState {
    /** Identidade da rota do formulário, para a lista não se embaralhar ao reordenar. */
    key: number;
    city_name: string;
    cumulative_km: number | null | '';
    estimated_minutes: number | null | '';
}

interface LineFormState {
    origin: string;
    destination: string;
    distance_km: number | null | '';
    estimated_duration_minutes: number | null | '';
    stops: StopState[];
}

/** Erros por caminho de campo, no formato da API: `origin`, `stops`, `stops.0.cumulative_km`. */
type FieldErrors = Record<string, string>;

/** Formulário de rota: cadastro quando `line` é null, edição caso contrário. */
export function useLineForm(line: Line | null, onSaved: (saved: Line) => void) {
    const store = useLineStore();
    let nextKey = 0;

    const form = reactive<LineFormState>({
        origin: line?.origin ?? '',
        destination: line?.destination ?? '',
        distance_km: line?.distance_km ?? null,
        estimated_duration_minutes: line?.estimated_duration_minutes ?? null,
        stops: (line?.stops ?? []).map((stop) => ({
            key: nextKey++,
            city_name: stop.city_name,
            cumulative_km: stop.cumulative_km,
            estimated_minutes: stop.estimated_minutes,
        })),
    });

    const clientErrors = ref<FieldErrors>({});

    store.resetSaveState();

    /** Validação local primeiro, depois a resposta da API. */
    const errors = computed<FieldErrors>(() => {
        const merged = { ...clientErrors.value };

        for (const [field, messages] of Object.entries(store.validationErrors)) {
            const message = messages[0];

            if (message !== undefined && !(field in merged)) {
                merged[field] = message;
            }
        }

        return merged;
    });

    const canAddStop = computed(() => form.stops.length < MAX_LINE_STOPS);

    // Mexer na lista muda a posição das paradas: os erros por posição deixam de valer.
    function clearStopErrors(): void {
        clientErrors.value = Object.fromEntries(Object.entries(clientErrors.value).filter(([path]) => !path.startsWith('stops')));
        store.validationErrors = Object.fromEntries(Object.entries(store.validationErrors).filter(([path]) => !path.startsWith('stops')));
    }

    function addStop(): void {
        if (canAddStop.value) {
            form.stops.push({ key: nextKey++, city_name: '', cumulative_km: null, estimated_minutes: null });
        }
    }

    function removeStop(index: number): void {
        form.stops.splice(index, 1);
        clearStopErrors();
    }

    /** Troca a parada de lugar com a vizinha de cima (-1) ou de baixo (+1). */
    function moveStop(index: number, direction: -1 | 1): void {
        const target = index + direction;
        const moved = form.stops[index];
        const neighbor = form.stops[target];

        if (moved === undefined || neighbor === undefined) {
            return;
        }

        form.stops[index] = neighbor;
        form.stops[target] = moved;
        clearStopErrors();
    }

    async function submit(): Promise<void> {
        // Campo numérico vazio chega como '' do v-model.number: para o schema, é "não informado".
        const blank = (value: number | null | ''): number | undefined => (value === '' || value === null ? undefined : value);

        const result = lineSchema.safeParse({
            origin: form.origin,
            destination: form.destination,
            distance_km: blank(form.distance_km),
            estimated_duration_minutes: blank(form.estimated_duration_minutes),
            stops: form.stops.map((stop) => ({
                city_name: stop.city_name,
                cumulative_km: blank(stop.cumulative_km),
                estimated_minutes: blank(stop.estimated_minutes),
            })),
        });

        if (!result.success) {
            const fieldErrors: FieldErrors = {};

            for (const issue of result.error.issues) {
                fieldErrors[issue.path.join('.')] ??= issue.message;
            }

            clientErrors.value = fieldErrors;

            return;
        }

        clientErrors.value = {};

        const saved = line ? await store.update(line.id, result.data) : await store.create(result.data);

        if (saved) {
            onSaved(saved);
        }
    }

    return { form, errors, canAddStop, addStop, removeStop, moveStop, submit, store };
}
