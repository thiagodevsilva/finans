export const PAYMENT_CARDS_TOUR_ID = 'payment-cards';

/**
 * Tour curto só da tela Cartões (Ajuda nesta tela).
 * O tutorial completo Contas+Cartões é o first-setup (Refazer tutorial).
 */
export const paymentCardsSteps = [
    {
        id: 'pc-intro',
        route: 'payment-cards.index',
        query: { tour: PAYMENT_CARDS_TOUR_ID },
        attachTo: { element: '[data-tour="pc-form"]', on: 'bottom' },
        title: 'Cartões',
        text: 'Aqui você cadastra crédito, débito e benefício (VR/VA). O tutorial não exige salvar nada. É só para você ver onde fica.',
    },
    {
        id: 'pc-name',
        route: 'payment-cards.index',
        query: { tour: PAYMENT_CARDS_TOUR_ID },
        attachTo: { element: '[data-tour="pc-name"]', on: 'bottom' },
        title: 'Nome do cartão',
        text: 'Por exemplo Nubank Roxinho. Preencha só se quiser cadastrar agora.',
    },
    {
        id: 'pc-bank',
        route: 'payment-cards.index',
        query: { tour: PAYMENT_CARDS_TOUR_ID },
        attachTo: { element: '[data-tour="pc-bank-account"]', on: 'bottom' },
        title: 'Vincular à conta',
        text: 'Depois de ter uma conta, você liga o cartão a ela. Assim fica claro de onde sai o pagamento da fatura.',
    },
    {
        id: 'pc-credit-days',
        route: 'payment-cards.index',
        query: { tour: PAYMENT_CARDS_TOUR_ID },
        attachTo: { element: '[data-tour="pc-credit-days"]', on: 'bottom' },
        title: 'Fechamento e vencimento',
        text: 'Só no crédito. Esses dias definem em qual fatura cada compra entra.',
    },
    {
        id: 'pc-done',
        route: 'payment-cards.index',
        query: { tour: PAYMENT_CARDS_TOUR_ID },
        attachTo: { element: '[data-tour="pc-submit"]', on: 'top' },
        title: 'Pronto',
        text: 'Este botão salva o cartão. No passeio, pode concluir sem cadastrar. Pronto, você já conhece esta tela.',
    },
];

export default {
    id: PAYMENT_CARDS_TOUR_ID,
    label: 'Cartões',
    persistOnboarding: false,
    useDemo: false,
    steps: paymentCardsSteps,
};
