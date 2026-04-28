<script setup>
import ClientTabs from '@/Components/ClientTabs.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    client: { type: Object, required: true },
    connections: { type: Array, required: true },
});

const destroyConnection = (connection) => {
    if (confirm(`Eliminar ${connection.name}?`)) {
        router.delete(`/admin/clients/${props.client.id}/connections/${connection.id}`);
    }
};
</script>

<template>
    <AdminLayout title="Conexiones">
        <ClientTabs :client="client" />
        <div class="page-head">
            <p>Conexiones activas para HubSpot y Trebel.</p>
            <Link class="primary" :href="`/admin/clients/${client.id}/connections/create`">Nueva conexión</Link>
        </div>
        <div class="stack">
            <article v-for="connection in connections" :key="connection.id" class="item">
                <div>
                    <strong>{{ connection.name }}</strong>
                    <p>{{ connection.platform_type }} · {{ connection.base_url || 'Sin base URL' }}</p>
                    <small>{{ connection.has_credentials ? 'Credenciales configuradas' : 'Sin credenciales' }}</small>
                </div>
                <div class="actions">
                    <Link :href="`/admin/clients/${client.id}/connections/${connection.id}/edit`">Editar</Link>
                    <button type="button" @click="destroyConnection(connection)">Eliminar</button>
                </div>
            </article>
        </div>
    </AdminLayout>
</template>

<style scoped>
.page-head, .item, .actions { display: flex; align-items: center; gap: 12px; }
.page-head { justify-content: space-between; margin-bottom: 16px; }
.stack { display: grid; gap: 12px; }
.item { justify-content: space-between; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; }
.item p, .item small { margin: 4px 0 0; color: #64748b; display: block; }
.actions a, .actions button, .primary { border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; background: #fff; color: #334155; text-decoration: none; }
.primary { background: #2563eb; border-color: #2563eb; color: #fff; }
.actions button { cursor: pointer; }
</style>
