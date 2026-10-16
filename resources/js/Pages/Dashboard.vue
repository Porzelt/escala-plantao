<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ stats: Object, nextShifts: Array });

const unit = computed(() => usePage().props.auth.unit);
const weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

// DAYOFWEEK do MariaDB: 1 = domingo
const byWeekday = computed(() => {
    const raw = props.stats.by_weekday || {};
    const values = weekdays.map((_, i) => Number(raw[i + 1] || 0));
    const max = Math.max(1, ...values);
    return weekdays.map((label, i) => ({ label, value: values[i], pct: (values[i] / max) * 100 }));
});

const cards = computed(() => [
    { label: 'Membros ativos', value: props.stats.members },
    { label: 'Plantões no mês', value: props.stats.shifts_month },
    { label: 'Trocas em aberto', value: props.stats.pending_swaps },
    { label: 'Diferença de carga', value: props.stats.spread, hint: 'mais escalado − menos escalado' },
]);
</script>

<template>
    <Head title="Painel" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Painel gerencial
                <span v-if="unit" class="ms-2 text-sm font-normal text-gray-500">{{ unit.name }}</span>
                <span v-else class="ms-2 text-sm font-normal text-gray-500">todas as unidades</span>
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div v-for="c in cards" :key="c.label" class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="text-sm text-gray-500">{{ c.label }}</div>
                        <div class="mt-1 text-3xl font-semibold tabular-nums text-gray-900">{{ c.value }}</div>
                        <div v-if="c.hint" class="mt-1 text-xs text-gray-400">{{ c.hint }}</div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <div class="rounded-lg bg-white p-5 shadow-sm lg:col-span-2">
                        <h3 class="font-medium text-gray-800">Plantões por dia da semana (mês atual)</h3>
                        <div class="mt-4 flex h-44 items-end gap-3">
                            <div v-for="d in byWeekday" :key="d.label" class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                                <span class="text-xs tabular-nums text-gray-500">{{ d.value }}</span>
                                <div class="w-full rounded-t bg-indigo-500" :style="{ height: `${Math.max(d.pct, 2)}%` }" />
                                <span class="text-xs text-gray-600">{{ d.label }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <h3 class="font-medium text-gray-800">Meus próximos plantões</h3>
                        <ul class="mt-3 divide-y divide-gray-100">
                            <li v-for="s in nextShifts" :key="s.date + s.shift" class="flex justify-between py-2 text-sm">
                                <span class="text-gray-700">{{ s.date }}</span>
                                <span class="text-gray-500">{{ s.shift }} · {{ s.starts_at }}</span>
                            </li>
                            <li v-if="!nextShifts.length" class="py-2 text-sm text-gray-400">Nenhum plantão agendado.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
