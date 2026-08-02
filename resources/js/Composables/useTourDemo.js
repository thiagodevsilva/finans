import { computed, reactive } from 'vue';

/**
 * Estado de demonstração do tour (somente no cliente — nunca persiste).
 */
const state = reactive({
    active: false,
    tourId: null,
    /** Tipo de formulário a forçar no Form de transações durante o tour */
    formType: null,
    /** payment_selection do form (ex.: pix, cash, card:uuid) */
    paymentSelection: null,
});

const today = () => new Date().toISOString().slice(0, 10);

export const demoDashboardData = {
    summary: {
        income: 5200,
        expense: 3100,
        expense_credit: 1800,
        expense_debit: 1300,
        balance: 8420.5,
        month_balance: 3400,
        investments: 500,
    },
    balanceMeta: {
        has_anchor: true,
        needs_initial: false,
        needs_monthly_checkin: false,
        as_of_date: today(),
        previous_month_balance: 7900,
    },
    recurringSummary: {
        paid_amount: 1400,
        pending_amount: 120,
        total_amount: 1520,
        paid_count: 1,
        pending_count: 1,
        total_count: 2,
        paid_percent: 92,
    },
    recentTransactions: [
        {
            id: 'demo-income',
            type: 'income',
            description: 'Salário (exemplo)',
            amount: 5200,
            date: today(),
            user: { name: 'Você' },
            category: { name: 'Salário', color: '#22c55e' },
            payment_method: 'pix',
            payment_card: null,
        },
        {
            id: 'demo-expense',
            type: 'expense',
            description: 'Mercado (exemplo)',
            amount: 320.9,
            date: today(),
            user: { name: 'Você' },
            category: { name: 'Alimentação', color: '#f59e0b' },
            payment_method: 'card',
            payment_card: { name: 'Nubank', last_four: '1234', type: 'credit' },
        },
        {
            id: 'demo-investment',
            type: 'investment',
            description: 'Aporte tesouro (exemplo)',
            amount: 500,
            date: today(),
            user: { name: 'Você' },
            category: { name: 'Investimento', color: '#0d9488' },
            payment_method: 'pix',
            payment_card: null,
        },
        {
            id: 'demo-transfer',
            type: 'transfer',
            description: 'Pagamento de fatura · Nubank (exemplo)',
            amount: 890.5,
            date: today(),
            user: { name: 'Você' },
            category: { name: 'Fatura cartão', color: '#64748b' },
            payment_method: 'pix',
            payment_card: null,
        },
    ],
};

export const demoTransactionsList = demoDashboardData.recentTransactions;

export function useTourDemo() {
    const isDemo = computed(() => state.active);
    const tourId = computed(() => state.tourId);
    const formType = computed(() => state.formType);
    const paymentSelection = computed(() => state.paymentSelection);

    function startDemo(id) {
        state.active = true;
        state.tourId = id;
        state.formType = null;
        state.paymentSelection = null;
    }

    function setFormType(type) {
        state.formType = type;
    }

    function setPaymentSelection(value) {
        state.paymentSelection = value;
    }

    function clearDemo() {
        state.active = false;
        state.tourId = null;
        state.formType = null;
        state.paymentSelection = null;
    }

    function isDemoTour(id) {
        return state.active && state.tourId === id;
    }

    return {
        isDemo,
        tourId,
        formType,
        paymentSelection,
        startDemo,
        setFormType,
        setPaymentSelection,
        clearDemo,
        isDemoTour,
        demoDashboardData,
        demoTransactionsList,
    };
}
