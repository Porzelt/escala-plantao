<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({ schedules: Object, units: Array });

const can = usePage().props.auth.can;
const form = useForm({ month: '', unit_id: '' });

const fmtMonth = (iso) => new Date(iso).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric', timeZone: 'UTC' });
</script>

<template>
    <Head title="Escalas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Escalas</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <form
                    v-if="can.escalas.write"
                    class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-5 shadow-sm"
                    @submit.prevent="form.post(route('schedules.store'))"
                >
                    <div>
                        <label class="block text-sm text-gray-600">Mês</label>
                        <input v-model="form.month" type="month" class="mt-1 rounded-md border-gray-300 shadow-sm" required />
                        <InputError :message="form.errors.month" />
                    </div>
                    <div v-if="units.length">
                        <label class="block text-sm text-gray-600">Unidade</label>
                        <select v-model="form.unit_id" class="mt-1 rounded-md border-gray-300 shadow-sm" required>
                            <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                        <InputError :message="form.errors.unit_id" />
                    </div>
                    <PrimaryButton :disabled="form.processing">Nova escala</PrimaryButton>
                </form>

                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-3">Mês</th>
                                <th class="px-4 py-3">Unidade</th>
                                <th class="px-4 py-3">Situação</th>
                                <th class="px-4 py-3 text-right">Plantões</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="s in schedules.data" :key="s.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 capitalize">
                                    <Link :href="route('schedules.show', s.id)" class="text-indigo-600 hover:underline">{{ fmtMonth(s.month) }}</Link>
                                </td>
                                <td class="px-4 py-3">{{ s.unit.code }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="s.status === 'publicada' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                    >{{ s.status }}</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ s.assignments_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex gap-2">
                    <Link
                        v-for="l in schedules.links"
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
