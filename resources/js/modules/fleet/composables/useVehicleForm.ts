import { computed, reactive, ref } from 'vue';
import { createVehicleSchema, updateVehicleSchema, type VehicleField } from '../schemas/createVehicleSchema';
import { useVehicleStore } from '../stores/vehicleStore';
import type { Vehicle, VehicleClass, VehicleStatus } from '../types/vehicle';

interface VehicleFormState {
    plate: string;
    chassis: string;
    capacity: number | null;
    class_type: VehicleClass | '';
    status: VehicleStatus;
    current_mileage: number | null;
    next_preventive_maintenance_km: number | null | '';
}

type FieldErrors = Partial<Record<VehicleField, string>>;

const stateFrom = (vehicle: Vehicle | null): VehicleFormState => ({
    plate: vehicle?.plate ?? '',
    chassis: vehicle?.chassis ?? '',
    capacity: vehicle?.capacity ?? null,
    class_type: vehicle?.class_type ?? '',
    status: vehicle?.status ?? 'active',
    current_mileage: vehicle?.current_mileage ?? 0,
    next_preventive_maintenance_km: vehicle?.next_preventive_maintenance_km ?? null,
});

/** Formulário de veículo: cadastro quando `vehicle` é null, edição caso contrário. */
export function useVehicleForm(vehicle: Vehicle | null, onSaved: (saved: Vehicle) => void) {
    const store = useVehicleStore();
    const form = reactive<VehicleFormState>(stateFrom(vehicle));
    const clientErrors = ref<FieldErrors>({});

    store.resetSaveState();

    /** Erro exibido por campo: validação local primeiro, depois a resposta da API. */
    const errors = computed<FieldErrors>(() => {
        const merged = { ...clientErrors.value };

        for (const [field, messages] of Object.entries(store.validationErrors)) {
            const message = messages[0];

            if (message !== undefined && !(field in merged)) {
                merged[field as VehicleField] = message;
            }
        }

        return merged;
    });

    function collect(issues: { path: PropertyKey[]; message: string }[]): void {
        const fieldErrors: FieldErrors = {};

        for (const issue of issues) {
            fieldErrors[issue.path[0] as VehicleField] ??= issue.message;
        }

        clientErrors.value = fieldErrors;
    }

    async function submit(): Promise<void> {
        let saved: Vehicle | null;

        if (vehicle) {
            const result = updateVehicleSchema.safeParse(form);

            if (!result.success) {
                return collect(result.error.issues);
            }

            clientErrors.value = {};
            saved = await store.update(vehicle.id, result.data);
        } else {
            const result = createVehicleSchema.safeParse(form);

            if (!result.success) {
                return collect(result.error.issues);
            }

            clientErrors.value = {};
            saved = await store.create(result.data);
        }

        if (saved) {
            onSaved(saved);
        }
    }

    return { form, errors, submit, store };
}
