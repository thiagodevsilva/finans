import firstSetup from '@/tours/firstSetup';
import dashboard from '@/tours/dashboard';
import transactions from '@/tours/transactions';
import bankAccounts from '@/tours/bankAccounts';
import paymentCards from '@/tours/paymentCards';

/** @type {Record<string, { id: string, steps: unknown[] }>} */
export const tourRegistry = {
    [firstSetup.id]: firstSetup,
    [dashboard.id]: dashboard,
    [transactions.id]: transactions,
    [bankAccounts.id]: bankAccounts,
    [paymentCards.id]: paymentCards,
};

/**
 * Tour sugerido por rota Ziggy (botão Ajuda nesta tela).
 * Nunca mapear first-setup aqui — o completo só via Refazer tutorial.
 */
export const pageTourByRoute = {
    dashboard: dashboard.id,
    'bank-accounts.index': bankAccounts.id,
    'payment-cards.index': paymentCards.id,
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
