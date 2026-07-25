import firstSetup from '@/tours/firstSetup';
import dashboard from '@/tours/dashboard';
import transactions from '@/tours/transactions';

/** @type {Record<string, import('@/tours/firstSetup').default>} */
export const tourRegistry = {
    [firstSetup.id]: firstSetup,
    [dashboard.id]: dashboard,
    [transactions.id]: transactions,
};

/** Tour sugerido por rota Ziggy (botão Ajuda nesta tela) */
export const pageTourByRoute = {
    dashboard: dashboard.id,
    'bank-accounts.index': firstSetup.id,
    'payment-cards.index': firstSetup.id,
    'transactions.index': transactions.id,
    'transactions.create': transactions.id,
    'transactions.edit': transactions.id,
};

export function getTour(id) {
    return tourRegistry[id] || null;
}

export function resolvePageTourId() {
    try {
        for (const [routeName, tourId] of Object.entries(pageTourByRoute)) {
            if (route().current(routeName)) {
                return tourId;
            }
        }
    } catch {
        // ziggy ainda não pronto
    }
    return null;
}
