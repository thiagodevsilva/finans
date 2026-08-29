<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
const viewContext = computed(() => page.props.viewContext);
const viewMembers = computed(() => page.props.viewMembers || []);
const viewCompanies = computed(() => page.props.viewCompanies || []);
const isOwner = computed(() => user.value?.is_owner === true);

const companiesByMember = computed(() => {
    const map = {};
    for (const company of viewCompanies.value) {
        if (!map[company.user_id]) {
            map[company.user_id] = [];
        }
        map[company.user_id].push(company);
    }
    return map;
});

const membersForSelect = computed(() => {
    if (isOwner.value) {
        return viewMembers.value;
    }
    return user.value ? [{ id: user.value.id, name: user.value.name }] : [];
});

const apply = (payload) => {
    router.put(route('view-context.update'), payload, {
        preserveScroll: true,
        preserveState: false,
    });
};

const optionValue = (memberId, company) => {
    const isSelf = memberId === user.value?.id;
    if (isSelf) {
        return `mine:${company}`;
    }
    return `member:${memberId}:${company}`;
};

const optionsForMember = (member) => {
    const isSelf = member.id === user.value?.id;
    const baseName = isSelf ? 'Eu' : member.name;
    const companies = companiesByMember.value[member.id] || [];

    if (companies.length === 0) {
        return [{ value: optionValue(member.id, 'all'), label: baseName }];
    }

    return [
        { value: optionValue(member.id, 'all'), label: `${baseName} (tudo)` },
        { value: optionValue(member.id, 'none'), label: `${baseName} · Só pessoal` },
        ...companies.map((c) => ({
            value: optionValue(member.id, c.id),
            label: `${baseName} · ${c.name}`,
        })),
    ];
};

const selectOptions = computed(() => {
    const opts = [{ value: 'family', label: 'Família' }];
    for (const member of membersForSelect.value) {
        opts.push(...optionsForMember(member));
    }
    return opts;
});

const selectValue = computed(() => {
    if (!viewContext.value || viewContext.value.scope === 'family') {
        return 'family';
    }

    const company = viewContext.value.company || 'all';
    const memberId = viewContext.value.scope === 'mine'
        ? user.value?.id
        : viewContext.value.member_id;

    if (!memberId) {
        return 'family';
    }

    return optionValue(memberId, company);
});

const onSelectChange = (event) => {
    const value = event.target.value;

    if (value === 'family') {
        apply({ scope: 'family', member_id: null, company: 'all' });
        return;
    }

    if (value.startsWith('mine:')) {
        apply({
            scope: 'mine',
            member_id: null,
            company: value.slice(5) || 'all',
        });
        return;
    }

    if (value.startsWith('member:')) {
        const [, memberId, company = 'all'] = value.split(':');
        if (memberId === user.value?.id) {
            apply({ scope: 'mine', member_id: null, company });
            return;
        }
        apply({ scope: 'member', member_id: memberId, company });
    }
};

const selectClass =
    'w-full appearance-none rounded-xl border-0 bg-lightPrimary py-2 pl-3 pr-8 text-[0.825rem] font-medium text-navy-700 shadow-none ring-1 ring-horizon-200/80 transition hover:ring-horizon-300 focus:ring-2 focus:ring-brand-500';
</script>

<template>
    <div v-if="viewContext" class="relative" data-tour="view-context">
        <label class="sr-only" for="sidebar-view-scope">Visão</label>
        <select
            id="sidebar-view-scope"
            class="view-select"
            :class="selectClass"
            :value="selectValue"
            @change="onSelectChange"
        >
            <option
                v-for="opt in selectOptions"
                :key="opt.value"
                :value="opt.value"
            >
                {{ opt.label }}
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
</template>
