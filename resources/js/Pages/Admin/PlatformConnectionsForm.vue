<script setup>
import ClientTabs from '@/Components/ClientTabs.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    mode: { type: String, required: true },
    client: { type: Object, required: true },
    connection: { type: Object, default: null },
});

const isEdit = computed(() => props.mode === 'edit');
const isHubspot = computed(() => form.platform_type === 'hubspot');
const isTrebel = computed(() => form.platform_type === 'trebel');
const form = useForm({
    name: props.connection?.name ?? '',
    slug: props.connection?.slug ?? '',
    platform_type: props.connection?.platform_type ?? 'hubspot',
    base_url: props.connection?.base_url ?? '',
    signature_header: props.connection?.signature_header ?? '',
    webhook_secret: '',
    access_token: '',
    api_key: '',
    username: '',
    password: '',
    contact_properties_text: JSON.stringify(props.connection?.settings?.contact_properties ?? [
        'firstname',
        'lastname',
        'phone',
        'campus_de_interes',
        'nivel_escolar_de_interes',
        'plantilla_de_whatsapp',
    ], null, 2),
    send_path: props.connection?.settings?.send_path ?? '',
    http_method: props.connection?.settings?.http_method ?? 'POST',
    auth_mode: props.connection?.settings?.auth_mode ?? 'bearer_api_key',
    api_key_header: props.connection?.settings?.api_key_header ?? 'X-API-Key',
    request_template_text: JSON.stringify(props.connection?.settings?.request_template ?? {
        template_id: '{{template.external_template_id}}',
        phone: '{{contact.phone}}',
        first_name: '{{contact.firstname}}',
    }, null, 2),
    headers_text: JSON.stringify(props.connection?.settings?.headers ?? {}, null, 2),
    timeout_seconds: props.connection?.settings?.timeout_seconds ?? 20,
    active: props.connection?.active ?? true,
});
const updateHubspotCredentials = computed(() => form.webhook_secret !== '' || form.access_token !== '');

const parseJsonField = (field, fallback) => {
    try {
        form.clearErrors(field);
        return JSON.parse(form[field] || fallback);
    } catch (error) {
        form.setError(field, 'JSON inválido');
        return null;
    }
};

const enableHubspotCredentialsEdit = () => {
    form.webhook_secret = ' ';
    form.access_token = ' ';
};

const disableHubspotCredentialsEdit = () => {
    form.webhook_secret = '';
    form.access_token = '';
};

const normalizeWebhookSecretInput = () => {
    if (form.webhook_secret === ' ') {
        form.webhook_secret = '';
    }
};

const normalizeAccessTokenInput = () => {
    if (form.access_token === ' ') {
        form.access_token = '';
    }
};

const submit = () => {
    const contactProperties = parseJsonField('contact_properties_text', '[]');
    const headers = parseJsonField('headers_text', '{}');
    const requestTemplate = parseJsonField('request_template_text', '{}');

    if (contactProperties === null || headers === null || requestTemplate === null) {
        return;
    }

    const payload = {
        name: form.name,
        slug: form.slug || null,
        platform_type: form.platform_type,
        base_url: form.base_url || null,
        signature_header: form.signature_header || null,
        webhook_secret: form.webhook_secret || null,
        active: !!form.active,
        credentials: {
            access_token: form.access_token || undefined,
            api_key: form.api_key || undefined,
            username: form.username || undefined,
            password: form.password || undefined,
        },
        settings: {
            contact_properties: contactProperties,
            send_path: form.send_path || null,
            http_method: form.http_method,
            auth_mode: form.auth_mode || null,
            api_key_header: form.api_key_header || null,
            request_template: requestTemplate,
            headers,
            timeout_seconds: Number(form.timeout_seconds || 20),
        },
    };

    form.transform(() => payload);

    if (isEdit.value) {
        form.put(`/admin/clients/${props.client.id}/connections/${props.connection.id}`);
        return;
    }

    form.post(`/admin/clients/${props.client.id}/connections`);
};
</script>

<template>
    <AdminLayout :title="isEdit ? 'Editar conexión' : 'Nueva conexión'">
        <ClientTabs :client="client" />
        <form class="form" @submit.prevent="submit">
            <div class="grid">
                <label><span>Nombre</span><input v-model="form.name" type="text" required></label>
                <label><span>Slug</span><input v-model="form.slug" type="text"></label>
                <label><span>Tipo de plataforma</span><select v-model="form.platform_type"><option value="hubspot">HubSpot</option><option value="trebel">Trebel</option></select></label>
            </div>

            <section v-if="isHubspot" class="section">
                <header>
                    <h2>HubSpot</h2>
                    <p>Webhook, token y propiedades del contacto a consultar.</p>
                </header>
                <div class="grid">
                    <label><span>Base URL</span><input v-model="form.base_url" type="url" placeholder="https://api.hubapi.com"></label>
                    <label><span>Header de firma</span><input v-model="form.signature_header" type="text" placeholder="x-signature"></label>
                    <label><span>Timeout (segundos)</span><input v-model="form.timeout_seconds" type="number" min="1"></label>
                    <label class="toggle-field">
                        <span>Estado</span>
                        <button
                            type="button"
                            class="toggle"
                            :class="{ on: form.active }"
                            :aria-pressed="form.active ? 'true' : 'false'"
                            @click="form.active = !form.active"
                        >
                            <span class="track">
                                <span class="thumb" />
                            </span>
                            <span class="toggle-label">{{ form.active ? 'Activa' : 'Inactiva' }}</span>
                        </button>
                    </label>
                </div>
                <div v-if="isEdit && (connection?.has_webhook_secret || connection?.has_credentials)" class="credentials-bar">
                    <div class="credentials-copy">
                        <strong>Credenciales</strong>
                        <small class="hint ok">Ya configuradas. Ábrelas solo si necesitas reemplazarlas.</small>
                    </div>
                    <button
                        type="button"
                        class="ghost-button"
                        @click="updateHubspotCredentials ? disableHubspotCredentialsEdit() : enableHubspotCredentialsEdit()"
                    >
                        {{ updateHubspotCredentials ? 'Conservar actuales' : 'Actualizar credenciales' }}
                    </button>
                </div>
                <div v-if="!isEdit || updateHubspotCredentials || (!connection?.has_webhook_secret && !connection?.has_credentials)" class="grid">
                    <label>
                        <span>Webhook secret</span>
                        <input
                            v-model="form.webhook_secret"
                            type="password"
                            :placeholder="isEdit ? 'solo para reemplazar' : 'Webhook secret'"
                            @focus="normalizeWebhookSecretInput"
                        >
                    </label>
                    <label>
                        <span>Access token</span>
                        <input
                            v-model="form.access_token"
                            type="password"
                            :placeholder="isEdit ? 'solo para reemplazar' : 'HubSpot token'"
                            @focus="normalizeAccessTokenInput"
                        >
                    </label>
                </div>
                <label><span>Propiedades HubSpot a leer</span><textarea v-model="form.contact_properties_text" rows="8"></textarea></label>
            </section>

            <section v-if="isTrebel" class="section">
                <header>
                    <h2>Trebel</h2>
                    <p>Endpoint, autenticación y payload configurable.</p>
                </header>
                <div class="grid">
                    <label><span>Base URL</span><input v-model="form.base_url" type="url" required placeholder="https://api.trebel.example"></label>
                    <label><span>Send path</span><input v-model="form.send_path" type="text" placeholder="/messages/send"></label>
                    <label><span>HTTP method</span><select v-model="form.http_method"><option value="POST">POST</option><option value="PUT">PUT</option><option value="PATCH">PATCH</option></select></label>
                    <label><span>Auth mode</span><select v-model="form.auth_mode"><option value="bearer_api_key">Bearer API key</option><option value="header_api_key">Header API key</option><option value="basic_auth">Basic auth</option></select></label>
                    <label v-if="form.auth_mode === 'header_api_key'"><span>Header API key</span><input v-model="form.api_key_header" type="text"></label>
                    <label v-if="form.auth_mode === 'bearer_api_key' || form.auth_mode === 'header_api_key'">
                        <span>API key</span>
                        <small v-if="isEdit && connection?.has_credentials" class="hint ok">Ya configurada. Déjala en blanco para conservarla.</small>
                        <input v-model="form.api_key" type="password" :placeholder="isEdit ? 'solo para reemplazar' : 'Trebel API key'">
                    </label>
                    <label v-if="form.auth_mode === 'basic_auth'"><span>Username</span><input v-model="form.username" type="text"></label>
                    <label v-if="form.auth_mode === 'basic_auth'">
                        <span>Password</span>
                        <small v-if="isEdit && connection?.has_credentials" class="hint ok">Ya configurada. Déjala en blanco para conservarla.</small>
                        <input v-model="form.password" type="password" :placeholder="isEdit ? 'solo para reemplazar' : 'Password'">
                    </label>
                    <label><span>Timeout (segundos)</span><input v-model="form.timeout_seconds" type="number" min="1"></label>
                    <label class="toggle-field">
                        <span>Estado</span>
                        <button
                            type="button"
                            class="toggle"
                            :class="{ on: form.active }"
                            :aria-pressed="form.active ? 'true' : 'false'"
                            @click="form.active = !form.active"
                        >
                            <span class="track">
                                <span class="thumb" />
                            </span>
                            <span class="toggle-label">{{ form.active ? 'Activa' : 'Inactiva' }}</span>
                        </button>
                    </label>
                </div>
                <label><span>Headers JSON</span><textarea v-model="form.headers_text" rows="8" placeholder='{"X-Workspace":"abc"}'></textarea></label>
                <label><span>Request template JSON</span><textarea v-model="form.request_template_text" rows="12" placeholder='{"template_id":"{{template.external_template_id}}","phone":"{{contact.phone}}"}'></textarea></label>
            </section>

            <div class="actions">
                <Link :href="`/admin/clients/${client.id}/connections`">Cancelar</Link>
                <button type="submit" :disabled="form.processing">Guardar</button>
            </div>
        </form>
    </AdminLayout>
</template>

<style scoped>
.form { display: grid; gap: 14px; }
.section { display: grid; gap: 14px; padding: 14px; border: 1px solid #e2e8f0; border-radius: 8px; }
.section header { display: grid; gap: 4px; }
.section h2, .section p { margin: 0; }
.section p { color: #64748b; font-size: 14px; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
label { display: grid; gap: 6px; }
span { color: #334155; font-size: 14px; }
.hint { font-size: 12px; color: #64748b; }
.hint.ok { color: #047857; }
.credentials-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    padding: 10px 12px;
}
.credentials-copy {
    display: grid;
    gap: 2px;
}
.ghost-button {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 6px 10px;
    background: #fff;
    color: #334155;
    font-size: 12px;
    cursor: pointer;
}
input, select, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; }
.toggle-field { display: grid; gap: 8px; align-content: start; }
.toggle {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    width: fit-content;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 8px 10px;
    background: #fff;
    color: #334155;
    cursor: pointer;
}
.toggle .track {
    position: relative;
    width: 40px;
    height: 22px;
    border-radius: 999px;
    background: #cbd5e1;
    transition: background 0.16s ease;
}
.toggle .thumb {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 16px;
    height: 16px;
    border-radius: 999px;
    background: #fff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.24);
    transition: transform 0.16s ease;
}
.toggle.on {
    border-color: #93c5fd;
    background: #eff6ff;
}
.toggle.on .track {
    background: #2563eb;
}
.toggle.on .thumb {
    transform: translateX(18px);
}
.toggle-label {
    font-size: 14px;
    color: #334155;
}
.actions { display: flex; gap: 10px; }
.actions a, .actions button { border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 14px; background: #fff; color: #334155; text-decoration: none; }
.actions button { background: #2563eb; border-color: #2563eb; color: #fff; cursor: pointer; }
</style>
