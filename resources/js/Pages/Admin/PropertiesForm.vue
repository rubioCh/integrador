<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import LightboxFormModal from '@/Components/LightboxFormModal.vue';
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mode: { type: String, required: true },
    property: { type: Object, default: null },
    platforms: { type: Array, default: () => [] },
});

const isEdit = computed(() => props.mode === 'edit');

const form = useForm({
    platform_id: props.property?.platform_id ?? '',
    name: props.property?.name ?? '',
    key: props.property?.key ?? '',
    type: props.property?.type ?? 'string',
    required: props.property?.required ?? false,
    active: props.property?.active ?? true,
    meta_text: JSON.stringify(props.property?.meta ?? {}, null, 2),
});

const parseMeta = () => {
    try {
        return JSON.parse(form.meta_text || '{}');
    } catch (error) {
        return null;
    }
};

const submit = () => {
    const meta = parseMeta();
    if (meta === null) {
        alert('`meta` debe ser JSON válido.');
        return;
    }

    form.transform((data) => ({
        platform_id: data.platform_id,
        name: data.name,
        key: data.key,
        type: data.type,
        required: !!data.required,
        active: !!data.active,
        meta,
    }));

    if (isEdit.value) {
        form.put(`/admin/properties/${props.property.id}`);
        return;
    }

    form.post('/admin/properties');
};
</script>

<template>
    <AdminLayout title="Properties">
        <LightboxFormModal :title="isEdit ? `Edit property #${props.property?.id}` : 'Create property'" close-href="/admin/properties">
            <form class="lightbox-form" @submit.prevent="submit">
                <div class="lightbox-grid">
                    <label class="lightbox-field">
                        <span class="lightbox-label">Platform</span>
                        <select v-model="form.platform_id" class="lightbox-select" required>
                            <option value="" disabled>Platform</option>
                            <option v-for="platform in props.platforms" :key="platform.id" :value="platform.id">{{ platform.name }} ({{ platform.type }})</option>
                        </select>
                    </label>
                    <label class="lightbox-field">
                        <span class="lightbox-label">Name</span>
                        <input v-model="form.name" class="lightbox-input" type="text" placeholder="Property Name" required>
                    </label>
                    <label class="lightbox-field">
                        <span class="lightbox-label">Key</span>
                        <input v-model="form.key" class="lightbox-input" type="text" placeholder="Property Key" required>
                    </label>
                    <label class="lightbox-field">
                        <span class="lightbox-label">Type</span>
                        <select v-model="form.type" class="lightbox-select" required>
                            <option value="string">string</option>
                            <option value="integer">integer</option>
                            <option value="float">float</option>
                            <option value="boolean">boolean</option>
                            <option value="datetime">datetime</option>
                            <option value="file">file</option>
                        </select>
                    </label>
                </div>

                <div class="lightbox-block lightbox-field">
                    <p class="lightbox-block-title">Meta JSON</p>
                    <p class="lightbox-help">Opcional. Usa este bloque para guardar metadata auxiliar de la propiedad.</p>
                    <textarea v-model="form.meta_text" class="lightbox-textarea" rows="5" placeholder='{"source":"hubspot"}' />
                </div>

                <div class="toggles">
                    <label class="lightbox-check-inline"><input v-model="form.required" type="checkbox"> Required</label>
                    <label class="lightbox-check-inline"><input v-model="form.active" type="checkbox"> Active</label>
                </div>

                <div class="lightbox-actions">
                    <button class="lightbox-submit" type="submit" :disabled="form.processing">{{ isEdit ? 'Save changes' : 'Create property' }}</button>
                    <Link class="lightbox-link" href="/admin/properties">Cancel</Link>
                </div>
            </form>
        </LightboxFormModal>
    </AdminLayout>
</template>
<style scoped>
.toggles{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
}
</style>
