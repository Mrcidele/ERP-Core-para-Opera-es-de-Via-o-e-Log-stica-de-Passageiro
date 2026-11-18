import { z } from 'zod';
import { VEHICLE_STATUSES, VEHICLE_TYPES } from '../types/vehicle';

// Mesmas regras de VehicleRequest, CreateVehicleRequest e UpdateVehicleRequest do backend.
const normalize = (value: string): string => value.replace(/[\s-]/g, '').toUpperCase();

const emptyToNull = (value: unknown): unknown => (value === '' || value === undefined ? null : value);

const vehicleFields = z.object({
    plate: z
        .string()
        .transform(normalize)
        .pipe(z.string().regex(/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/, 'A placa deve estar no padrão ABC1234 ou ABC1D23.')),
    chassis: z
        .string()
        .transform(normalize)
        .pipe(
            z
                .string()
                .regex(/^[A-HJ-NPR-Z0-9]{17}$/, 'O chassi deve ter 17 caracteres alfanuméricos, sem as letras I, O e Q.'),
        ),
    vehicle_type: z.enum(VEHICLE_TYPES, 'Selecione o tipo de veículo.'),
    model: z.preprocess(
        (value) => (typeof value === 'string' && value.trim() === '' ? null : typeof value === 'string' ? value.trim() : value),
        z.string().max(60, 'O modelo deve ter até 60 caracteres.').nullable(),
    ),
    payload_kg: z
        .number('Informe a carga útil.')
        .int('A carga útil deve ser um número inteiro de quilos.')
        .min(1, 'A carga útil mínima é 1 kg.')
        .max(60_000, 'A carga útil máxima é 60.000 kg.'),
    current_mileage: z
        .number('Informe a quilometragem.')
        .int('A quilometragem deve ser um número inteiro.')
        .min(0, 'A quilometragem não pode ser negativa.'),
    // Opcional: campo vazio significa revisão não programada.
    next_preventive_maintenance_km: z.preprocess(
        (value) => (value === '' || value === undefined ? null : value),
        z
            .number('Informe a quilometragem da próxima revisão.')
            .int('A quilometragem deve ser um número inteiro.')
            .min(0, 'A quilometragem não pode ser negativa.')
            .max(2_000_000_000, 'A quilometragem é alta demais.')
            .nullable(),
    ),
    // Na tela a meta é digitada em km/L; a API recebe metros por litro (2,2 km/L = 2200). Vazio segue o padrão do tipo.
    min_km_per_liter: z.preprocess(
        emptyToNull,
        z
            .number('Informe a meta em km/L.')
            .min(0.5, 'A meta mínima é 0,5 km/L.')
            .max(30, 'A meta máxima é 30 km/L.')
            .nullable(),
    ),
});

/** Troca a meta digitada em km/L pelo campo da API, em metros por litro. */
function toApiPayload<T extends { min_km_per_liter: number | null }>({ min_km_per_liter, ...rest }: T) {
    return { ...rest, min_meters_per_liter: min_km_per_liter === null ? null : Math.round(min_km_per_liter * 1000) };
}

export const createVehicleSchema = vehicleFields.transform(toApiPayload);

export const updateVehicleSchema = vehicleFields
    .extend({ status: z.enum(VEHICLE_STATUSES, 'Selecione o status do veículo.') })
    .transform(toApiPayload);

export type CreateVehiclePayload = z.output<typeof createVehicleSchema>;
export type UpdateVehiclePayload = z.output<typeof updateVehicleSchema>;
/** Campos do formulário (a meta aparece em km/L). */
export type VehicleField = keyof z.input<typeof vehicleFields> | 'status';
