<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';

defineProps({
    clients: { type: Object, required: true },
});

const destroyClient = (client) => {
    if (confirm(`Eliminar ${client.name}?`)) {
        router.delete(`/admin/clients/${client.id}`);
    }
};
</script>

<template>
    <AdminLayout title="Clients">
        <div class="page-head">
            <p>Clientes operando el flujo Lite HubSpot -> Trebel.</p>
            <Link class="primary" href="/admin/clients/create">Nuevo cliente</Link>
        </div>

        <div class="table">
            <div class="row head">
                <span>Cliente</span>
                <span>Conexiones</span>
                <span>Plantillas</span>
                <span>Reglas</span>
                <span>Estado</span>
                <span>Acciones</span>
            </div>

            <div v-for="client in clients.data" :key="client.id" class="row">
                <div>
                    <strong>{{ client.name }}</strong>
                    <p>{{ client.slug }}</p>
                </div>
                <span>{{ client.platform_connections_count }}</span>
                <span>{{ client.trebel_templates_count }}</span>
                <span>{{ client.message_rules_count }}</span>
                <span :class="client.active ? 'ok' : 'off'">{{ client.active ? 'Activo' : 'Inactivo' }}</span>
                <div class="actions">
                    <Link :href="`/admin/clients/${client.id}/connections`">Abrir</Link>
                    <Link :href="`/admin/clients/${client.id}/edit`">Editar</Link>
                    <button type="button" @click="destroyClient(client)">Eliminar</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.page-head,
.row,
.actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-head {
    justify-content: space-between;
    margin-bottom: 16px;
}

.table {
    display: grid;
    gap: 10px;
}

.row {
    display: grid;
    grid-template-columns: minmax(180px, 2fr) repeat(3, minmax(80px, 1fr)) minmax(90px, 1fr) minmax(180px, 1.4fr);
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px;
}

.row.head {
    background: #f8fafc;
    color: #475569;
    font-size: 13px;
}

.row p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 13px;
}

.actions {
    flex-wrap: wrap;
}

.actions a,
.actions button,
.primary {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 8px 12px;
    background: #fff;
    color: #334155;
    text-decoration: none;
}

.primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.actions button {
    cursor: pointer;
}

.ok { color: #047857; }
.off { color: #b45309; }
</style>
