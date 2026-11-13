import { computed, reactive, ref, watch } from 'vue';
import { maskCpf, onlyDigits } from '@/core/utils/cpf';
import { createEmployeeSchema, type EmployeeField } from '../schemas/createEmployeeSchema';
import { useEmployeeStore } from '../stores/employeeStore';
import type { Employee, EmployeeRole, EmployeeStatus } from '../types/employee';

interface EmployeeFormState {
    name: string;
    cpf: string;
    role: EmployeeRole | '';
    status: EmployeeStatus;
    cnh_number: string;
    cnh_category: string;
    cnh_expiration_date: string;
}

type FieldErrors = Partial<Record<EmployeeField, string>>;

export function useEmployeeForm(onSaved: (saved: Employee) => void) {
    const store = useEmployeeStore();
    const form = reactive<EmployeeFormState>({
        name: '',
        cpf: '',
        role: '',
        status: 'active',
        cnh_number: '',
        cnh_category: '',
        cnh_expiration_date: '',
    });
    const clientErrors = ref<FieldErrors>({});

    store.resetSaveState();

    watch(
        () => form.cpf,
        (value) => {
            form.cpf = maskCpf(value);
        },
    );

    watch(
        () => form.cnh_number,
        (value) => {
            form.cnh_number = onlyDigits(value).slice(0, 11);
        },
    );

    // Os campos de CNH só aparecem para motorista; ao trocar de cargo, o que estava digitado não deve ser enviado.
    watch(
        () => form.role,
        (role) => {
            if (role !== 'driver') {
                form.cnh_number = '';
                form.cnh_category = '';
                form.cnh_expiration_date = '';
            }
        },
    );

    /** Erro exibido por campo: validação local primeiro, depois a resposta da API. */
    const errors = computed<FieldErrors>(() => {
        const merged = { ...clientErrors.value };

        for (const [field, messages] of Object.entries(store.validationErrors)) {
            const message = messages[0];

            if (message !== undefined && !(field in merged)) {
                merged[field as EmployeeField] = message;
            }
        }

        return merged;
    });

    async function submit(): Promise<void> {
        const result = createEmployeeSchema.safeParse(form);

        if (!result.success) {
            const fieldErrors: FieldErrors = {};

            for (const issue of result.error.issues) {
                fieldErrors[issue.path[0] as EmployeeField] ??= issue.message;
            }

            clientErrors.value = fieldErrors;

            return;
        }

        clientErrors.value = {};

        const saved = await store.create(result.data);

        if (saved) {
            onSaved(saved);
        }
    }

    return { form, errors, submit, store };
}
