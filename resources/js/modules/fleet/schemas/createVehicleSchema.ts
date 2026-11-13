import { z } from 'zod';
import { VEHICLE_CLASSES, VEHICLE_STATUSES } from '../types/vehicle';

// Mesmas regras de VehicleRequest, CreateVehicleRequest e UpdateVehicleRequest do backend.
const normalize = (value: string): string => value.replace(/[\s-]/g, '').toUpperCase();

export const createVehicleSchema = z.object({
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
    capacity: z
        .number('Informe a capacidade.')
        .int('A capacidade deve ser um número inteiro.')
        .min(1, 'A capacidade mínima é 1.')
        .max(100, 'A capacidade máxima é 100.'),
    class_type: z.enum(VEHICLE_CLASSES, 'Selecione a classe do veículo.'),
    current_mileage: z
        .number('Informe a quilometragem.')
        .int('A quilometragem deve ser um número inteiro.')
        .min(0, 'A quilometragem não pode ser negativa.'),
});

export const updateVehicleSchema = createVehicleSchema.extend({
    status: z.enum(VEHICLE_STATUSES, 'Selecione o status do veículo.'),
});

export type CreateVehiclePayload = z.output<typeof createVehicleSchema>;
export type UpdateVehiclePayload = z.output<typeof updateVehicleSchema>;
export type VehicleField = keyof UpdateVehiclePayload;
