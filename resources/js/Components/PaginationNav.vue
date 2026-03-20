<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <nav v-if="links.length > 3" class="pagination" aria-label="Pagination">
        <Link
            v-for="(link, index) in links"
            :key="index"
            :href="link.url || '#'"
            :class="['page-link', { active: link.active, disabled: !link.url }]"
            preserve-scroll
            preserve-state
            v-html="link.label"
        />
    </nav>
</template>

<style scoped>
.pagination{
    margin-top:12px;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}

.page-link{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:36px;
    height:36px;
    border:1px solid #cbd5e1;
    border-radius:8px;
    background:#fff;
    color:#334155;
    font-size:13px;
    text-decoration:none;
    padding:0 10px;
}

.page-link.active{
    border-color:#1d4ed8;
    background:#1d4ed8;
    color:#fff;
}

.page-link.disabled{
    opacity:.45;
    pointer-events:none;
}
</style>
