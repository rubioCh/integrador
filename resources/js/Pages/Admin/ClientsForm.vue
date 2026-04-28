<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    mode: { type: String, required: true },
    client: { type: Object, default: null },
});

const isEdit = computed(() => props.mode === 'edit');
const form = useForm({
    name: props.client?.name ?? '',
    slug: props.client?.slug ?? '',
    description: props.client?.description ?? '',
    active: props.client?.active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(`/admin/clients/${props.client.id}`);
        return;
    }

    form.post('/admin/clients');
};
</script>

<template>
    <AdminLayout :title="isEdit ? 'Editar cliente' : 'Nuevo cliente'">
        <form class="form" @submit.prevent="submit">
            <label><span>Nombre</span><input v-model="form.name" type="text" required></label>
            <label><span>Slug</span><input v-model="form.slug" type="text"></label>
            <label><span>Descripción</span><textarea v-model="form.description" rows="4"></textarea></label>
            <label class="checkbox"><input v-model="form.active" type="checkbox"><span>Activo</span></label>
            <div class="actions">
                <Link href="/admin/clients">Cancelar</Link>
                <button type="submit" :disabled="form.processing">Guardar</button>
            </div>
        </form>
    </AdminLayout>
</template>

<style scoped>
.form { display: grid; gap: 14px; max-width: 760px; }
label { display: grid; gap: 6px; }
span { color: #334155; font-size: 14px; }
input, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; }
.checkbox { display: flex; align-items: center; gap: 10px; }
.actions { display: flex; gap: 10px; }
.actions a, .actions button { border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 14px; background: #fff; color: #334155; text-decoration: none; }
.actions button { background: #2563eb; border-color: #2563eb; color: #fff; cursor: pointer; }
</style>
