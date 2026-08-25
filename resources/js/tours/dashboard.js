export const DASHBOARD_TOUR_ID = 'dashboard';

/**
 * Tour curto da tela Dashboard (com dados fictícios).
 * Evitar : e ; na copy voltada ao usuário.
 */
export const dashboardSteps = [
    {
        id: 'dash-intro',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-page"]', on: 'bottom' },
        title: 'Seu painel do mês',
        text: 'Aqui você vê o resumo do mês. Os números deste tutorial são de exemplo. Nada é salvo.',
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
        id: 'dash-balance',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-balance"]', on: 'bottom' },
        title: 'Saldo (caixa)',
        text: 'É o dinheiro da família. Soma o valor informado com o que entrou e tira o que saiu de fato, como PIX, débito, fatura e investimento. Compra no crédito não reduz até pagar a fatura. O dono pode usar Atualizar saldo para corrigir o valor.',
    },
    {
        id: 'dash-stats',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-stats"]', on: 'bottom' },
        title: 'Saldo do mês e gastos',
        text: 'No mesmo card ficam o saldo do mês (entradas menos saídas de caixa menos investimentos) e, ao lado, entradas e gastos (crédito e débito). Toque em Crédito ou Débito para ver os lançamentos. Contas fixas ficam no bloco próprio.',
    },
    {
        id: 'dash-recurring',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-recurring"]', on: 'bottom' },
        title: 'Contas fixas',
        text: 'Progresso do mês por valor. Contas fixas têm vencimento; variáveis abatem a estimativa com vários gastos. Use R$ ou % e abra a lista em Ver contas fixas.',
    },
    {
        id: 'dash-recent',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-recent"]', on: 'top' },
        title: 'Últimas transações',
        text: 'Amostra dos lançamentos. Cores ajudam. Verde é entrada, vermelho é gasto, cinza é fatura e teal é investimento.',
    },
    {
        id: 'dash-new',
        route: 'dashboard',
        query: { tour: DASHBOARD_TOUR_ID },
        attachTo: { element: '[data-tour="dash-new"]', on: 'bottom' },
        title: 'Nova transação',
        text: 'Daqui você lança movimentações. O tutorial de Transações, em Ajuda naquela tela, explica cada tipo com calma.',
    },
];

export default {
    id: DASHBOARD_TOUR_ID,
    label: 'Dashboard',
    persistOnboarding: false,
    useDemo: true,
    steps: dashboardSteps,
};
