<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PaginationNav from '@/Components/PaginationNav.vue';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    records: { type: Object, required: true },
    filters: { type: Object, required: true },
    event_types: { type: Array, default: () => [] },
    status_options: { type: Array, default: () => [] },
});

const form = reactive({
    status: props.filters.status ?? '',
    event_type: props.filters.event_type ?? '',
});

const applyFilters = () => {
    router.get('/admin/records', {
        status: form.status || undefined,
        event_type: form.event_type || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    form.status = '';
    form.event_type = '';
    applyFilters();
};

const prettyJson = (value) => {
    if (value === null || value === undefined) {
        return 'No details available';
    }

    if (typeof value === 'object' && Object.keys(value).length === 0) {
        return 'No details available';
    }

    return JSON.stringify(value, null, 2);
};

const hasObjectValue = (value) => value && typeof value === 'object' && !Array.isArray(value);

const joinList = (value) => {
    if (!Array.isArray(value) || value.length === 0) {
        return 'n/a';
    }

    return value.join(', ');
};
</script>

<template>
    <AdminLayout title="Records">
        <form class="filters" @submit.prevent="applyFilters">
            <select v-model="form.status">
                <option value="">Todos los estados</option>
                <option v-for="status in props.status_options" :key="status" :value="status">{{ status }}</option>
            </select>
            <select v-model="form.event_type">
                <option value="">Todos los tipos</option>
                <option v-for="eventType in props.event_types" :key="eventType" :value="eventType">{{ eventType }}</option>
            </select>
            <button type="submit">Filtrar</button>
            <button type="button" class="secondary" @click="resetFilters">Limpiar</button>
        </form>

        <div class="summary">
            <p>Total: {{ props.records.total }}</p>
            <p>Página: {{ props.records.current_page }} / {{ props.records.last_page }}</p>
        </div>

        <div class="list">
            <article v-for="record in props.records.data" :key="record.id" class="item">
                <header>
                    <div>
                        <h3>#{{ record.id }} · {{ record.event_type }}</h3>
                        <p>{{ record.message }}</p>
                    </div>
                    <span :class="['status', record.status]">{{ record.status }}</span>
                </header>

                <div class="meta">
                    <span>event_id: {{ record.event_id ?? 'n/a' }}</span>
                    <span>parent_record: {{ record.record_id ?? 'n/a' }}</span>
                    <span>children: {{ record.children_count }}</span>
                    <span>created: {{ record.created_at ?? 'n/a' }}</span>
                </div>

                <details>
                    <summary>Payload</summary>
                    <pre>{{ prettyJson(record.payload) }}</pre>
                </details>

                <details v-if="hasObjectValue(record.details) && record.details.output_payload">
                    <summary>Output Payload</summary>
                    <pre>{{ prettyJson(record.details.output_payload) }}</pre>
                </details>

                <details v-if="hasObjectValue(record.details) && record.details.hubspot_enrichment">
                    <summary>HubSpot Enrichment</summary>
                    <div class="enrichment-meta">
                        <p>
                            Mapped properties requested:
                            <strong>{{ joinList(record.details.hubspot_enrichment.requested_properties) }}</strong>
                        </p>
                        <p>
                            Properties fetched:
                            <strong>{{ joinList(record.details.hubspot_enrichment.fetched_properties) }}</strong>
                        </p>
                    </div>
                    <pre>{{ prettyJson(record.details.hubspot_enrichment) }}</pre>
                </details>

                <details>
                    <summary>Details</summary>
                    <pre>{{ prettyJson(record.details) }}</pre>
                </details>
            </article>
        </div>
        <PaginationNav :links="props.records.links ?? []" />
    </AdminLayout>
</template>

<style scoped>
.filters{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
.filters select,.filters button{height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;background:#fff}
.filters button{cursor:pointer;background:#1d4ed8;color:#fff;border:0}
.filters .secondary{border:1px solid #cbd5e1;background:#f8fafc;color:#334155}
.summary{display:flex;gap:18px;margin:8px 0;color:#475569;font-size:13px}
.list{display:grid;gap:10px}
.item{border:1px solid #dbe4ef;border-radius:12px;padding:12px;background:#fff}
header{display:flex;justify-content:space-between;gap:12px}
h3{margin:0 0 4px;font-size:15px;color:#1f2937}
header p{margin:0;color:#475569;font-size:13px}
.status{border-radius:999px;padding:4px 8px;font-size:12px;font-weight:600;height:max-content}
.status.init{background:#e2e8f0;color:#334155}
.status.processing{background:#dbeafe;color:#1d4ed8}
.status.success{background:#dcfce7;color:#166534}
.status.warning{background:#fef3c7;color:#78350f}
.status.error{background:#fee2e2;color:#991b1b}
.meta{display:flex;flex-wrap:wrap;gap:8px 12px;margin:8px 0;color:#475569;font-size:12px}
details{margin-top:6px}
summary{cursor:pointer;color:#334155;font-size:13px}
pre{margin:6px 0 0;background:#0f172a;color:#e2e8f0;padding:10px;border-radius:8px;font-size:12px;white-space:pre-wrap}
.enrichment-meta{margin-top:6px;display:grid;gap:4px}
.enrichment-meta p{margin:0;color:#475569;font-size:12px}
.enrichment-meta strong{color:#0f172a}
</style>
