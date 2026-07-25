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

const PAYMENT_METHOD_LABELS = {
    cash: 'Dinheiro',
    pix: 'PIX',
    transfer: 'Transferência',
    card: 'Cartão',
};

export function paymentLabel(tx) {
    if (!tx) return '';
    if (tx.type === 'transfer') {
        const card = tx.payment_card?.name ? ` · ${tx.payment_card.name}` : '';
        const method = PAYMENT_METHOD_LABELS[tx.payment_method];
        const via = method ? ` via ${method}` : '';
        const bank = tx.bank_account?.name ? ` · ${tx.bank_account.name}` : '';
        return `Pagamento de fatura${card}${via}${bank}`;
    }
    if (tx.type === 'income') {
        return tx.bank_account?.name || 'Sem conta';
    }
    if (tx.type === 'investment') {
        const method = PAYMENT_METHOD_LABELS[tx.payment_method] || 'Aporte';
        const bank = tx.bank_account?.name ? ` · ${tx.bank_account.name}` : '';
        return `${method}${bank}`;
    }
    if (tx.payment_method === 'card' && tx.payment_card) {
        return formatCardLabel(tx.payment_card);
    }
    if ((tx.payment_method === 'pix' || tx.payment_method === 'transfer') && tx.bank_account?.name) {
        return `${PAYMENT_METHOD_LABELS[tx.payment_method]} · ${tx.bank_account.name}`;
    }
    return PAYMENT_METHOD_LABELS[tx.payment_method] || tx.payment_method || '—';
}

export function formatCardLabel(card) {
    if (!card) return '';
    const typeLabel = card.type === 'debit' ? 'Débito' : 'Crédito';
    const parts = [card.name, typeLabel];
    if (card.last_four) {
        parts.push(`•••• ${card.last_four}`);
    }
    return parts.join(' · ');
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
