export function formatDateTimeBR(value: string): string {
    return new Date(value).toLocaleString('pt-BR');
}
