<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    stats: {
        type: Object,
        required: true,
    },
    recent_clients: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AdminLayout title="Dashboard">
        <div class="dashboard-grid">
            <section class="hero-card">
                <p class="kicker">Lite HubSpot -> Treble</p>
                <h2>Operación multi-cliente</h2>
                <p>
                    Configura reglas por cliente, escucha cambios en HubSpot y despacha plantillas a Treble con trazabilidad completa.
                </p>
            </section>

            <section class="cards" aria-label="Resumen del sistema">
                <article class="card">
                    <span class="label">Clientes</span>
                    <strong>{{ stats.clients }}</strong>
                </article>
                <article class="card">
                    <span class="label">Clientes activos</span>
                    <strong>{{ stats.active_clients }}</strong>
                </article>
                <article class="card">
                    <span class="label">Conexiones</span>
                    <strong>{{ stats.connections }}</strong>
                </article>
                <article class="card">
                    <span class="label">Plantillas</span>
                    <strong>{{ stats.templates }}</strong>
                </article>
                <article class="card">
                    <span class="label">Reglas</span>
                    <strong>{{ stats.rules }}</strong>
                </article>
                <article class="card">
                    <span class="label">Records</span>
                    <strong>{{ stats.records }}</strong>
                </article>
            </section>

            <section class="quick-panel">
                <div>
                    <p class="kicker">Accesos rápidos</p>
                    <h3>Configuración central</h3>
                </div>

                <div class="shortcuts">
                    <a href="/admin/clients">Clientes</a>
                    <a href="/admin/records">Records</a>
                    <a href="/admin/users">Usuarios</a>
                    <a href="/admin/roles">Roles</a>
                </div>
            </section>

            <section class="client-list">
                <article v-for="client in recent_clients" :key="client.id" class="client-item">
                    <div>
                        <strong>{{ client.name }}</strong>
                        <p>{{ client.slug }}</p>
                    </div>
                    <div class="meta">
                        <span>{{ client.platform_connections_count }} conexiones</span>
                        <span>{{ client.treble_templates_count }} plantillas</span>
                        <span>{{ client.message_rules_count }} reglas</span>
                    </div>
                    <a :href="`/admin/clients/${client.id}/connections`">Abrir</a>
                </article>
            </section>
        </div>
    </AdminLayout>
</template>

<style scoped>
.dashboard-grid {
    display: grid;
    gap: 18px;
}

.hero-card {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(51, 65, 85, 0.12);
    border-radius: 8px;
    padding: 26px;
    background:
        radial-gradient(circle at 92% 18%, rgba(251, 191, 36, 0.42), transparent 28%),
        linear-gradient(135deg, #fff 0%, #eef6ff 100%);
    box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
}

.kicker {
    margin: 0 0 8px;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
}

.hero-card h2,
.quick-panel h3 {
    margin: 0;
    color: #172033;
}

.hero-card h2 {
    font-size: 30px;
}

.hero-card p:last-child {
    max-width: 560px;
    margin: 10px 0 0;
    color: #526174;
}

.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
}

.card {
    border: 1px solid rgba(148, 163, 184, 0.3);
    border-radius: 8px;
    padding: 18px;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 12px 26px rgba(15, 23, 42, 0.07);
}

.label {
    display: block;
    margin-bottom: 8px;
    color: #64748b;
    font-size: 13px;
}

.card strong {
    color: #0f172a;
    font-size: 30px;
}

.quick-panel {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: center;
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 8px;
    padding: 18px;
    background: rgba(255, 255, 255, 0.88);
}

.shortcuts {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

.shortcuts a {
    border: 1px solid #d6dee8;
    border-radius: 8px;
    padding: 8px 12px;
    background: #fff;
    color: #334155;
    font-size: 13px;
    text-decoration: none;
}

.client-list {
    display: grid;
    gap: 12px;
}

.client-item {
    display: grid;
    grid-template-columns: minmax(160px, 2fr) minmax(220px, 2fr) auto;
    gap: 12px;
    align-items: center;
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 8px;
    padding: 16px;
    background: rgba(255, 255, 255, 0.88);
}

.client-item p {
    margin: 4px 0 0;
    color: #64748b;
}

.meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    color: #475569;
    font-size: 13px;
}

.client-item a {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 8px 12px;
    color: #334155;
    text-decoration: none;
    background: #fff;
}

.shortcuts a:hover {
    border-color: #9db1c9;
    background: #f8fbff;
}

@media (max-width: 720px) {
    .quick-panel {
        align-items: flex-start;
        flex-direction: column;
    }

    .shortcuts {
        justify-content: flex-start;
    }
}
</style>
