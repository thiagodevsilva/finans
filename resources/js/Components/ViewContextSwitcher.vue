<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
const viewContext = computed(() => page.props.viewContext);
const viewMembers = computed(() => page.props.viewMembers || []);
const viewCompanies = computed(() => page.props.viewCompanies || []);
const isOwner = computed(() => user.value?.is_owner === true);
const isPersonal = computed(() =>
    viewContext.value?.scope === 'mine' || viewContext.value?.scope === 'member',
);

const apply = (payload) => {
    router.put(route('view-context.update'), payload, {
        preserveScroll: true,
        preserveState: false,
    });
};

const selectValue = computed(() => {
    if (!viewContext.value) return 'family';
    if (viewContext.value.scope === 'family') return 'family';
    if (viewContext.value.scope === 'mine') return `member:${user.value?.id}`;
    if (viewContext.value.scope === 'member' && viewContext.value.member_id) {
        return `member:${viewContext.value.member_id}`;
    }
    return 'family';
});

const onSelectChange = (event) => {
    const value = event.target.value;
    if (value === 'family') {
        apply({ scope: 'family', member_id: null, company: 'all' });
        return;
    }
    const memberId = value.startsWith('member:') ? value.slice(7) : null;
    if (!memberId) {
        apply({ scope: 'family', member_id: null, company: 'all' });
        return;
    }
    if (memberId === user.value?.id) {
        apply({ scope: 'mine', member_id: null, company: 'all' });
        return;
    }
    apply({ scope: 'member', member_id: memberId, company: 'all' });
};

const setCompany = (event) => {
    apply({
        scope: viewContext.value?.scope || 'mine',
        member_id: viewContext.value?.member_id || null,
        company: event.target.value,
    });
};

const selectClass =
    'w-full appearance-none rounded-xl border-0 bg-lightPrimary py-2 pl-3 pr-8 text-[0.825rem] font-medium text-navy-700 shadow-none ring-1 ring-horizon-200/80 transition hover:ring-horizon-300 focus:ring-2 focus:ring-brand-500';
</script>

<template>
    <div v-if="viewContext" class="space-y-2" data-tour="view-context">
        <div class="relative">
            <label class="sr-only" for="sidebar-view-scope">Visão</label>
            <select
                id="sidebar-view-scope"
                class="view-select"
                :class="selectClass"
                :value="selectValue"
                @change="onSelectChange"
            >
                <option value="family">Família</option>
                <option
                    v-if="!isOwner"
                    :value="`member:${user?.id}`"
                >
                    Eu
                </option>
                <template v-if="isOwner">
                    <option disabled>────────</option>
                    <option
                        v-for="m in viewMembers"
                        :key="m.id"
                        :value="`member:${m.id}`"
                    >
                        {{ m.id === user?.id ? 'Eu' : m.name }}
                    </option>
                </template>
            </select>
            <span
                class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-horizon-400"
                aria-hidden="true"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                    <path
                        fill-rule="evenodd"
                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                        clip-rule="evenodd"
                    />
                </svg>
            </span>
        </div>

        <div v-if="isPersonal && viewCompanies.length" class="relative">
            <label class="sr-only" for="sidebar-view-company">CNPJ</label>
            <select
                id="sidebar-view-company"
                class="view-select"
                :class="selectClass"
                :value="viewContext.company || 'all'"
                @change="setCompany"
            >
                <option value="all">Tudo (PF + CNPJs)</option>
                <option value="none">Só pessoal</option>
                <option
                    v-for="c in viewCompanies"
                    :key="c.id"
                    :value="c.id"
                >
                    {{ c.name }}
                </option>
            </select>
            <span
                class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-horizon-400"
                aria-hidden="true"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                    <path
                        fill-rule="evenodd"
                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                        clip-rule="evenodd"
                    />
                </svg>
            </span>
        </div>
    </div>
</template>
