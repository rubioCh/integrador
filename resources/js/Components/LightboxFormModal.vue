<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    closeHref: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="overlay">
        <section class="modal">
            <header class="head">
                <h2>{{ props.title }}</h2>
                <Link class="close" :href="props.closeHref" aria-label="Cerrar">×</Link>
            </header>
            <div class="body">
                <slot />
            </div>
        </section>
    </div>
</template>

<style scoped>
.overlay{
    position:fixed;
    inset:0;
    z-index:60;
    background:
        radial-gradient(circle at 15% 12%, rgba(247, 201, 120, 0.24), transparent 35%),
        radial-gradient(circle at 88% 18%, rgba(91, 173, 255, 0.2), transparent 36%),
        rgba(246, 248, 251, 0.82);
    backdrop-filter: blur(4px);
    display:flex;
    align-items:flex-start;
    justify-content:center;
    padding:28px 12px;
    overflow:auto;
}

.modal{
    width:min(900px, 100%);
    background:rgba(255, 255, 255, 0.98);
    border:1px solid rgba(148, 163, 184, 0.26);
    border-radius:12px;
    box-shadow:0 24px 55px rgba(15, 23, 42, 0.16);
    color:#0f172a;
}

.head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:16px 20px;
    border-bottom:1px solid rgba(148, 163, 184, 0.22);
}

.head h2{
    margin:0;
    font-size:28px;
    font-family:'Barlow Condensed', sans-serif;
    letter-spacing:0.02em;
}

.close{
    color:#64748b;
    text-decoration:none;
    font-size:30px;
    line-height:1;
}

.close:hover{
    color:#0f172a;
}

.body{
    padding:18px 20px 20px;
}

:deep(.lightbox-form){
    display:grid;
    gap:14px;
}

:deep(.lightbox-grid){
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
    gap:14px;
}

:deep(.lightbox-field){
    display:grid;
    gap:6px;
}

:deep(.lightbox-field.full){
    grid-column:1 / -1;
}

:deep(.lightbox-label){
    margin:0;
    font-size:13px;
    color:#334155;
    font-weight:600;
}

:deep(.lightbox-help){
    margin:0;
    font-size:12px;
    color:#64748b;
    line-height:1.45;
}

:deep(.lightbox-input),
:deep(.lightbox-select),
:deep(.lightbox-textarea){
    width:100%;
    border:1px solid #cbd5e1;
    border-radius:10px;
    background:#ffffff;
    color:#0f172a;
    font-size:14px;
    padding:10px 12px;
}

:deep(.lightbox-input::placeholder),
:deep(.lightbox-textarea::placeholder){
    color:#64748b;
}

:deep(.lightbox-textarea){
    resize:vertical;
    min-height:90px;
}

:deep(.lightbox-check),
:deep(.lightbox-check-inline){
    display:flex;
    gap:8px;
    align-items:center;
    font-size:13px;
    color:#334155;
}

:deep(.lightbox-check-inline){
    display:inline-flex;
}

:deep(.lightbox-actions){
    display:flex;
    justify-content:flex-start;
    gap:10px;
    border-top:1px solid rgba(148, 163, 184, 0.22);
    padding-top:14px;
}

:deep(.lightbox-submit){
    border:0;
    border-radius:10px;
    background:#2563eb;
    color:#f8fafc;
    font-weight:600;
    padding:10px 16px;
    cursor:pointer;
}

:deep(.lightbox-link){
    border:1px solid #cbd5e1;
    border-radius:10px;
    background:#f8fafc;
    color:#334155;
    text-decoration:none;
    padding:9px 12px;
}

:deep(.lightbox-block){
    border:1px solid rgba(148, 163, 184, 0.24);
    border-radius:10px;
    background:rgba(248, 250, 252, 0.85);
    padding:10px;
}

:deep(.lightbox-block-title){
    margin:0 0 10px;
    font-size:13px;
    color:#334155;
    font-weight:600;
}

@media (max-width: 780px){
    .head h2{font-size:25px}
    .body{padding:14px}
}
</style>
