export const DASHBOARD_TOUR_ID = 'dashboard';

/**
 * Tour curto da tela Dashboard (com dados fictícios).
 */
export const dashboardSteps = [
    {
        id: 'dash-intro',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-page"]', on: 'bottom' },
        title: 'Seu painel do mês',
        text: 'Aqui você vê o resumo do mês. Os números deste passeio são de exemplo — nada é salvo.',
    },
    {
        id: 'dash-period',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-period"]', on: 'bottom' },
        title: 'Período',
        text: 'Troque mês e ano para olhar outros períodos. O restante da tela acompanha essa escolha.',
    },
    {
        id: 'dash-stats',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-stats"]', on: 'bottom' },
        title: 'Saldo, entradas e saídas',
        text: 'Entradas somam o que entrou. Saídas são o consumo (inclui crédito pela data da compra). Saldo = entradas − saídas.',
    },
    {
        id: 'dash-cash',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-cash"]', on: 'bottom' },
        title: 'Fluxo de caixa',
        text: 'É o dinheiro que de fato saiu da conta: à vista, PIX, débito e pagamento de fatura (exceto pago com outro cartão).',
    },
    {
        id: 'dash-invest',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-invest"]', on: 'bottom' },
        title: 'Investimentos',
        text: 'Aportes saem do caixa, mas não entram nas Saídas de consumo — ficam separados para não misturar com gasto do dia a dia.',
    },
    {
        id: 'dash-invoices',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-invoices"]', on: 'bottom' },
        title: 'Faturas',
        text: 'Fatura atual (a pagar) e futuras vêm dos cartões de crédito. Compras no crédito alimentam essas faixas.',
    },
    {
        id: 'dash-recurring',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-recurring"]', on: 'bottom' },
        title: 'Contas fixas',
        text: 'Resumo do que já foi pago e do que ainda falta nas contas recorrentes do mês.',
    },
    {
        id: 'dash-recent',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-recent"]', on: 'top' },
        title: 'Últimas transações',
        text: 'Amostra dos lançamentos. Cores ajudam: verde entrada, vermelho saída, cinza fatura, teal investimento.',
    },
    {
        id: 'dash-new',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-new"]', on: 'bottom' },
        title: 'Nova transação',
        text: 'Daqui você lança movimentações. O guia de Transações (Ajuda naquela tela) explica cada tipo com calma.',
    },
];

export default {
    id: DASHBOARD_TOUR_ID,
    label: 'Dashboard',
    persistOnboarding: false,
    useDemo: true,
    steps: dashboardSteps,
};
