<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps({ swaps: Object, myShifts: Array, colleagues: Array });

const form = useForm({ shift_assignment_id: '', target_user_id: '', reason: '' });

const submit = () => form.post(route('swaps.store'), { preserveScroll: true, onSuccess: () => form.reset() });
const act = (name, id, data = {}) => router.post(route(name, id), data, { preserveScroll: true });

const badge = {
    pendente: 'bg-amber-100 text-amber-800',
    aceita: 'bg-sky-100 text-sky-800',
    aprovada: 'bg-emerald-100 text-emerald-800',
    recusada: 'bg-red-100 text-red-800',
    cancelada: 'bg-gray-100 text-gray-600',
};
const fmt = (iso) => new Date(`${iso}T12:00:00Z`).toLocaleDateString('pt-BR', { timeZone: 'UTC' });
</script>

<template>
    <Head title="Trocas de plantão" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Trocas de plantão</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <form v-if="myShifts.length" class="grid gap-3 rounded-lg bg-white p-5 shadow-sm md:grid-cols-4" @submit.prevent="submit">
                    <div>
                        <label class="block text-sm text-gray-600">Meu plantão</label>
                        <select v-model="form.shift_assignment_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" required>
                            <option v-for="s in myShifts" :key="s.id" :value="s.id">{{ s.label }}</option>
                        </select>
                        <InputError :message="form.errors.shift_assignment_id" />
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600">Trocar com</label>
                        <select v-model="form.target_user_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" required>
                            <option v-for="c in colleagues" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <InputError :message="form.errors.target_user_id" />
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600">Motivo</label>
                        <input v-model="form.reason" maxlength="255" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <div class="flex items-end"><PrimaryButton :disabled="form.processing">Solicitar troca</PrimaryButton></div>
                </form>

                <p class="text-xs text-gray-500">Fluxo: solicitante → colega aceita → gestor aprova (o plantão muda de dono numa transação com lock).</p>

                <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-3">Data</th>
                                <th class="px-4 py-3">Turno</th>
                                <th class="px-4 py-3">De → Para</th>
                                <th class="px-4 py-3">Situação</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="s in swaps.data" :key="s.id">
                                <td class="px-4 py-3 tabular-nums">{{ fmt(s.date) }}</td>
                                <td class="px-4 py-3">{{ s.shift }}</td>
                                <td class="px-4 py-3">{{ s.requester }} → {{ s.target }}<div v-if="s.reason" class="text-xs text-gray-400">{{ s.reason }}</div></td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="badge[s.status]">{{ s.status }}</span></td>
                                <td class="space-x-2 whitespace-nowrap px-4 py-3 text-right">
                                    <template v-if="s.can.respond">
                                        <button class="text-emerald-700 hover:underline" @click="act('swaps.respond', s.id, { accept: true })">Aceitar</button>
                                        <button class="text-red-600 hover:underline" @click="act('swaps.respond', s.id, { accept: false })">Recusar</button>
                                    </template>
                                    <template v-if="s.can.decide">
                                        <button class="text-emerald-700 hover:underline" @click="act('swaps.decide', s.id, { approve: true })">Aprovar</button>
                                        <button class="text-red-600 hover:underline" @click="act('swaps.decide', s.id, { approve: false })">Negar</button>
                                    </template>
                                    <button v-if="s.can.cancel" class="text-gray-500 hover:underline" @click="act('swaps.cancel', s.id)">Cancelar</button>
                                </td>
                            </tr>
                            <tr v-if="!swaps.data.length"><td colspan="5" class="px-4 py-6 text-center text-gray-400">Nenhuma troca.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
