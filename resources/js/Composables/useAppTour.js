import { router } from '@inertiajs/vue3';
import { offset, flip, shift, limitShift } from '@floating-ui/dom';
import Shepherd from 'shepherd.js';
import { useTourDemo } from '@/Composables/useTourDemo';
import { DASHBOARD_TOUR_ID } from '@/tours/dashboard';
import { FIRST_SETUP_TOUR_ID } from '@/tours/firstSetup';
import { getTour, resolvePageTourId } from '@/tours/registry';

const STORAGE_ACTIVE = 'levita.tour.active';
const STORAGE_STEP = 'levita.tour.step';

/** @type {import('shepherd.js').Tour | null} */
let activeTour = null;

const { startDemo, setFormType, setPaymentSelection, clearDemo } = useTourDemo();

function waitForElement(selector, timeoutMs = 2800) {
    return new Promise((resolve) => {
        if (!selector) {
            resolve(null);
            return;
        }

        const existing = document.querySelector(selector);
        if (existing) {
            resolve(existing);
            return;
        }

        const started = Date.now();
        const timer = window.setInterval(() => {
            const el = document.querySelector(selector);
            if (el || Date.now() - started > timeoutMs) {
                window.clearInterval(timer);
                resolve(el);
            }
        }, 50);
    });
}

function setSidebarOpen(open) {
    if (typeof window === 'undefined') {
        return;
    }
    window.dispatchEvent(new CustomEvent('levita:tour-sidebar', { detail: { open: Boolean(open) } }));
}

function clearTourStorage() {
    sessionStorage.removeItem(STORAGE_ACTIVE);
    sessionStorage.removeItem(STORAGE_STEP);
}

function setTourProgress(tourId, stepId) {
    sessionStorage.setItem(STORAGE_ACTIVE, tourId);
    sessionStorage.setItem(STORAGE_STEP, stepId);
}

function visitStepRoute(step) {
    const url = route(step.route, step.query || {});
    router.visit(url, { preserveScroll: false });
}

function stripTourQuery() {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    if (!url.searchParams.has('tour')) {
        return;
    }

    url.searchParams.delete('tour');
    const next = url.pathname + (url.searchParams.toString() ? `?${url.searchParams}` : '');
    router.visit(next, { replace: true, preserveState: true, preserveScroll: true });
}

function persistOnboardingStatus(status) {
    router.post(route('onboarding.store'), { status }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function destroyActiveTour() {
    if (!activeTour) {
        return;
    }

    const tour = activeTour;
    activeTour = null;
    tour.off('cancel');
    tour.off('complete');

    try {
        tour.hide();
    } catch {
        // already torn down
    }

    setSidebarOpen(false);
}

function buildButtons(tour, stepIndex, steps, tourId) {
    const isFirst = stepIndex === 0;
    const isLast = stepIndex === steps.length - 1;
    const buttons = [
        {
            text: 'Pular',
            secondary: true,
            action() {
                tour.cancel();
            },
        },
    ];

    if (!isFirst) {
        buttons.push({
            text: 'Anterior',
            secondary: true,
            action() {
                const prev = steps[stepIndex - 1];
                setTourProgress(tourId, prev.id);
                if (!route().current(prev.route)) {
                    destroyActiveTour();
                    visitStepRoute(prev);
                    return;
                }
                tour.back();
            },
        });
    }

    buttons.push({
        text: isLast ? 'Concluir' : 'Próximo',
        action() {
            if (isLast) {
                tour.complete();
                return;
            }

            const next = steps[stepIndex + 1];
            setTourProgress(tourId, next.id);

            if (!route().current(next.route)) {
                destroyActiveTour();
                visitStepRoute(next);
                return;
            }

            tour.next();
        },
    });

    return buttons;
}

function prepareStepUi(step) {
    if (step.openSidebar || step.attachTo?.element?.includes('nav-')) {
        setSidebarOpen(true);
    } else {
        setSidebarOpen(false);
    }
}

function runTourFromStep(tourConfig, stepId) {
    const steps = tourConfig.steps;
    const startIndex = Math.max(0, steps.findIndex((s) => s.id === stepId));
    const startStep = steps[startIndex] || steps[0];

    if (!route().current(startStep.route)) {
        setTourProgress(tourConfig.id, startStep.id);
        visitStepRoute(startStep);
        return;
    }

    destroyActiveTour();

    if (tourConfig.useDemo) {
        startDemo(tourConfig.id);
    }

    const tour = new Shepherd.Tour({
        useModalOverlay: true,
        defaultStepOptions: {
            cancelIcon: { enabled: true },
            classes: 'shepherd-levita',
            scrollTo: { behavior: 'smooth', block: 'center' },
            modalOverlayOpeningPadding: 14,
            modalOverlayOpeningRadius: 14,
            floatingUIOptions: {
                middleware: [
                    offset(14),
                    flip({ fallbackPlacements: ['bottom', 'top', 'left', 'right'] }),
                    shift({ padding: 12, limiter: limitShift() }),
                ],
            },
        },
    });

    const demoApi = { setFormType, setPaymentSelection };

    steps.forEach((step, index) => {
        if (index < startIndex) {
            return;
        }

        const stepOptions = {
            id: step.id,
            title: step.title,
            text: step.text,
            buttons: buildButtons(tour, index, steps, tourConfig.id),
            beforeShowPromise() {
                setTourProgress(tourConfig.id, step.id);
                prepareStepUi(step);
                if (typeof step.onShow === 'function') {
                    step.onShow(demoApi);
                }
                return new Promise((resolve) => {
                    window.setTimeout(() => {
                        if (step.attachTo?.element) {
                            waitForElement(step.attachTo.element).then(() => resolve());
                        } else {
                            resolve();
                        }
                    }, step.openSidebar || step.attachTo?.element?.includes('nav-') ? 220 : 120);
                });
            },
            when: {
                show() {
                    setTourProgress(tourConfig.id, step.id);
                    prepareStepUi(step);
                    if (typeof step.onShow === 'function') {
                        step.onShow(demoApi);
                    }
                },
            },
        };

        if (step.attachTo) {
            stepOptions.attachTo = step.attachTo;
        }

        tour.addStep(stepOptions);
    });

    let finishedCleanly = false;

    tour.on('complete', () => {
        finishedCleanly = true;
        clearTourStorage();
        activeTour = null;
        clearDemo();
        setSidebarOpen(false);

        if (tourConfig.persistOnboarding) {
            router.post(route('onboarding.store'), { status: 'completed' }, {
                preserveScroll: true,
                onSuccess: () => {
                    const dash = getTour(DASHBOARD_TOUR_ID);
                    const first = dash?.steps?.[0];
                    if (first) {
                        setTourProgress(DASHBOARD_TOUR_ID, first.id);
                    }
                    router.visit(route('dashboard', { tour: DASHBOARD_TOUR_ID }));
                },
            });
            return;
        }

        stripTourQuery();
    });

    tour.on('cancel', () => {
        if (finishedCleanly) {
            return;
        }
        clearTourStorage();
        activeTour = null;
        clearDemo();
        setSidebarOpen(false);

        if (tourConfig.persistOnboarding) {
            persistOnboardingStatus('skipped');
        } else {
            stripTourQuery();
        }
    });

    activeTour = tour;
    tour.start();
}

/**
 * API pública de tours do Levita.
 */
export function useAppTour() {
    function activeTourId() {
        return sessionStorage.getItem(STORAGE_ACTIVE);
    }

    function isTourActive(tourId = null) {
        const active = activeTourId();
        if (!active) {
            return false;
        }
        return tourId ? active === tourId : true;
    }

    function startTour(tourId) {
        const config = getTour(tourId);
        if (!config?.steps?.length) {
            return;
        }

        const first = config.steps[0];
        setTourProgress(tourId, first.id);
        if (config.useDemo) {
            startDemo(tourId);
        }
        runTourFromStep(config, first.id);
    }

    function startFirstSetup(fromStepId = null) {
        const config = getTour(FIRST_SETUP_TOUR_ID);
        if (!config?.steps?.length) {
            return;
        }

        const stepId = fromStepId || config.steps[0].id;
        setTourProgress(FIRST_SETUP_TOUR_ID, stepId);
        runTourFromStep(config, stepId);
    }

    function resumeIfActive() {
        const tourId = activeTourId();
        if (!tourId) {
            return;
        }

        const config = getTour(tourId);
        if (!config) {
            clearTourStorage();
            clearDemo();
            return;
        }

        const stepId = sessionStorage.getItem(STORAGE_STEP) || config.steps[0].id;
        window.setTimeout(() => runTourFromStep(config, stepId), 80);
    }

    function skipOnboarding() {
        clearTourStorage();
        destroyActiveTour();
        clearDemo();
        persistOnboardingStatus('skipped');
    }

    function restartOnboarding() {
        clearTourStorage();
        destroyActiveTour();
        clearDemo();

        const onDashboard = route().current('dashboard');
        const first = getTour(FIRST_SETUP_TOUR_ID)?.steps?.[0];
        if (first) {
            setTourProgress(FIRST_SETUP_TOUR_ID, first.id);
        }

        // No Dashboard: inicia na hora. Em outra tela: após reset, o start navega.
        if (onDashboard) {
            startFirstSetup();
        }

        router.delete(route('onboarding.destroy'), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (!onDashboard) {
                    startFirstSetup();
                }
            },
        });
    }

    /** Inicia o tour da tela atual (ou um id explícito). Nunca inicia first-setup. */
    function startPageTour(tourId = null) {
        const id = tourId || resolvePageTourId();
        if (!id || id === FIRST_SETUP_TOUR_ID) {
            return;
        }
        startTour(id);
    }

    return {
        activeTourId,
        isTourActive,
        startTour,
        startFirstSetup,
        resumeIfActive,
        skipOnboarding,
        restartOnboarding,
        startPageTour,
        FIRST_SETUP_TOUR_ID,
    };
}

export { FIRST_SETUP_TOUR_ID };
