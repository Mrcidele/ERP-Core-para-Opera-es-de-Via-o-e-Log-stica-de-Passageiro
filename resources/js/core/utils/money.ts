/** Valor em reais como se digita no Brasil: "450", "450,9", "1.234,56". */
export const REAIS = /^(\d{1,3}(\.\d{3})+|\d+)(,\d{1,2})?$/;

/** Converte "1.234,56" em 123456 centavos, sem passar por ponto flutuante. */
export function reaisToCents(value: string): number {
    const [integer = '0', decimals = ''] = value.trim().replaceAll('.', '').split(',');

    return Number(integer) * 100 + Number(decimals.padEnd(2, '0'));
}

/** Volume em litros como se digita no Brasil, com até três casas: "350", "350,5", "1.250,125". */
export const LITERS = /^(\d{1,3}(\.\d{3})+|\d+)(,\d{1,3})?$/;

/** Converte "350,5" em 350500 mililitros, sem passar por ponto flutuante. */
export function litersToMilliliters(value: string): number {
    const [integer = '0', decimals = ''] = value.trim().replaceAll('.', '').split(',');

    return Number(integer) * 1000 + Number(decimals.padEnd(3, '0'));
}
