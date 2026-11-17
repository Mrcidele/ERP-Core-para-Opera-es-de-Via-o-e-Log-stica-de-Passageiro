import { computed, reactive, ref } from 'vue';
import { openMaintenanceOrderSchema, type OpenMaintenanceOrderField } from '../schemas/maintenanceOrderSchemas';
import { useMaintenanceStore } from '../stores/maintenanceStore';
import type { MaintenanceOrder, MaintenanceType } from '../types/maintenance';

interface OpenOrderFormState {
    vehicle_id: string;
    mechanic_id: string;
    type: MaintenanceType | '';
    description: string;
    /** <input type="datetime-local">; vazio quando não há previsão. */
    estimated_completion_at: string;
}

type FieldErrors = Partial<Record<OpenMaintenanceOrderField, string>>;

export function useOpenMaintenanceOrderForm(onOpened: (order: MaintenanceOrder) => void) {
    const store = useMaintenanceStore();
    const form = reactive<OpenOrderFormState>({ vehicle_id: '', mechanic_id: '', type: '', description: '', estimated_completion_at: '' });
    const clientErrors = ref<FieldErrors>({});

    store.resetSaveState();
    void store.loadOptions();

    /** Erro exibido por campo: validação local primeiro, depois a resposta da API. */
    const errors = computed<FieldErrors>(() => {
        const merged = { ...clientErrors.value };

        for (const [field, messages] of Object.entries(store.validationErrors)) {
            const message = messages[0];

            if (message !== undefined && !(field in merged)) {
                merged[field as OpenMaintenanceOrderField] = message;
            }
        }

        return merged;
    });

    async function submit(): Promise<void> {
        const result = openMaintenanceOrderSchema.safeParse(form);

        if (!result.success) {
            const fieldErrors: FieldErrors = {};

            for (const issue of result.error.issues) {
                fieldErrors[issue.path[0] as OpenMaintenanceOrderField] ??= issue.message;
            }

            clientErrors.value = fieldErrors;

            return;
        }

        clientErrors.value = {};

        const order = await store.open(result.data);

        if (order) {
            onOpened(order);
        }
    }

    return { form, errors, submit, store };
}
