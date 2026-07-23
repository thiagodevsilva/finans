export function formatBRL(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number(value || 0));
}

export function formatDate(value) {
    if (!value) return '';
    const date = typeof value === 'string' ? value.slice(0, 10) : value;
    const [y, m, d] = String(date).split('-');
    return `${d}/${m}/${y}`;
}

export const PAYMENT_METHODS = [
    { value: 'cash', label: 'Dinheiro' },
    { value: 'pix', label: 'PIX' },
    { value: 'transfer', label: 'Transferência' },
];

export function paymentLabel(tx) {
    if (!tx) return '';
    if (tx.payment_method === 'card' && tx.payment_card) {
        return `${tx.payment_card.name} •••• ${tx.payment_card.last_four}`;
    }
    const map = {
        cash: 'Dinheiro',
        pix: 'PIX',
        transfer: 'Transferência',
        card: 'Cartão',
    };
    return map[tx.payment_method] || tx.payment_method || '—';
}

export const MONTHS = [
    { value: 1, label: 'Janeiro' },
    { value: 2, label: 'Fevereiro' },
    { value: 3, label: 'Março' },
    { value: 4, label: 'Abril' },
    { value: 5, label: 'Maio' },
    { value: 6, label: 'Junho' },
    { value: 7, label: 'Julho' },
    { value: 8, label: 'Agosto' },
    { value: 9, label: 'Setembro' },
    { value: 10, label: 'Outubro' },
    { value: 11, label: 'Novembro' },
    { value: 12, label: 'Dezembro' },
];
