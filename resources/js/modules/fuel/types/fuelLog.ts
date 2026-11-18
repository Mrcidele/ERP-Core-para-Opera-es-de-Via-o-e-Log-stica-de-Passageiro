// Espelha App\Presentation\Http\Resources\Fuel\FuelLogResource.

export interface FuelLog {
    id: string;
    odometer_km: number;
    /** Volume em mililitros (350500 = 350,5 L). */
    liters_milliliters: number;
    total_cost_cents: number;
    station_name: string;
    /** ISO 8601 em UTC. */
    fueled_at: string;
    /** Quilômetros desde o abastecimento anterior do mesmo veículo; null no primeiro. */
    distance_km: number | null;
    /** Rendimento do trecho; null no primeiro abastecimento ou quando o veículo não rodou. */
    km_per_liter: number | null;
    /** Rendimento abaixo da meta do veículo. */
    low_efficiency_alert: boolean;
    /** Meta de rendimento do veículo, em metros por litro (2200 = 2,2 km/L). */
    min_meters_per_liter: number;
    vehicle: { id: string; plate: string };
    recorded_by: { id: string; name: string };
}

