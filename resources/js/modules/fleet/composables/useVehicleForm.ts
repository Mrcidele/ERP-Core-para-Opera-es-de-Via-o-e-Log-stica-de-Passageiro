import { computed, reactive, ref } from 'vue';
import { createVehicleSchema, type CreateVehicleField } from '../schemas/createVehicleSchema';
import { useVehicleStore } from '../stores/vehicleStore';
import type { VehicleClass } from '../types/vehicle';

interface VehicleFormState {
    plate: string;
    chassis: string;
    capacity: number | null;
    class_type: VehicleClass | '';
    current_mileage: number | null;
}

const initialState = (): VehicleFormState => ({
    plate: '',
    chassis: '',
    capacity: null,
    class_type: '',
    current_mileage: 0,
});

export function useVehicleForm() {
    const store = useVehicleStore();
    const form = reactive<VehicleFormState>(initialState());
    const clientErrors = ref<Partial<Record<CreateVehicleField, string>>>({});

    /** Erro exibido por campo: validação local primeiro, depois a resposta da API. */
    const errors = computed<Partial<Record<CreateVehicleField, string>>>(() => {
        const merged = { ...clientErrors.value };

        for (const [field, messages] of Object.entries(store.validationErrors)) {
            const message = messages[0];

            if (message !== undefined && !(field in merged)) {
                merged[field as CreateVehicleField] = message;
            }
        }

        return merged;
    });

    async function submit(): Promise<void> {
        const result = createVehicleSchema.safeParse(form);

        if (!result.success) {
            const fieldErrors: Partial<Record<CreateVehicleField, string>> = {};

            for (const issue of result.error.issues) {
                const field = issue.path[0] as CreateVehicleField;
                fieldErrors[field] ??= issue.message;
            }

            clientErrors.value = fieldErrors;

            return;
        }

        clientErrors.value = {};

        if (await store.create(result.data)) {
            Object.assign(form, initialState());
        }
    }

    return { form, errors, submit, store };
}
