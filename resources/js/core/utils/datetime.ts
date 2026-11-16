const pad = (value: number): string => String(value).padStart(2, '0');

/** Data local no formato de <input type="date">: AAAA-MM-DD. */
export const toDateInput = (date: Date): string => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

/** Data e hora locais no formato de <input type="datetime-local">: AAAA-MM-DDTHH:mm. */
export const toDateTimeInput = (date: Date): string => `${toDateInput(date)}T${pad(date.getHours())}:${pad(date.getMinutes())}`;

/** Meia-noite local do dia de um <input type="date">. */
export function fromDateInput(value: string): Date {
    const [year = 1970, month = 1, day = 1] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}
