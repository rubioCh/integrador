<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    properties: { type: Object, required: true },
});

const remove = (id) => router.delete(`/admin/properties/${id}`, { preserveScroll: true });
</script>

<template>
    <AdminLayout title="Properties">
        <div v-if="$page.props.flash?.success" class="flash success">{{ $page.props.flash.success }}</div>
        <div v-if="$page.props.flash?.error" class="flash error">{{ $page.props.flash.error }}</div>

        <div class="toolbar">
            <Link class="primary" href="/admin/properties/create">Crear propiedad</Link>
        </div>

        <div class="summary">
            <p>Total: {{ props.properties.total }}</p>
            <p>Página: {{ props.properties.current_page }} / {{ props.properties.last_page }}</p>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Key</th>
                        <th>Type</th>
                        <th>Plataforma</th>
                        <th>Requerida</th>
                        <th>Activa</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="property in props.properties.data" :key="property.id">
                        <td>{{ property.id }}</td>
                        <td>{{ property.name }}</td>
                        <td>{{ property.key }}</td>
                        <td>{{ property.type }}</td>
                        <td>{{ property.platform?.name ?? 'n/a' }}</td>
                        <td>{{ property.required ? 'Sí' : 'No' }}</td>
                        <td>{{ property.active ? 'Sí' : 'No' }}</td>
                        <td>
                            <Link class="secondary compact" :href="`/admin/properties/${property.id}/edit`">Editar</Link>
                            <button type="button" class="danger" @click="remove(property.id)">Eliminar</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <PaginationNav :links="props.properties.links ?? []" />
    </AdminLayout>
</template>

<style scoped>
.toolbar{display:flex;justify-content:flex-end;margin-bottom:10px}
.summary{display:flex;gap:18px;margin:8px 0;color:#475569;font-size:13px}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th,td{border-bottom:1px solid #e2e8f0;padding:10px 8px;text-align:left;font-size:13px}
th{font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#475569}
.primary,.secondary{border:1px solid #1d4ed8;background:#1d4ed8;color:#fff;border-radius:8px;padding:8px 12px;cursor:pointer;text-decoration:none}
.secondary{border-color:#cbd5e1;background:#f8fafc;color:#334155}
.compact{margin-right:6px;padding:6px 8px}
.danger{border:1px solid #fecaca;background:#fee2e2;color:#991b1b;border-radius:8px;padding:6px 8px;cursor:pointer}
.flash{border-radius:10px;padding:8px 12px;margin-bottom:10px;font-size:13px}
.flash.success{background:#dcfce7;color:#166534}
.flash.error{background:#fee2e2;color:#991b1b}
</style>
