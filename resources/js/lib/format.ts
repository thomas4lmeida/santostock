export function formatDateTimeBR(value: string): string {
    return new Date(value).toLocaleString('pt-BR');
}

export function formatBRL(cents: number): string {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(cents / 100);
}
