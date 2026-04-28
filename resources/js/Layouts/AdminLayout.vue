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
const currentUrl = computed(() => page.url ?? '');
const clients = computed(() => page.props.clients ?? []);

const links = [
    { href: '/dashboard', label: 'Dashboard', icon: 'M4 13h6V4H4v9zM14 20h6V4h-6v16zM4 20h6v-3H4v3z' },
    { href: '/admin/clients', label: 'Clients', icon: 'M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3zM4 7.5l8 4.5 8-4.5M12 12v9' },
    { href: '/admin/records', label: 'Records', icon: 'M6 3h12v18H6zM9 7h6M9 11h6M9 15h4' },
    { href: '/admin/users', label: 'Users', icon: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75' },
    { href: '/admin/roles', label: 'Roles', icon: 'M12 2l7 4v6c0 5-3 8-7 10-4-2-7-5-7-10V6l7-4zM9 12l2 2 4-5' },
];

const logout = () => router.post('/logout');
const isActive = (href) => currentUrl.value === href || currentUrl.value.startsWith(`${href}/`);
</script>

<template>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <p>Integrator</p>
                <strong>Admin Panel</strong>
            </div>

            <nav class="nav">
                <Link
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    :class="{ active: isActive(link.href) }"
                    :aria-current="isActive(link.href) ? 'page' : undefined"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path :d="link.icon" />
                    </svg>
                    {{ link.label }}
                </Link>
            </nav>

            <div v-if="clients.length" class="client-rail">
                <p>Clientes</p>
                <Link
                    v-for="client in clients"
                    :key="client.id"
                    :href="`/admin/clients/${client.id}/connections`"
                    class="client-link"
                >
                    {{ client.name }}
                </Link>
            </div>
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

.nav a,
.client-link {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #d6e0ea;
    text-decoration: none;
    border: 1px solid rgba(214, 224, 234, 0.2);
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 14px;
    transition: background 0.16s ease, border-color 0.16s ease, transform 0.16s ease;
}

.nav svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.9;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.nav a:hover,
.client-link:hover {
    background: rgba(255, 255, 255, 0.12);
    transform: translateX(2px);
}

.nav a.active {
    border-color: rgba(255, 255, 255, 0.38);
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
}

.client-rail {
    display: grid;
    gap: 8px;
}

.client-rail p {
    margin: 0;
    color: #8ba1b5;
    font-size: 12px;
    text-transform: uppercase;
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
    border-radius: 8px;
    background: #1d4ed8;
    color: #fff;
    padding: 9px 13px;
    cursor: pointer;
    font-size: 13px;
}

.panel {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(148, 163, 184, 0.26);
    border-radius: 8px;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.1);
    padding: 18px;
}

.panel:has(.dashboard-grid) {
    background: transparent;
    border: 0;
    box-shadow: none;
    padding: 0;
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
