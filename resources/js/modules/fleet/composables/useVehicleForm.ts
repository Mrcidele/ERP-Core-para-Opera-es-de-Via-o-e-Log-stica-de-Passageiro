import { computed, reactive, ref } from 'vue';
import { createVehicleSchema, updateVehicleSchema, type VehicleField } from '../schemas/createVehicleSchema';
import { useVehicleStore } from '../stores/vehicleStore';
import { DEFAULT_MIN_METERS_PER_LITER, type Vehicle, type VehicleStatus, type VehicleType } from '../types/vehicle';

interface VehicleFormState {
    plate: string;
    chassis: string;
    vehicle_type: VehicleType | '';
    model: string;
    payload_kg: number | null;
    status: VehicleStatus;
    current_mileage: number | null;
    next_preventive_maintenance_km: number | null | '';
    /** Meta própria em km/L; vazio segue o padrão do tipo. */
    min_km_per_liter: number | null | '';
}

type FieldErrors = Partial<Record<VehicleField, string>>;

const stateFrom = (vehicle: Vehicle | null): VehicleFormState => ({
    plate: vehicle?.plate ?? '',
    chassis: vehicle?.chassis ?? '',
    vehicle_type: vehicle?.vehicle_type ?? '',
    model: vehicle?.model ?? '',
    payload_kg: vehicle?.payload_kg ?? null,
    status: vehicle?.status ?? 'active',
    current_mileage: vehicle?.current_mileage ?? 0,
    next_preventive_maintenance_km: vehicle?.next_preventive_maintenance_km ?? null,
    min_km_per_liter: vehicle?.min_meters_per_liter == null ? null : vehicle.min_meters_per_liter / 1000,
});

/** A API responde pela meta em metros por litro; no formulário o campo é em km/L. */
const formField = (apiField: string): VehicleField => (apiField === 'min_meters_per_liter' ? 'min_km_per_liter' : (apiField as VehicleField));

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

            if (message !== undefined && !(formField(field) in merged)) {
                merged[formField(field)] = message;
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

    /** Padrão do tipo selecionado, mostrado quando o veículo não tem meta própria. */
    const typeDefaultMetersPerLiter = computed<number | null>(() =>
        form.vehicle_type === '' ? null : DEFAULT_MIN_METERS_PER_LITER[form.vehicle_type],
    );

    return { form, errors, submit, store, typeDefaultMetersPerLiter };
}
