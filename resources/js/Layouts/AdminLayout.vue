<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const links = [
    { href: '/dashboard', label: 'Dashboard' },
    { href: '/admin/events', label: 'Events' },
    { href: '/admin/platforms', label: 'Platforms' },
    { href: '/admin/properties', label: 'Properties' },
    { href: '/admin/records', label: 'Records' },
    { href: '/admin/users', label: 'Users' },
    { href: '/admin/roles', label: 'Roles' },
    { href: '/admin/categories', label: 'Categories' },
    { href: '/admin/configs', label: 'Configs' },
];

const logout = () => router.post('/logout');
</script>

<template>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <p>Integrator</p>
                <strong>Admin Panel</strong>
            </div>

            <nav class="nav">
                <Link v-for="link in links" :key="link.href" :href="link.href">
                    {{ link.label }}
                </Link>
            </nav>
        </aside>

        <main class="content">
            <header class="header">
                <div>
                    <h1>{{ title }}</h1>
                    <p>Gestión administrativa del sistema de integración</p>
                </div>

                <div class="actions">
                    <span>
                        {{ user?.name ?? user?.email ?? 'Usuario' }}
                    </span>
                    <button type="button" @click="logout">Cerrar sesión</button>
                </div>
            </header>

            <section class="panel">
                <slot />
            </section>
        </main>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Barlow+Condensed:wght@600&display=swap');

.shell {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 260px 1fr;
    font-family: 'DM Sans', sans-serif;
    background:
        radial-gradient(circle at 10% 10%, rgba(247, 201, 120, 0.38), transparent 35%),
        radial-gradient(circle at 90% 20%, rgba(91, 173, 255, 0.34), transparent 34%),
        #f6f8fb;
}

.sidebar {
    background: linear-gradient(180deg, #1d2a35 0%, #0f1720 100%);
    color: #d1dae3;
    padding: 26px 20px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.brand p {
    margin: 0;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.14em;
    color: #8ba1b5;
}

.brand strong {
    font-family: 'Barlow Condensed', sans-serif;
    letter-spacing: 0.04em;
    font-size: 24px;
}

.nav {
    display: grid;
    gap: 8px;
}

.nav a {
    color: #d6e0ea;
    text-decoration: none;
    border: 1px solid rgba(214, 224, 234, 0.2);
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 14px;
}

.nav a:hover {
    background: rgba(255, 255, 255, 0.12);
}

.content {
    padding: 26px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.header h1 {
    margin: 0;
    font-size: 27px;
}

.header p {
    margin: 4px 0 0;
    color: #5a6675;
}

.actions {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #334155;
}

.actions button {
    border: 0;
    border-radius: 999px;
    background: #1d4ed8;
    color: #fff;
    padding: 9px 13px;
    cursor: pointer;
    font-size: 13px;
}

.panel {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(148, 163, 184, 0.26);
    border-radius: 16px;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.1);
    padding: 18px;
}

@media (max-width: 900px) {
    .shell {
        grid-template-columns: 1fr;
    }

    .sidebar {
        gap: 14px;
    }

    .content {
        padding: 16px;
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
}
</style>
