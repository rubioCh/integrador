<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mode: { type: String, required: true },
    platform: { type: Object, default: null },
});

const isEdit = computed(() => props.mode === 'edit');

const credentials = props.platform?.credentials ?? {};
const settings = props.platform?.settings ?? {};

const form = useForm({
    name: props.platform?.name ?? '',
    slug: props.platform?.slug ?? '',
    type: props.platform?.type ?? 'hubspot',

    hubspot_api_token: credentials.access_token ?? credentials.api_token ?? '',

    odoo_username: credentials.username ?? '',
    odoo_password: credentials.password ?? '',
    odoo_database: credentials.database ?? '',
    odoo_url: settings.url ?? '',

    netsuite_account: credentials.account ?? '',
    netsuite_consumer_key: credentials.consumer_key ?? '',
    netsuite_consumer_secret: credentials.consumer_secret ?? '',
    netsuite_token_id: credentials.token_id ?? credentials.certificate_id ?? '',
    netsuite_token_secret: credentials.token_secret ?? '',
    netsuite_private_key: credentials.private_key ?? '',

    generic_auth_mode: settings.auth_mode ?? '',
    generic_api_key: credentials.api_key ?? '',
    generic_basic_user: credentials.username ?? credentials.basic_user ?? '',
    generic_basic_password: credentials.password ?? credentials.basic_password ?? '',
    generic_oauth_client_id: credentials.client_id ?? credentials.oauth_client_id ?? '',
    generic_oauth_client_secret: credentials.client_secret ?? credentials.oauth_client_secret ?? '',
    generic_oauth_token_url: settings.token_url ?? settings.oauth_token_url ?? '',

    signature: props.platform?.signature ?? '',
    secret_key: props.platform?.secret_key ?? '',
    api_url: settings.base_url ?? '',
    active: props.platform?.active ?? true,
});

const requiredFields = computed(() => {
    const required = ['name', 'type'];

    if (form.type === 'hubspot') {
        required.push('hubspot_api_token');
    }

    if (form.type === 'odoo') {
        required.push('odoo_username', 'odoo_password', 'odoo_database');
    }

    if (form.type === 'netsuite') {
        required.push(
            'netsuite_account',
            'netsuite_consumer_key',
            'netsuite_consumer_secret',
            'netsuite_token_id',
            'netsuite_token_secret',
            'netsuite_private_key'
        );
    }

    if (form.type === 'generic') {
        required.push('api_url');

        if (form.generic_auth_mode === 'bearer_api_key') {
            required.push('generic_api_key');
        }

        if (form.generic_auth_mode === 'basic_auth') {
            required.push('generic_basic_user', 'generic_basic_password');
        }

        if (form.generic_auth_mode === 'oauth2_client_credentials') {
            required.push('generic_oauth_client_id', 'generic_oauth_client_secret', 'generic_oauth_token_url');
        }
    }

    return required;
});

const isGenericBearer = computed(() => form.type === 'generic' && form.generic_auth_mode === 'bearer_api_key');
const isGenericBasic = computed(() => form.type === 'generic' && form.generic_auth_mode === 'basic_auth');
const isGenericOAuth = computed(() => form.type === 'generic' && form.generic_auth_mode === 'oauth2_client_credentials');

const missingRequiredCount = computed(() => requiredFields.value
    .filter((field) => {
        const value = form[field];
        return value === null || value === undefined || String(value).trim() === '';
    }).length);

const canTestConnection = computed(() => isEdit.value && !form.processing);

const compactObject = (obj) => Object.fromEntries(Object.entries(obj)
    .filter(([_, value]) => {
        if (value === null || value === undefined) return false;
        if (typeof value === 'string') return value.trim() !== '';
        return true;
    }));

const buildCredentials = () => {
    if (form.type === 'hubspot') {
        return compactObject({
            access_token: form.hubspot_api_token,
        });
    }

    if (form.type === 'odoo') {
        return compactObject({
            username: form.odoo_username,
            password: form.odoo_password,
            database: form.odoo_database,
        });
    }

    if (form.type === 'netsuite') {
        return compactObject({
            account: form.netsuite_account,
            consumer_key: form.netsuite_consumer_key,
            consumer_secret: form.netsuite_consumer_secret,
            token_id: form.netsuite_token_id,
            token_secret: form.netsuite_token_secret,
            private_key: form.netsuite_private_key,
        });
    }

    if (form.generic_auth_mode === 'bearer_api_key') {
        return compactObject({
            api_key: form.generic_api_key,
        });
    }

    if (form.generic_auth_mode === 'basic_auth') {
        return compactObject({
            username: form.generic_basic_user,
            password: form.generic_basic_password,
        });
    }

    if (form.generic_auth_mode === 'oauth2_client_credentials') {
        return compactObject({
            client_id: form.generic_oauth_client_id,
            client_secret: form.generic_oauth_client_secret,
        });
    }

    return {};
};

const buildSettings = () => {
    const common = compactObject({
        base_url: form.api_url,
    });

    if (form.type === 'odoo') {
        return {
            ...common,
            ...compactObject({
                url: form.odoo_url || form.api_url,
            }),
        };
    }

    if (form.type === 'generic') {
        return {
            ...common,
            ...compactObject({
                auth_mode: form.generic_auth_mode,
                token_url: form.generic_oauth_token_url,
            }),
        };
    }

    return common;
};

const testConnection = () => {
    if (!isEdit.value) {
        return;
    }

    router.post(`/admin/platforms/${props.platform.id}/test-connection`, {}, {
        preserveScroll: true,
    });
};

const submit = () => {
    const payload = {
        name: form.name,
        slug: form.slug || null,
        type: form.type,
        signature: form.signature || null,
        secret_key: form.secret_key || null,
        active: !!form.active,
        credentials: buildCredentials(),
        settings: buildSettings(),
    };

    form.transform(() => payload);

    if (isEdit.value) {
        form.put(`/admin/platforms/${props.platform.id}`);
        return;
    }

    form.post('/admin/platforms');
};
</script>

<template>
    <AdminLayout title="Platforms">
        <form class="platform-page" @submit.prevent="submit">
            <header class="head-actions">
                <p class="subtitle">Configure a new platform integration</p>
                <div class="actions">
                    <span v-if="missingRequiredCount > 0" class="warning">Complete required fields</span>
                    <button type="button" class="secondary" :disabled="!canTestConnection" @click="testConnection">
                        Test connection
                    </button>
                    <button type="submit" class="primary" :disabled="form.processing">Save platform</button>
                </div>
            </header>

            <section class="block">
                <header>
                    <h2>Basic Information</h2>
                    <p>Platform name and type configuration</p>
                </header>
                <div class="grid two">
                    <label>
                        <span>Platform Name</span>
                        <input v-model="form.name" type="text" placeholder="Enter platform name" required>
                    </label>
                    <label>
                        <span>Platform Type</span>
                        <select v-model="form.type" required>
                            <option value="hubspot">HubSpot</option>
                            <option value="odoo">Odoo</option>
                            <option value="netsuite">NetSuite</option>
                            <option value="generic">Generic</option>
                        </select>
                    </label>
                    <label>
                        <span>Slug</span>
                        <input v-model="form.slug" type="text" placeholder="platform-slug">
                    </label>
                </div>
            </section>

            <section v-if="form.type === 'hubspot'" class="block">
                <header>
                    <h2>HubSpot Configuration</h2>
                    <p>API credentials and authentication settings</p>
                </header>
                <div class="grid one">
                    <label>
                        <span>API Token</span>
                        <input v-model="form.hubspot_api_token" type="text" placeholder="Enter your HubSpot API token" required>
                    </label>
                </div>
            </section>

            <section v-if="form.type === 'odoo'" class="block">
                <header>
                    <h2>Odoo Configuration</h2>
                    <p>Database credentials and connection settings</p>
                </header>
                <div class="grid two">
                    <label>
                        <span>Username</span>
                        <input v-model="form.odoo_username" type="text" placeholder="Enter Odoo username" required>
                    </label>
                    <label>
                        <span>Password</span>
                        <input v-model="form.odoo_password" type="password" placeholder="Enter Odoo password" required>
                    </label>
                    <label>
                        <span>Database</span>
                        <input v-model="form.odoo_database" type="text" placeholder="Enter database name" required>
                    </label>
                    <label>
                        <span>Odoo URL</span>
                        <input v-model="form.odoo_url" type="url" placeholder="https://odoo.example.com">
                    </label>
                </div>
            </section>

            <section v-if="form.type === 'netsuite'" class="block">
                <header>
                    <h2>NetSuite Configuration</h2>
                    <p>OAuth credentials and certificate settings</p>
                </header>
                <div class="grid two">
                    <label>
                        <span>Account ID</span>
                        <input v-model="form.netsuite_account" type="text" placeholder="e.g. 1234567" required>
                    </label>
                    <label>
                        <span>Consumer Key</span>
                        <input v-model="form.netsuite_consumer_key" type="text" placeholder="Enter consumer key" required>
                    </label>
                    <label>
                        <span>Consumer Secret</span>
                        <input v-model="form.netsuite_consumer_secret" type="password" placeholder="Enter consumer secret" required>
                    </label>
                    <label>
                        <span>Token ID</span>
                        <input v-model="form.netsuite_token_id" type="text" placeholder="Enter token ID" required>
                    </label>
                    <label>
                        <span>Token Secret</span>
                        <input v-model="form.netsuite_token_secret" type="password" placeholder="Enter token secret" required>
                    </label>
                </div>
                <div class="grid one">
                    <label>
                        <span>Private Key</span>
                        <textarea v-model="form.netsuite_private_key" rows="4" placeholder="Paste your private key here" required />
                    </label>
                </div>
            </section>

            <section v-if="form.type === 'generic'" class="block">
                <header>
                    <h2>Generic Configuration</h2>
                    <p>Authentication and integration settings</p>
                </header>
                <div class="grid two">
                    <label>
                        <span>Auth Mode</span>
                        <select v-model="form.generic_auth_mode">
                            <option value="">Select auth mode</option>
                            <option value="bearer_api_key">bearer_api_key</option>
                            <option value="basic_auth">basic_auth</option>
                            <option value="oauth2_client_credentials">oauth2_client_credentials</option>
                        </select>
                    </label>
                    <label v-if="isGenericBearer">
                        <span>API Key</span>
                        <input v-model="form.generic_api_key" type="text" placeholder="Enter API key">
                    </label>
                    <label v-if="isGenericBasic">
                        <span>Basic User</span>
                        <input v-model="form.generic_basic_user" type="text" placeholder="Enter basic user">
                    </label>
                    <label v-if="isGenericBasic">
                        <span>Basic Password</span>
                        <input v-model="form.generic_basic_password" type="password" placeholder="Enter basic password">
                    </label>
                    <label v-if="isGenericOAuth">
                        <span>OAuth Client ID</span>
                        <input v-model="form.generic_oauth_client_id" type="text" placeholder="Enter OAuth client ID">
                    </label>
                    <label v-if="isGenericOAuth">
                        <span>OAuth Client Secret</span>
                        <input v-model="form.generic_oauth_client_secret" type="password" placeholder="Enter OAuth client secret">
                    </label>
                    <label v-if="isGenericOAuth">
                        <span>OAuth Token URL</span>
                        <input v-model="form.generic_oauth_token_url" type="url" placeholder="https://oauth.example.com/token">
                    </label>
                </div>
            </section>

            <section class="block">
                <header>
                    <h2>Security &amp; API Settings</h2>
                    <p>Webhook signatures and API configuration</p>
                </header>
                <div class="grid two">
                    <label>
                        <span>Webhook Signature</span>
                        <input v-model="form.signature" type="text" placeholder="Enter webhook signature">
                    </label>
                    <label>
                        <span>Secret Key</span>
                        <input v-model="form.secret_key" type="text" placeholder="Enter secret key">
                    </label>
                    <label class="full">
                        <span>API URL</span>
                        <input v-model="form.api_url" type="url" placeholder="https://api.example.com">
                    </label>
                    <label class="check full">
                        <input v-model="form.active" type="checkbox">
                        <span>Active platform</span>
                    </label>
                </div>
            </section>

            <footer class="bottom-actions">
                <Link class="secondary link" href="/admin/platforms">Cancel</Link>
                <button type="submit" class="primary" :disabled="form.processing">Save platform</button>
            </footer>
        </form>
    </AdminLayout>
</template>

<style scoped>
.platform-page{
    display:grid;
    gap:16px;
}

.head-actions{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}

.subtitle{
    margin:0;
    color:#64748b;
    font-size:16px;
}

.actions{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
}

.warning{
    color:#f97316;
    font-size:13px;
}

.block{
    background:#ffffff;
    border:1px solid #dbe4ef;
    border-radius:14px;
    overflow:hidden;
    box-shadow:0 8px 20px rgba(15, 23, 42, 0.05);
}

.block > header{
    padding:16px 20px;
    border-bottom:1px solid #e2e8f0;
}

.block h2{
    margin:0;
    color:#0f172a;
    font-size:24px;
    font-family:'Barlow Condensed', sans-serif;
}

.block p{
    margin:4px 0 0;
    color:#64748b;
    font-size:14px;
}

.grid{
    padding:18px 20px;
    display:grid;
    gap:12px;
}

.grid.one{grid-template-columns:1fr;}
.grid.two{grid-template-columns:repeat(2, minmax(0, 1fr));}

label{
    display:grid;
    gap:6px;
}

label span{
    color:#334155;
    font-size:14px;
    font-weight:500;
}

input,
select,
textarea{
    width:100%;
    border:1px solid #cbd5e1;
    border-radius:8px;
    background:#f8fafc;
    color:#0f172a;
    font-size:14px;
    padding:10px 12px;
}

textarea{resize:vertical;}

input::placeholder,
textarea::placeholder{color:#94a3b8;}

.full{grid-column:1 / -1;}

.check{
    display:flex;
    align-items:center;
    gap:8px;
}

.check input{width:16px;height:16px;}

.primary,
.secondary{
    border-radius:8px;
    padding:10px 14px;
    font-size:14px;
    cursor:pointer;
    text-decoration:none;
}

.primary{
    border:0;
    background:#1d4ed8;
    color:#f8fafc;
}

.secondary{
    border:1px solid #cbd5e1;
    background:#f8fafc;
    color:#334155;
}

button:disabled{opacity:.55;cursor:not-allowed;}

.bottom-actions{
    display:flex;
    justify-content:flex-end;
    gap:10px;
}

@media (max-width: 980px){
    .subtitle{font-size:15px}
    .grid.two{grid-template-columns:1fr;}
    .block h2{font-size:24px}
    .block p{font-size:14px}
    label span{font-size:14px}
    input,select,textarea{font-size:14px}
}
</style>
