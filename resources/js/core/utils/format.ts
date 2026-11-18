const integer = new Intl.NumberFormat('pt-BR');
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const dateTime = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

export const formatInteger = (value: number): string => integer.format(value);

export const formatKm = (value: number): string => `${integer.format(value)} km`;

/** 390 → "6h 30min"; 120 → "2h"; 45 → "45min". */
export function formatDuration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) {
        return `${rest}min`;
    }

    return rest === 0 ? `${hours}h` : `${hours}h ${rest}min`;
}

/** Data e hora no fuso do navegador, a partir de um ISO 8601. */
export const formatDateTime = (iso: string): string => dateTime.format(new Date(iso));

/** 45090 → "R$ 450,90". */
export const formatCents = (cents: number): string => currency.format(cents / 100);

/** countLabel(1, 'viagem', 'viagens') → "1 viagem"; countLabel(3, …) → "3 viagens". */
export const countLabel = (count: number, singular: string, plural: string): string =>
    `${integer.format(count)} ${count === 1 ? singular : plural}`;

/** "2026-11-20" → "20/11/2026", sem conversão de fuso (é uma data, não um instante). */
export function formatDate(isoDate: string): string {
    const [year, month, day] = isoDate.split('-');

    return `${day}/${month}/${year}`;
}

/** Percentual com uma casa decimal: 66.7 → "66,7%". */
export const formatPercent = (value: number): string =>
    `${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 }).format(value)}%`;

const liters = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 3 });
const efficiency = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** 350500 → "350,5 L" (o volume trafega em mililitros). */
export const formatLiters = (milliliters: number): string => `${liters.format(milliliters / 1000)} L`;

/** 2.85 → "2,85 km/L". */
export const formatKmPerLiter = (value: number): string => `${efficiency.format(value)} km/L`;

/** Meta de rendimento: 2200 m/L → "2,2 km/L" (o rendimento trafega em metros por litro). */
export const formatMetersPerLiter = (metersPerLiter: number): string =>
    `${new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 2 }).format(metersPerLiter / 1000)} km/L`;

/** 18000 → "18.000 kg". */
export const formatKg = (kilograms: number): string => `${new Intl.NumberFormat('pt-BR').format(kilograms)} kg`;
