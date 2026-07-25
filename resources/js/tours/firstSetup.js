export const FIRST_SETUP_TOUR_ID = 'first-setup';

/**
 * Steps declarativos do tour first-setup.
 * Espelho de docs/onboarding.md — manter sincronizado.
 *
 * Tom: passeio explicativo. Cadastro é opcional durante o guia.
 */
export const firstSetupSteps = [
    {
        id: 'dash-nav-contas',
        route: 'dashboard',
        attachTo: { element: '[data-tour="nav-bank-accounts"]', on: 'right' },
        title: 'Comece pelas contas',
        text: 'Contas bancárias são o ponto de partida: entradas, PIX e vínculo com cartões. Vamos só conhecer as telas — cadastrar é opcional.',
    },
    {
        id: 'ba-intro',
        route: 'bank-accounts.index',
        attachTo: { element: '[data-tour="ba-page"]', on: 'bottom' },
        title: 'Suas contas',
        text: 'Aqui ficam as contas da família (ex.: Nubank Conta). Não precisa preencher agora: pode só seguir o guia e criar depois.',
    },
    {
        id: 'ba-name',
        route: 'bank-accounts.index',
        attachTo: { element: '[data-tour="ba-name"]', on: 'bottom' },
        title: 'Nome da conta',
        text: 'Quando for cadastrar, use um nome fácil de reconhecer. No guia, pode avançar sem digitar.',
    },
    {
        id: 'ba-color',
        route: 'bank-accounts.index',
        attachTo: { element: '[data-tour="ba-color"]', on: 'bottom' },
        title: 'Cor',
        text: 'A cor ajuda a achar a conta nos lançamentos. Também é só para quando você for criar.',
    },
    {
        id: 'ba-submit',
        route: 'bank-accounts.index',
        attachTo: { element: '[data-tour="ba-submit"]', on: 'top' },
        title: 'Adicionar',
        text: 'Este botão salva a conta. No passeio, pode seguir sem cadastrar — o próximo passo mostra os cartões.',
    },
    {
        id: 'pc-intro',
        route: 'payment-cards.index',
        query: { tour: FIRST_SETUP_TOUR_ID },
        attachTo: { element: '[data-tour="pc-form"]', on: 'bottom' },
        title: 'Cartões',
        text: 'Mesma ideia: aqui você cadastra crédito e débito. O guia não exige salvar nada — é só para você ver onde fica.',
    },
    {
        id: 'pc-name',
        route: 'payment-cards.index',
        query: { tour: FIRST_SETUP_TOUR_ID },
        attachTo: { element: '[data-tour="pc-name"]', on: 'bottom' },
        title: 'Nome do cartão',
        text: 'Ex.: Nubank Roxinho. Preencha só se quiser cadastrar agora.',
    },
    {
        id: 'pc-bank',
        route: 'payment-cards.index',
        query: { tour: FIRST_SETUP_TOUR_ID },
        attachTo: { element: '[data-tour="pc-bank-account"]', on: 'bottom' },
        title: 'Vincular à conta',
        text: 'Depois de ter uma conta, você liga o cartão a ela. Assim fica claro de onde sai o pagamento da fatura.',
    },
    {
        id: 'pc-credit-days',
        route: 'payment-cards.index',
        query: { tour: FIRST_SETUP_TOUR_ID },
        attachTo: { element: '[data-tour="pc-credit-days"]', on: 'bottom' },
        title: 'Fechamento e vencimento',
        text: 'Só no crédito: definem em qual fatura cada compra entra.',
    },
    {
        id: 'pc-done',
        route: 'payment-cards.index',
        query: { tour: FIRST_SETUP_TOUR_ID },
        attachTo: { element: '[data-tour="pc-submit"]', on: 'top' },
        title: 'Pronto',
        text: 'Base pronta. Depois você pode ver o guia do Dashboard ou de Transações pelo botão Ajuda em cada tela.',
    },
];

export default {
    id: FIRST_SETUP_TOUR_ID,
    label: 'Contas e cartões',
    persistOnboarding: true,
    useDemo: false,
    steps: firstSetupSteps,
};
