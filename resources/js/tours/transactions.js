export const TRANSACTIONS_TOUR_ID = 'transactions';

/**
 * Tour curto de Transações: lista + formulário (tipos, conta, cartão).
 * Dados/valores no form são só demonstração — submit bloqueado.
 */
export const transactionsSteps = [
    {
        id: 'tx-intro',
        route: 'transactions.index',
        query: { tour: TRANSACTIONS_TOUR_ID },
        attachTo: { element: '[data-tour="tx-page"]', on: 'bottom' },
        title: 'Transações',
        text: 'Todas as movimentações da família. Neste passeio usamos exemplos — nada será salvo.',
    },
    {
        id: 'tx-filters',
        route: 'transactions.index',
        query: { tour: TRANSACTIONS_TOUR_ID },
        attachTo: { element: '[data-tour="tx-filters"]', on: 'bottom' },
        title: 'Filtros',
        text: 'Filtre por mês, tipo (entrada, saída, investimento, fatura) e categoria.',
    },
    {
        id: 'tx-list',
        route: 'transactions.index',
        query: { tour: TRANSACTIONS_TOUR_ID },
        attachTo: { element: '[data-tour="tx-list"]', on: 'top' },
        title: 'Lista',
        text: 'Cada linha é um lançamento. A cor da borda indica o tipo — útil para achar rápido.',
    },
    {
        id: 'tx-add',
        route: 'transactions.index',
        query: { tour: TRANSACTIONS_TOUR_ID },
        attachTo: { element: '[data-tour="tx-add"]', on: 'bottom' },
        title: 'Adicionar',
        text: 'Vamos ao formulário para ver os tipos e como usar conta e cartão.',
    },
    {
        id: 'tx-types',
        route: 'transactions.create',
        query: { tour: TRANSACTIONS_TOUR_ID },
        attachTo: { element: '[data-tour="tx-types"]', on: 'bottom' },
        title: 'Tipos de lançamento',
        text: 'São quatro tipos. Saída (gasto), Entrada, Investimento e Pagamento de fatura. O formulário muda conforme o tipo.',
        onShow: ({ setFormType }) => setFormType('expense'),
    },
    {
        id: 'tx-expense',
        route: 'transactions.create',
        query: { tour: TRANSACTIONS_TOUR_ID },
        attachTo: { element: '[data-tour="tx-types"]', on: 'bottom' },
        title: 'Saída (gasto)',
        text: 'Compras do dia a dia. No crédito, entra na fatura pela data da compra. No PIX, dinheiro, débito ou débito automático, sai do caixa na hora.',
        onShow: ({ setFormType }) => setFormType('expense'),
    },
    {
        id: 'tx-payment',
        route: 'transactions.create',
        query: { tour: TRANSACTIONS_TOUR_ID },
        attachTo: { element: '[data-tour="tx-payment"]', on: 'bottom' },
        title: 'Pagamento e cartão',
        text: 'Escolha dinheiro, PIX, transferência, débito, débito automático ou um cartão cadastrado. No crédito, fechamento e vencimento definem a fatura.',
        onShow: ({ setFormType, setPaymentSelection }) => {
            setFormType('expense');
            setPaymentSelection('cash');
        },
    },
    {
        id: 'tx-bank',
        route: 'transactions.create',
        query: { tour: TRANSACTIONS_TOUR_ID },
        attachTo: { element: '[data-tour="tx-bank"]', on: 'bottom' },
        title: 'Conta bancária',
        text: 'Com PIX, transferência, débito ou débito automático, você pode indicar de qual conta saiu. Ajuda no controle e é opcional.',
        onShow: ({ setFormType, setPaymentSelection }) => {
            setFormType('expense');
            setPaymentSelection('pix');
        },
    },
    {
        id: 'tx-income',
        route: 'transactions.create',
        query: { tour: TRANSACTIONS_TOUR_ID },
        attachTo: { element: '[data-tour="tx-types"]', on: 'bottom' },
        title: 'Entrada',
        text: 'Salário, freelance, reembolso. Aumenta o saldo do mês. Dá para vincular a uma conta.',
        onShow: ({ setFormType }) => setFormType('income'),
    },
    {
        id: 'tx-investment',
        route: 'transactions.create',
        query: { tour: TRANSACTIONS_TOUR_ID },
        attachTo: { element: '[data-tour="tx-types"]', on: 'bottom' },
        title: 'Investimento',
        text: 'Aporte que sai do caixa, mas não conta como gasto de consumo. Entra no saldo do mês como saída e reduz o saldo de caixa.',
        onShow: ({ setFormType }) => setFormType('investment'),
    },
    {
        id: 'tx-transfer',
        route: 'transactions.create',
        query: { tour: TRANSACTIONS_TOUR_ID },
        attachTo: { element: '[data-tour="tx-types"]', on: 'bottom' },
        title: 'Pagamento de fatura',
        text: 'Quita ou paga parcialmente a fatura de um cartão de crédito. Escolha o cartão e a forma, como PIX, débito ou outro cartão.',
        onShow: ({ setFormType }) => setFormType('transfer'),
    },
    {
        id: 'tx-done',
        route: 'transactions.create',
        query: { tour: TRANSACTIONS_TOUR_ID },
        attachTo: { element: '[data-tour="tx-submit"]', on: 'top' },
        title: 'Salvar de verdade',
        text: 'No uso normal, este botão grava o lançamento. Neste tutorial ele fica bloqueado. Pode concluir sem medo.',
        onShow: ({ setFormType }) => setFormType('expense'),
    },
];

export default {
    id: TRANSACTIONS_TOUR_ID,
    label: 'Transações',
    persistOnboarding: false,
    useDemo: true,
    steps: transactionsSteps,
};
