<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({ logs: Object, filters: Object, retentionDays: Number });

const f = reactive({ event: props.filters.event || '', type: props.filters.type || '' });
const open = ref(null);
const apply = () => router.get(route('audit.index'), { event: f.event || undefined, type: f.type || undefined }, { preserveState: true });

const color = { created: 'text-emerald-700', updated: 'text-sky-700', deleted: 'text-red-700' };
const label = { created: 'criou', updated: 'alterou', deleted: 'excluiu' };
</script>

<template>
    <Head title="Auditoria" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Auditoria
                <span class="ms-2 text-sm font-normal text-gray-500">retenção de {{ retentionDays }} dias</span>
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap gap-3">
                    <select v-model="f.event" class="rounded-md border-gray-300 text-sm shadow-sm" @change="apply">
                        <option value="">Todos os eventos</option>
                        <option value="created">Criação</option>
                        <option value="updated">Alteração</option>
                        <option value="deleted">Exclusão</option>
                    </select>
                    <select v-model="f.type" class="rounded-md border-gray-300 text-sm shadow-sm" @change="apply">
                        <option value="">Todas as entidades</option>
                        <option v-for="t in ['Schedule', 'ShiftAssignment', 'SwapRequest', 'User', 'ShiftType']" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>

                <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-3">Quando</th>
                                <th class="px-4 py-3">Quem</th>
                                <th class="px-4 py-3">O quê</th>
                                <th class="px-4 py-3">Detalhes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template v-for="l in logs.data" :key="l.id">
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-600">{{ l.when }}</td>
                                    <td class="px-4 py-3">{{ l.user }}</td>
                                    <td class="px-4 py-3"><span :class="color[l.event]">{{ label[l.event] }}</span> {{ l.type }} #{{ l.auditable_id }}</td>
                                    <td class="px-4 py-3">
                                        <button class="text-indigo-600 hover:underline" @click="open = open === l.id ? null : l.id">{{ open === l.id ? 'ocultar' : 'ver diff' }}</button>
                                    </td>
                                </tr>
                                <tr v-if="open === l.id" class="bg-gray-50">
                                    <td colspan="4" class="px-4 py-3">
                                        <div class="grid gap-3 md:grid-cols-2">
                                            <pre class="overflow-x-auto rounded bg-red-50 p-2 text-xs text-red-900">{{ l.old ? JSON.stringify(l.old, null, 2) : '—' }}</pre>
                                            <pre class="overflow-x-auto rounded bg-emerald-50 p-2 text-xs text-emerald-900">{{ l.new ? JSON.stringify(l.new, null, 2) : '—' }}</pre>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="l in logs.links"
                        :key="l.label"
                        :href="l.url || ''"
                        v-html="l.label"
                        class="rounded px-3 py-1 text-sm"
                        :class="[l.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600', !l.url && 'pointer-events-none opacity-40']"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
