export const BANK_ACCOUNTS_TOUR_ID = 'bank-accounts';

/**
 * Tour curto só da tela Contas (Ajuda nesta tela).
 * O tutorial completo Contas+Cartões é o first-setup (Refazer tutorial).
 */
export const bankAccountsSteps = [
    {
        id: 'ba-intro',
        route: 'bank-accounts.index',
        query: { tour: BANK_ACCOUNTS_TOUR_ID },
        attachTo: { element: '[data-tour="ba-page"]', on: 'bottom' },
        title: 'Suas contas',
        text: 'Aqui ficam as contas da família, por exemplo Nubank Conta. Não precisa preencher agora. Pode só seguir o tutorial e criar depois.',
    },
    {
        id: 'ba-name',
        route: 'bank-accounts.index',
        query: { tour: BANK_ACCOUNTS_TOUR_ID },
        attachTo: { element: '[data-tour="ba-name"]', on: 'bottom' },
        title: 'Nome da conta',
        text: 'Quando for cadastrar, use um nome fácil de reconhecer. No tutorial, pode avançar sem digitar.',
    },
    {
        id: 'ba-color',
        route: 'bank-accounts.index',
        query: { tour: BANK_ACCOUNTS_TOUR_ID },
        attachTo: { element: '[data-tour="ba-color"]', on: 'bottom' },
        title: 'Cor',
        text: 'A cor ajuda a achar a conta nos lançamentos. Também é só para quando você for criar.',
    },
    {
        id: 'ba-submit',
        route: 'bank-accounts.index',
        query: { tour: BANK_ACCOUNTS_TOUR_ID },
        attachTo: { element: '[data-tour="ba-submit"]', on: 'top' },
        title: 'Adicionar',
        text: 'Este botão salva a conta. No passeio, pode seguir sem cadastrar. Pronto, você já conhece esta tela.',
    },
];

export default {
    id: BANK_ACCOUNTS_TOUR_ID,
    label: 'Contas',
    persistOnboarding: false,
    useDemo: false,
    steps: bankAccountsSteps,
};
