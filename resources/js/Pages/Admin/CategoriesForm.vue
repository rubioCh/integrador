<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import LightboxFormModal from '@/Components/LightboxFormModal.vue';
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mode: { type: String, required: true },
    category: { type: Object, default: null },
    properties: { type: Array, default: () => [] },
});

const isEdit = computed(() => props.mode === 'edit');

const form = useForm({
    name: props.category?.name ?? '',
    slug: props.category?.slug ?? '',
    description: props.category?.description ?? '',
    active: props.category?.active ?? true,
    property_ids: props.category?.property_ids ?? [],
});

const submit = () => {
    if (isEdit.value) {
        form.put(`/admin/categories/${props.category.id}`);
        return;
    }

    form.post('/admin/categories');
};
</script>

<template>
    <AdminLayout title="Categories">
        <LightboxFormModal :title="isEdit ? `Edit category #${props.category?.id}` : 'Create category'" close-href="/admin/categories">
            <form class="lightbox-form" @submit.prevent="submit">
                <div class="lightbox-grid">
                    <label class="lightbox-field">
                        <span class="lightbox-label">Name</span>
                        <input v-model="form.name" class="lightbox-input" type="text" placeholder="Category Name" required>
                    </label>
                    <label class="lightbox-field">
                        <span class="lightbox-label">Slug</span>
                        <input v-model="form.slug" class="lightbox-input" type="text" placeholder="Category Slug">
                    </label>
                    <label class="lightbox-field">
                        <span class="lightbox-label">Description</span>
                        <input v-model="form.description" class="lightbox-input" type="text" placeholder="Description">
                    </label>
                </div>

                <div class="lightbox-block">
                    <p class="lightbox-block-title">Properties</p>
                    <div class="lightbox-grid">
                        <label v-for="property in props.properties" :key="property.id" class="lightbox-check">
                            <input v-model="form.property_ids" type="checkbox" :value="property.id">
                            {{ property.name }} ({{ property.key }})
                        </label>
                    </div>
                </div>

                <label class="lightbox-check-inline"><input v-model="form.active" type="checkbox"> Active</label>

                <div class="lightbox-actions">
                    <button class="lightbox-submit" type="submit" :disabled="form.processing">{{ isEdit ? 'Save changes' : 'Create category' }}</button>
                    <Link class="lightbox-link" href="/admin/categories">Cancel</Link>
                </div>
            </form>
        </LightboxFormModal>
    </AdminLayout>
</template>
