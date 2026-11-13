export const onlyDigits = (value: string): string => value.replace(/\D/g, '');

/** Aplica a máscara 000.000.000-00 conforme o usuário digita. */
export function maskCpf(value: string): string {
    const digits = onlyDigits(value).slice(0, 11);

    return digits
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1-$2');
}

/** Mesma regra de App\Presentation\Http\Rules\Cpf: 11 dígitos e dígitos verificadores corretos. */
export function isValidCpf(value: string): boolean {
    const cpf = onlyDigits(value);

    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
        return false;
    }

    return [9, 10].every((length) => {
        let sum = 0;

        for (let i = 0; i < length; i++) {
            sum += Number(cpf[i]) * (length + 1 - i);
        }

        return Number(cpf[length]) === ((sum * 10) % 11) % 10;
    });
}
