<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ members: Object, filters: Object, roles: Array });

const can = usePage().props.auth.can;
const q = ref(props.filters.q || '');
let t;
watch(q, (v) => {
    clearTimeout(t);
    t = setTimeout(() => router.get(route('members.index'), { q: v || undefined }, { preserveState: true, replace: true }), 300);
});

const form = useForm({ name: '', email: '', registration: '', role: 'membro' });
const update = (m, data) => router.patch(route('members.update', m.id), data, { preserveScroll: true });
</script>

<template>
    <Head title="Membros" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Membros</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <form
                    v-if="can.membros.write"
                    class="grid gap-3 rounded-lg bg-white p-5 shadow-sm md:grid-cols-5"
                    @submit.prevent="form.post(route('members.store'), { preserveScroll: true, onSuccess: () => form.reset() })"
                >
                    <div><input v-model="form.name" placeholder="Nome" class="w-full rounded-md border-gray-300 shadow-sm" required /><InputError :message="form.errors.name" /></div>
                    <div><input v-model="form.email" type="email" placeholder="E-mail" class="w-full rounded-md border-gray-300 shadow-sm" required /><InputError :message="form.errors.email" /></div>
                    <div><input v-model="form.registration" placeholder="Matrícula" class="w-full rounded-md border-gray-300 shadow-sm" required /><InputError :message="form.errors.registration" /></div>
                    <select v-model="form.role" class="rounded-md border-gray-300 shadow-sm">
                        <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                    <PrimaryButton :disabled="form.processing" class="justify-center">Cadastrar</PrimaryButton>
                </form>

                <input v-model="q" placeholder="Buscar por nome ou matrícula…" class="w-full rounded-md border-gray-300 shadow-sm md:w-80" />

                <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-3">Nome</th>
                                <th class="px-4 py-3">Matrícula</th>
                                <th class="px-4 py-3">Unidade</th>
                                <th class="px-4 py-3">Perfil</th>
                                <th class="px-4 py-3">Ativo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="m in members.data" :key="m.id" :class="!m.active && 'opacity-50'">
                                <td class="px-4 py-3">{{ m.name }}<div class="text-xs text-gray-400">{{ m.email }}</div></td>
                                <td class="px-4 py-3 font-mono text-xs">{{ m.registration }}</td>
                                <td class="px-4 py-3">{{ m.unit || '—' }}</td>
                                <td class="px-4 py-3">
                                    <select
                                        v-if="can.membros.write && m.role !== 'admin'"
                                        :value="m.role"
                                        class="rounded-md border-gray-300 py-1 text-sm"
                                        @change="update(m, { role: $event.target.value })"
                                    >
                                        <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                                    </select>
                                    <span v-else>{{ m.role_label }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        :checked="m.active"
                                        :disabled="!can.membros.write || m.role === 'admin'"
                                        class="rounded border-gray-300 text-indigo-600"
                                        @change="update(m, { active: $event.target.checked })"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="l in members.links"
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
