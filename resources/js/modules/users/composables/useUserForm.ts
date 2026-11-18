import { computed, reactive, ref } from 'vue';
import type { UserRole } from '@/modules/auth/types/auth';
import { createUserSchema, updateUserSchema, type UserField } from '../schemas/userSchema';
import { useUserStore } from '../stores/userStore';
import type { ManagedUser } from '../types/user';

interface UserFormState {
    name: string;
    email: string;
    role: UserRole | '';
    password: string;
    password_confirmation: string;
}

type FieldErrors = Partial<Record<UserField, string>>;

/** Formulário de usuário: criação quando `user` é null, edição (senha opcional) caso contrário. */
export function useUserForm(user: ManagedUser | null, onSaved: (saved: ManagedUser) => void) {
    const store = useUserStore();
    const form = reactive<UserFormState>({
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user?.role ?? '',
        password: '',
        password_confirmation: '',
    });
    const clientErrors = ref<FieldErrors>({});

    store.resetSaveState();

    /** Validação local primeiro; depois a resposta 422 da API, campo a campo. */
    const errors = computed<FieldErrors>(() => {
        const merged = { ...clientErrors.value };

        for (const [field, messages] of Object.entries(store.validationErrors)) {
            const message = messages[0];

            if (message !== undefined && !(field in merged)) {
                merged[field as UserField] = message;
            }
        }

        return merged;
    });

    function collect(issues: { path: PropertyKey[]; message: string }[]): void {
        const fieldErrors: FieldErrors = {};

        for (const issue of issues) {
            fieldErrors[issue.path[0] as UserField] ??= issue.message;
        }

        clientErrors.value = fieldErrors;
    }

    async function submit(): Promise<void> {
        let saved: ManagedUser | null;

        if (user) {
            const result = updateUserSchema.safeParse(form);

            if (!result.success) {
                return collect(result.error.issues);
            }

            clientErrors.value = {};
            saved = await store.update(user.id, result.data);
        } else {
            const result = createUserSchema.safeParse(form);

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
