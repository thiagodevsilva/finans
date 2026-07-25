import { router } from '@inertiajs/vue3';
import { offset } from '@floating-ui/dom';
import Shepherd from 'shepherd.js';
import { useTourDemo } from '@/Composables/useTourDemo';
import { FIRST_SETUP_TOUR_ID } from '@/tours/firstSetup';
import { getTour } from '@/tours/registry';

const STORAGE_ACTIVE = 'levita.tour.active';
const STORAGE_STEP = 'levita.tour.step';

/** @type {import('shepherd.js').Tour | null} */
let activeTour = null;

const { startDemo, setFormType, setPaymentSelection, clearDemo } = useTourDemo();

function waitForElement(selector, timeoutMs = 2800) {
    return new Promise((resolve) => {
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

function runTourFromStep(tourConfig, stepId) {
    const steps = tourConfig.steps;
    const startIndex = Math.max(0, steps.findIndex((s) => s.id === stepId));
    const startStep = steps[startIndex];

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
                middleware: [offset(18)],
            },
        },
    });

    const demoApi = { setFormType, setPaymentSelection };

    steps.forEach((step, index) => {
        if (index < startIndex) {
            return;
        }

        tour.addStep({
            id: step.id,
            title: step.title,
            text: step.text,
            attachTo: step.attachTo,
            buttons: buildButtons(tour, index, steps, tourConfig.id),
            beforeShowPromise() {
                setTourProgress(tourConfig.id, step.id);
                if (typeof step.onShow === 'function') {
                    step.onShow(demoApi);
                }
                return new Promise((resolve) => {
                    window.setTimeout(() => {
                        waitForElement(step.attachTo.element).then(() => resolve());
                    }, 120);
                });
            },
            when: {
                show() {
                    setTourProgress(tourConfig.id, step.id);
                    if (typeof step.onShow === 'function') {
                        step.onShow(demoApi);
                    }
                },
            },
        });
    });

    let finishedCleanly = false;

    tour.on('complete', () => {
        finishedCleanly = true;
        clearTourStorage();
        activeTour = null;
        clearDemo();

        if (tourConfig.persistOnboarding) {
            router.post(route('onboarding.store'), { status: 'completed' }, {
                preserveScroll: true,
                onSuccess: () => router.visit(route('dashboard')),
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

    function startFirstSetup() {
        startTour(FIRST_SETUP_TOUR_ID);
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
        router.delete(route('onboarding.destroy'));
    }

    /** Inicia o tour da tela atual (ou um id explícito). */
    function startPageTour(tourId = null) {
        const id = tourId || sessionStorage.getItem(STORAGE_ACTIVE);
        // caller should pass resolved id from registry
        if (tourId) {
            startTour(tourId);
        }
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
