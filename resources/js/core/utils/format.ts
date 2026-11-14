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
