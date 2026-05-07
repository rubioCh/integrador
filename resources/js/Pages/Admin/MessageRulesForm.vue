<script setup>
import ClientTabs from '@/Components/ClientTabs.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    mode: { type: String, required: true },
    client: { type: Object, required: true },
    rule: { type: Object, default: null },
    templates: { type: Array, required: true },
});

const isEdit = computed(() => props.mode === 'edit');
const form = useForm({
    treble_template_id: props.rule?.treble_template_id ?? props.templates[0]?.id ?? '',
    name: props.rule?.name ?? '',
    priority: props.rule?.priority ?? 100,
    trigger_property: props.rule?.trigger_property ?? 'plantilla_de_whatsapp',
    trigger_value: props.rule?.trigger_value ?? '',
    conditions_text: JSON.stringify(props.rule?.conditions ?? {
        campus_de_interes: '',
        nivel_escolar_de_interes: '',
    }, null, 2),
    active: props.rule?.active ?? true,
});

const submit = () => {
    let conditions = {};

    try {
        conditions = JSON.parse(form.conditions_text || '{}');
        form.clearErrors('conditions_text');
    } catch (error) {
        form.setError('conditions_text', 'JSON inválido');
        return;
    }

    form.transform(() => ({
        treble_template_id: Number(form.treble_template_id),
        name: form.name,
        priority: Number(form.priority),
        trigger_property: form.trigger_property,
        trigger_value: form.trigger_value || null,
        conditions,
        active: !!form.active,
    }));

    if (isEdit.value) {
        form.put(`/admin/clients/${props.client.id}/rules/${props.rule.id}`);
        return;
    }

    form.post(`/admin/clients/${props.client.id}/rules`);
};
</script>

<template>
    <AdminLayout :title="isEdit ? 'Editar regla' : 'Nueva regla'">
        <ClientTabs :client="client" />
        <form class="form" @submit.prevent="submit">
            <div class="grid">
                <label><span>Nombre</span><input v-model="form.name" type="text" required></label>
                <label><span>Plantilla Treble</span><select v-model="form.treble_template_id"><option v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }} ({{ template.external_template_id }})</option></select></label>
                <label><span>Prioridad</span><input v-model="form.priority" type="number" min="0"></label>
                <label><span>Trigger property</span><input v-model="form.trigger_property" type="text" required></label>
                <label><span>Trigger value</span><input v-model="form.trigger_value" type="text"></label>
                <label class="checkbox"><input v-model="form.active" type="checkbox"><span>Activa</span></label>
            </div>
            <label><span>Conditions JSON</span><textarea v-model="form.conditions_text" rows="10" placeholder='{"campus_de_interes":"CDMX","nivel_escolar_de_interes":"Primaria"}'></textarea></label>
            <div class="actions">
                <Link :href="`/admin/clients/${client.id}/rules`">Cancelar</Link>
                <button type="submit" :disabled="form.processing">Guardar</button>
            </div>
        </form>
    </AdminLayout>
</template>

<style scoped>
.form { display: grid; gap: 14px; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
label { display: grid; gap: 6px; }
span { color: #334155; font-size: 14px; }
input, select, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; }
.checkbox { display: flex; align-items: center; gap: 10px; }
.actions { display: flex; gap: 10px; }
.actions a, .actions button { border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 14px; background: #fff; color: #334155; text-decoration: none; }
.actions button { background: #2563eb; border-color: #2563eb; color: #fff; cursor: pointer; }
</style>
