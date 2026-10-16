<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useExportProgress } from '@/Composables/useExportProgress';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ schedule: Object, shiftTypes: Array, assignments: Array, loads: Array, can: Object });

const me = usePage().props.auth.user;
const seed = ref(props.schedule.seed || '');
const busy = ref(false);
const { state: exportState, start: startExport } = useExportProgress();

const monthLabel = computed(() =>
    new Date(props.schedule.month).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric', timeZone: 'UTC' }),
);

// dias do mês com as pessoas de cada turno
const days = computed(() => {
    const [y, m] = props.schedule.month.slice(0, 10).split('-').map(Number);
    const total = new Date(Date.UTC(y, m, 0)).getUTCDate();
    const index = {};
    for (const a of props.assignments) {
        (index[`${a.date}|${a.shift_type_id}`] ||= []).push(a);
    }
    return Array.from({ length: total }, (_, i) => {
        const date = `${y}-${String(m).padStart(2, '0')}-${String(i + 1).padStart(2, '0')}`;
        const dow = new Date(`${date}T12:00:00Z`).getUTCDay();
        return {
            date,
            label: String(i + 1).padStart(2, '0'),
            dow: ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'][dow],
            weekend: dow === 0 || dow === 6,
            shifts: props.shiftTypes.map((t) => ({ type: t, people: index[`${date}|${t.id}`] || [] })),
        };
    });
});

const maxLoad = computed(() => Math.max(1, ...props.loads.map((l) => l.total)));

const generate = () => {
    busy.value = true;
    router.post(route('schedules.generate', props.schedule.id), { seed: seed.value || null }, {
        preserveScroll: true,
        onFinish: () => (busy.value = false),
    });
};

const publish = () => {
    if (confirm('Publicar a escala? Depois disso ela não pode ser regerada.')) {
        router.post(route('schedules.publish', props.schedule.id), {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head :title="`Escala ${monthLabel}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold capitalize leading-tight text-gray-800">
                    <Link :href="route('schedules.index')" class="text-gray-400 hover:text-gray-600">Escalas /</Link>
                    {{ monthLabel }} · {{ schedule.unit.code }}
                    <span
                        class="ms-2 rounded-full px-2 py-0.5 align-middle text-xs font-medium normal-case"
                        :class="schedule.status === 'publicada' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                    >{{ schedule.status }}</span>
                </h2>

                <div class="flex flex-wrap items-center gap-2">
                    <template v-if="can.update">
                        <input v-model="seed" type="number" min="1" placeholder="semente" class="w-28 rounded-md border-gray-300 text-sm shadow-sm" title="Mesma semente = mesma escala" />
                        <PrimaryButton :disabled="busy" @click="generate">{{ busy ? 'Gerando…' : 'Gerar distribuição' }}</PrimaryButton>
                    </template>
                    <SecondaryButton v-if="can.publish" @click="publish">Publicar</SecondaryButton>
                    <SecondaryButton @click="startExport(schedule.id, 'xlsx')">Excel</SecondaryButton>
                    <SecondaryButton @click="startExport(schedule.id, 'pdf')">PDF</SecondaryButton>
                </div>
            </div>

            <div v-if="exportState" class="mt-4">
                <div class="flex justify-between text-xs text-gray-500">
                    <span>Exportação: {{ exportState.status.replace('_', ' ') }}</span>
                    <span class="tabular-nums">{{ exportState.progress }}%</span>
                </div>
                <div class="mt-1 h-2 overflow-hidden rounded bg-gray-200">
                    <div
                        class="h-full rounded transition-all duration-500"
                        :class="exportState.status === 'falhou' ? 'bg-red-500' : 'bg-indigo-500'"
                        :style="{ width: `${exportState.progress}%` }"
                    />
                </div>
                <p v-if="exportState.error" class="mt-1 text-xs text-red-600">{{ exportState.error }}</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-4 lg:px-8">
                <div class="overflow-x-auto rounded-lg bg-white shadow-sm lg:col-span-3">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-3 py-2">Dia</th>
                                <th v-for="t in shiftTypes" :key="t.id" class="px-3 py-2">
                                    {{ t.name }} <span class="font-normal text-gray-400">{{ t.starts_at.slice(0, 5) }} · {{ t.duration_hours }}h</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="d in days" :key="d.date" :class="d.weekend && 'bg-indigo-50/40'">
                                <td class="whitespace-nowrap px-3 py-2 tabular-nums text-gray-700">
                                    {{ d.label }} <span class="text-xs text-gray-400">{{ d.dow }}</span>
                                </td>
                                <td v-for="s in d.shifts" :key="s.type.id" class="px-3 py-2">
                                    <span
                                        v-for="p in s.people"
                                        :key="p.id"
                                        class="me-1 inline-block rounded px-1.5 py-0.5 text-xs"
                                        :class="p.user_id === me.id ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'"
                                    >{{ p.user }}</span>
                                    <span v-if="!s.people.length" class="text-xs text-gray-300">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <aside class="space-y-4">
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <h3 class="font-medium text-gray-800">Carga por membro</h3>
                        <p class="text-xs text-gray-400">Distribuição justa: menor carga ponderada primeiro, empate por sorteio.</p>
                        <ul class="mt-3 space-y-2">
                            <li v-for="l in loads" :key="l.name">
                                <div class="flex justify-between text-xs text-gray-600">
                                    <span class="truncate">{{ l.name }}</span>
                                    <span class="tabular-nums">{{ l.total }}</span>
                                </div>
                                <div class="mt-0.5 h-1.5 rounded bg-gray-100">
                                    <div class="h-full rounded bg-indigo-400" :style="{ width: `${(l.total / maxLoad) * 100}%` }" />
                                </div>
                            </li>
                            <li v-if="!loads.length" class="text-sm text-gray-400">Ainda não gerada.</li>
                        </ul>
                    </div>
                    <div v-if="schedule.seed" class="rounded-lg bg-white p-4 text-xs text-gray-500 shadow-sm">
                        Semente do sorteio: <span class="font-mono text-gray-800">{{ schedule.seed }}</span>
                    </div>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
