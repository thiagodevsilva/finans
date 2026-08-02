export const FIRST_SETUP_TOUR_ID = 'first-setup';

/**
 * Steps declarativos do tour first-setup.
 * Espelho de docs/onboarding.md — manter sincronizado.
 *
 * Tom: passeio explicativo. Cadastro é opcional durante o tutorial.
 * Evitar : e ; na copy voltada ao usuário.
 */
export const firstSetupSteps = [
    {
        id: 'fs-welcome',
        route: 'dashboard',
        title: 'Bem-vindo ao Levita',
        text: 'Seja bem-vindo ao Levita, um app simples pra você que quer ter controle total! Aqui contas e cartões são representações. Não integramos com o banco. Você nos passa a realidade. Vamos conhecer as telas. Cadastrar é opcional.',
    },
    {
        id: 'dash-nav-contas',
        route: 'dashboard',
        attachTo: { element: '[data-tour="nav-bank-accounts"]', on: 'bottom' },
        openSidebar: true,
        title: 'Comece pelas contas',
        text: 'Contas bancárias são o ponto de partida para entradas, PIX e vínculo com cartões. Vamos só conhecer as telas. Cadastrar é opcional.',
    },
    {
        id: 'ba-intro',
        route: 'bank-accounts.index',
        attachTo: { element: '[data-tour="ba-page"]', on: 'bottom' },
        title: 'Suas contas',
        text: 'Aqui ficam as contas da família, por exemplo Nubank Conta. Não precisa preencher agora. Pode só seguir o tutorial e criar depois.',
    },
    {
        id: 'ba-name',
        route: 'bank-accounts.index',
        attachTo: { element: '[data-tour="ba-name"]', on: 'bottom' },
        title: 'Nome da conta',
        text: 'Quando for cadastrar, use um nome fácil de reconhecer. No tutorial, pode avançar sem digitar.',
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
        text: 'Este botão salva a conta. No passeio, pode seguir sem cadastrar. O próximo passo mostra os cartões.',
    },
    {
        id: 'pc-intro',
        route: 'payment-cards.index',
        query: { tour: FIRST_SETUP_TOUR_ID },
        attachTo: { element: '[data-tour="pc-form"]', on: 'bottom' },
        title: 'Cartões',
        text: 'Mesma ideia. Aqui você cadastra crédito, débito e benefício (VR/VA). O tutorial não exige salvar nada. É só para você ver onde fica.',
    },
    {
        id: 'pc-name',
        route: 'payment-cards.index',
        query: { tour: FIRST_SETUP_TOUR_ID },
        attachTo: { element: '[data-tour="pc-name"]', on: 'bottom' },
        title: 'Nome do cartão',
        text: 'Por exemplo Nubank Roxinho. Preencha só se quiser cadastrar agora.',
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
        text: 'Só no crédito. Esses dias definem em qual fatura cada compra entra.',
    },
    {
        id: 'pc-done',
        route: 'payment-cards.index',
        query: { tour: FIRST_SETUP_TOUR_ID },
        attachTo: { element: '[data-tour="pc-submit"]', on: 'top' },
        title: 'Pronto',
        text: 'Base pronta. Em seguida vamos ao painel do mês, o Dashboard.',
    },
];

export default {
    id: FIRST_SETUP_TOUR_ID,
    label: 'Contas e cartões',
    persistOnboarding: true,
    useDemo: false,
    steps: firstSetupSteps,
};
