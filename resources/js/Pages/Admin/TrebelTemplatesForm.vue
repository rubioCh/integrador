<script setup>
import ClientTabs from '@/Components/ClientTabs.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    mode: { type: String, required: true },
    client: { type: Object, required: true },
    template: { type: Object, default: null },
});

const isEdit = computed(() => props.mode === 'edit');
const form = useForm({
    name: props.template?.name ?? '',
    external_template_id: props.template?.external_template_id ?? '',
    payload_mapping_text: JSON.stringify(props.template?.payload_mapping ?? {
        template_id: '{{template.external_template_id}}',
        phone: '{{contact.phone}}',
    }, null, 2),
    active: props.template?.active ?? true,
});

const submit = () => {
    let payloadMapping = {};

    try {
        payloadMapping = JSON.parse(form.payload_mapping_text || '{}');
        form.clearErrors('payload_mapping_text');
    } catch (error) {
        form.setError('payload_mapping_text', 'JSON inválido');
        return;
    }

    form.transform(() => ({
        name: form.name,
        external_template_id: form.external_template_id,
        payload_mapping: payloadMapping,
        active: !!form.active,
    }));

    if (isEdit.value) {
        form.put(`/admin/clients/${props.client.id}/templates/${props.template.id}`);
        return;
    }

    form.post(`/admin/clients/${props.client.id}/templates`);
};
</script>

<template>
    <AdminLayout :title="isEdit ? 'Editar plantilla' : 'Nueva plantilla'">
        <ClientTabs :client="client" />
        <form class="form" @submit.prevent="submit">
            <label><span>Nombre</span><input v-model="form.name" type="text" required></label>
            <label><span>ID externo</span><input v-model="form.external_template_id" type="text" required></label>
            <label><span>Payload mapping JSON</span><textarea v-model="form.payload_mapping_text" rows="12"></textarea></label>
            <label class="checkbox"><input v-model="form.active" type="checkbox"><span>Activa</span></label>
            <div class="actions">
                <Link :href="`/admin/clients/${client.id}/templates`">Cancelar</Link>
                <button type="submit" :disabled="form.processing">Guardar</button>
            </div>
        </form>
    </AdminLayout>
</template>

<style scoped>
.form { display: grid; gap: 14px; max-width: 820px; }
label { display: grid; gap: 6px; }
span { color: #334155; font-size: 14px; }
input, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; }
.checkbox { display: flex; align-items: center; gap: 10px; }
.actions { display: flex; gap: 10px; }
.actions a, .actions button { border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 14px; background: #fff; color: #334155; text-decoration: none; }
.actions button { background: #2563eb; border-color: #2563eb; color: #fff; cursor: pointer; }
</style>
