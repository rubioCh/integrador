<script setup>
import { reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const form = reactive({
    login: '',
    password: '',
    remember: false,
});

const submit = () => {
    router.post('/login', form, {
        preserveScroll: true,
    });
};

const firstError = (field) => page.props.errors?.[field] ?? null;
</script>

<template>
    <div class="login-page">
        <div class="panel">
            <p class="eyebrow">Integrador v2</p>
            <h1>Iniciar sesión</h1>
            <p class="subtitle">Acceso al panel administrativo de integraciones.</p>

            <form class="form" @submit.prevent="submit">
                <label>
                    <span>Email o usuario</span>
                    <input v-model="form.login" type="text" autocomplete="username" required>
                    <small v-if="firstError('login')" class="error">{{ firstError('login') }}</small>
                </label>

                <label>
                    <span>Contraseña</span>
                    <input v-model="form.password" type="password" autocomplete="current-password" required>
                    <small v-if="firstError('password')" class="error">{{ firstError('password') }}</small>
                </label>

                <label class="remember">
                    <input v-model="form.remember" type="checkbox">
                    <span>Mantener sesión</span>
                </label>

                <button type="submit">Entrar</button>
            </form>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;700&family=Sora:wght@500;700&display=swap');

.login-page {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 24px;
    background:
        radial-gradient(circle at 10% 10%, rgba(247, 208, 131, 0.55), transparent 42%),
        radial-gradient(circle at 92% 18%, rgba(86, 178, 177, 0.4), transparent 35%),
        linear-gradient(130deg, #f4f7fb 0%, #e8eef6 100%);
}

.panel {
    width: min(460px, 100%);
    padding: 34px 30px;
    border-radius: 18px;
    border: 1px solid rgba(16, 24, 40, 0.08);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 26px 60px rgba(16, 24, 40, 0.14);
    backdrop-filter: blur(6px);
    font-family: 'IBM Plex Sans', sans-serif;
    color: #1f2937;
}

.eyebrow {
    margin: 0 0 6px;
    font-size: 11px;
    letter-spacing: 0.14em;
    font-weight: 700;
    text-transform: uppercase;
    color: #0f766e;
}

h1 {
    margin: 0;
    font-family: 'Sora', sans-serif;
    font-size: 29px;
}

.subtitle {
    margin: 9px 0 0;
    color: #4b5563;
    font-size: 14px;
}

.form {
    margin-top: 22px;
    display: grid;
    gap: 14px;
}

label {
    display: grid;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
}

input[type='text'],
input[type='password'] {
    height: 44px;
    border: 1px solid #cdd5df;
    border-radius: 10px;
    padding: 0 12px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

input[type='text']:focus,
input[type='password']:focus {
    border-color: #0f766e;
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
}

.remember {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 500;
    color: #4b5563;
}

button {
    height: 44px;
    border: 0;
    border-radius: 11px;
    background: linear-gradient(95deg, #0f766e 0%, #0d9488 100%);
    color: #fff;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
}

button:hover {
    filter: brightness(1.03);
}

.error {
    color: #b91c1c;
    font-size: 12px;
    font-weight: 500;
}
</style>
