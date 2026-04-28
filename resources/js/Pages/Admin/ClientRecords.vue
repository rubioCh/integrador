<script setup>
import ClientTabs from '@/Components/ClientTabs.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    client: { type: Object, required: true },
    records: { type: Object, required: true },
});
</script>

<template>
    <AdminLayout title="Records del cliente">
        <ClientTabs :client="client" />
        <div class="stack">
            <article v-for="record in records.data" :key="record.id" class="item">
                <header>
                    <strong>#{{ record.id }} · {{ record.event_type }}</strong>
                    <span :class="record.status">{{ record.status }}</span>
                </header>
                <p>{{ record.message }}</p>
                <pre>{{ JSON.stringify(record.details, null, 2) }}</pre>
            </article>
        </div>
    </AdminLayout>
</template>

<style scoped>
.stack { display: grid; gap: 12px; }
.item { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; }
header { display: flex; justify-content: space-between; gap: 12px; }
p { color: #475569; }
pre { margin: 0; padding: 12px; border-radius: 8px; background: #0f172a; color: #e2e8f0; overflow: auto; font-size: 12px; }
.success { color: #047857; }
.error { color: #b91c1c; }
.warning { color: #b45309; }
.processing { color: #2563eb; }
</style>
