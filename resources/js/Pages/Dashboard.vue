<template>
  <div class="dashboard">
    <div class="hero">
      <div>
        <p class="kicker">Integration Control Center</p>
        <h1>Dashboard</h1>
        <p class="subtitle">Estado general del sistema de integración y trazabilidad.</p>
      </div>
      <div class="user-box">
        <div class="user-name">{{ $page.props.auth?.user?.name ?? 'Usuario' }}</div>
        <button type="button" @click="logout">Cerrar sesión</button>
      </div>
    </div>

    <div class="cards">
      <div class="card">
        <span class="label">Eventos</span>
        <span class="value">{{ stats.events }}</span>
      </div>
      <div class="card">
        <span class="label">Eventos Activos</span>
        <span class="value">{{ stats.active_events }}</span>
      </div>
      <div class="card">
        <span class="label">Plataformas</span>
        <span class="value">{{ stats.platforms }}</span>
      </div>
      <div class="card">
        <span class="label">Records</span>
        <span class="value">{{ stats.records }}</span>
      </div>
    </div>

    <div class="shortcuts">
      <a href="/admin/events">Events</a>
      <a href="/admin/platforms">Platforms</a>
      <a href="/admin/properties">Properties</a>
      <a href="/admin/records">Records</a>
      <a href="/admin/users">Users</a>
      <a href="/admin/roles">Roles</a>
      <a href="/admin/categories">Categories</a>
      <a href="/admin/configs">Configs</a>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3';

defineProps({
  stats: {
    type: Object,
    required: true,
  },
});

const logout = () => {
  router.post('/logout');
};
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=Fraunces:opsz,wght@9..144,600&display=swap');

.dashboard {
  min-height: 100vh;
  padding: 48px 64px 80px;
  background: radial-gradient(circle at top left, #ffe7d6, transparent 55%),
    radial-gradient(circle at 30% 40%, #dfe8ff, transparent 55%),
    #f7f4ef;
  font-family: 'Space Grotesk', 'Segoe UI', sans-serif;
  color: #2f2a24;
}

.hero {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 36px;
}

.user-box {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-name {
  font-size: 14px;
  font-weight: 600;
  color: #4b5563;
}

.user-box button {
  border: 1px solid rgba(47, 42, 36, 0.2);
  background: rgba(255, 255, 255, 0.8);
  border-radius: 999px;
  padding: 8px 14px;
  font-size: 13px;
  cursor: pointer;
}

.kicker {
  text-transform: uppercase;
  letter-spacing: 0.2em;
  font-size: 12px;
  font-weight: 600;
  color: #6c5c50;
  margin-bottom: 8px;
}

h1 {
  font-family: 'Fraunces', serif;
  font-size: 40px;
  margin: 0 0 8px;
}

.subtitle {
  max-width: 420px;
  font-size: 16px;
  color: #6c5c50;
}

.cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
}

.shortcuts {
  margin-top: 24px;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.shortcuts a {
  text-decoration: none;
  border: 1px solid rgba(51, 44, 36, 0.2);
  background: rgba(255, 255, 255, 0.85);
  color: #2f2a24;
  border-radius: 999px;
  padding: 8px 12px;
  font-size: 13px;
}

.card {
  background: rgba(255, 255, 255, 0.8);
  border: 1px solid rgba(51, 44, 36, 0.1);
  border-radius: 16px;
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  backdrop-filter: blur(6px);
  box-shadow: 0 12px 30px rgba(47, 42, 36, 0.08);
}

.label {
  font-size: 14px;
  color: #6c5c50;
}

.value {
  font-size: 28px;
  font-weight: 700;
  color: #2f2a24;
}

@media (max-width: 640px) {
  .dashboard {
    padding: 32px 24px 60px;
  }

  h1 {
    font-size: 32px;
  }
}
</style>
