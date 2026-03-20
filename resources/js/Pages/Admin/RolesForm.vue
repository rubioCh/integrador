<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import LightboxFormModal from '@/Components/LightboxFormModal.vue';
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mode: { type: String, required: true },
    role: { type: Object, default: null },
    permissions: { type: Array, default: () => [] },
});

const isEdit = computed(() => props.mode === 'edit');

const form = useForm({
    name: props.role?.name ?? '',
    slug: props.role?.slug ?? '',
    description: props.role?.description ?? '',
    permission_ids: props.role?.permission_ids ?? [],
});

const submit = () => {
    if (isEdit.value) {
        form.put(`/admin/roles/${props.role.id}`);
        return;
    }

    form.post('/admin/roles');
};
</script>

<template>
    <AdminLayout title="Roles">
        <LightboxFormModal :title="isEdit ? `Edit role #${props.role?.id}` : 'Create role'" close-href="/admin/roles">
            <form class="lightbox-form" @submit.prevent="submit">
                <div class="lightbox-grid">
                    <label class="lightbox-field">
                        <span class="lightbox-label">Name</span>
                        <input v-model="form.name" class="lightbox-input" type="text" placeholder="Role Name" required>
                    </label>
                    <label class="lightbox-field">
                        <span class="lightbox-label">Slug</span>
                        <input v-model="form.slug" class="lightbox-input" type="text" placeholder="Role Slug">
                    </label>
                    <label class="lightbox-field">
                        <span class="lightbox-label">Description</span>
                        <input v-model="form.description" class="lightbox-input" type="text" placeholder="Description">
                    </label>
                </div>

                <div class="lightbox-block">
                    <p class="lightbox-block-title">Permissions</p>
                    <div class="lightbox-grid">
                        <label v-for="permission in props.permissions" :key="permission.id" class="lightbox-check">
                            <input v-model="form.permission_ids" type="checkbox" :value="permission.id">
                            {{ permission.slug }}
                        </label>
                    </div>
                </div>

                <div class="lightbox-actions">
                    <button class="lightbox-submit" type="submit" :disabled="form.processing">{{ isEdit ? 'Save changes' : 'Create role' }}</button>
                    <Link class="lightbox-link" href="/admin/roles">Cancel</Link>
                </div>
            </form>
        </LightboxFormModal>
    </AdminLayout>
</template>
