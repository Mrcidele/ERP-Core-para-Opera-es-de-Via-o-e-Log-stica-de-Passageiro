const integer = new Intl.NumberFormat('pt-BR');

export const formatInteger = (value: number): string => integer.format(value);

export const formatKm = (value: number): string => `${integer.format(value)} km`;
