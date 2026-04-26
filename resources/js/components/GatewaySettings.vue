<script setup>
import { Header, Panel, PanelHeader, Button, Field, Subheading, Input, Select, Checkbox } from '@statamic/cms/ui';
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    settings: Object,
    maskedToken: String,
    logChannels: Array,
    updateUrl: String,
    resourcesUrl: String,
    csrfToken: String,
    successMessage: String,
    errors: { type: Object, default: () => ({}) },
});

const dirty = ref(false);
const saving = ref(false);
const tokenRevealed = ref(false);
const loadingResources = ref(true);

const availableResources = reactive({
    collections: [],
    globals: [],
    navigations: [],
    taxonomies: [],
    asset_containers: [],
    forms: [],
});

const form = reactive({
    enabled: props.settings.enabled ?? false,
    token: props.maskedToken ?? '',
    rate_limits: {
        execute: props.settings.rate_limits?.execute ?? 30,
        capabilities: props.settings.rate_limits?.capabilities ?? 60,
    },
    max_request_size: props.settings.max_request_size ?? 65536,
    tools: JSON.parse(JSON.stringify(props.settings.tools ?? {})),
    allowed_collections: [...(props.settings.allowed_collections ?? [])],
    allowed_globals: [...(props.settings.allowed_globals ?? [])],
    allowed_navigations: [...(props.settings.allowed_navigations ?? [])],
    allowed_taxonomies: [...(props.settings.allowed_taxonomies ?? [])],
    allowed_cache_targets: [...(props.settings.allowed_cache_targets ?? [])],
    allowed_asset_containers: [...(props.settings.allowed_asset_containers ?? [])],
    allowed_forms: [...(props.settings.allowed_forms ?? [])],
    allowed_custom_commands: [...(props.settings.allowed_custom_commands ?? [])],
    allowed_user_operations: props.settings.allowed_user_operations ?? false,
    max_asset_size: props.settings.max_asset_size ?? 10485760,
    allowed_asset_extensions: [...(props.settings.allowed_asset_extensions ?? [])],
    custom_commands: JSON.parse(JSON.stringify(props.settings.custom_commands ?? [])),
    denied_fields: {
        entry: [...(props.settings.denied_fields?.entry ?? [])],
        global: [...(props.settings.denied_fields?.global ?? [])],
        term: [...(props.settings.denied_fields?.term ?? [])],
    },
    confirmation: {
        ttl: props.settings.confirmation?.ttl ?? 60,
        tools: JSON.parse(JSON.stringify(props.settings.confirmation?.tools ?? {})),
    },
    audit: {
        channel: props.settings.audit?.channel ?? '',
    },
});

const realToken = ref(props.settings.token ?? '');

const toolGroups = {
    asset: ['upload', 'list', 'get', 'delete', 'move'],
    blueprint: ['get', 'create', 'update', 'delete'],
    collection: ['list'],
    entry: ['get', 'list', 'create', 'update', 'upsert', 'delete', 'search', 'publish', 'unpublish'],
    global: ['get', 'update'],
    navigation: ['get', 'update', 'list'],
    term: ['get', 'list', 'upsert', 'delete'],
    taxonomy: ['list', 'get'],
    form: ['get', 'list', 'submissions'],
    site: ['list'],
    user: ['list', 'get', 'create', 'update', 'delete'],
    system: ['info'],
    custom_command: ['execute'],
    cache: ['clear'],
    stache: ['warm'],
    static: ['warm'],
};

const cacheTargetOptions = ['application', 'static', 'stache', 'glide'];

const confirmationTools = [
    { label: 'entry.create', group: 'entry', action: 'create' },
    { label: 'entry.update', group: 'entry', action: 'update' },
    { label: 'entry.upsert', group: 'entry', action: 'upsert' },
    { label: 'entry.delete', group: 'entry', action: 'delete' },
    { label: 'entry.unpublish', group: 'entry', action: 'unpublish' },
    { label: 'global.update', group: 'global', action: 'update' },
    { label: 'navigation.update', group: 'navigation', action: 'update' },
    { label: 'term.upsert', group: 'term', action: 'upsert' },
    { label: 'term.delete', group: 'term', action: 'delete' },
    { label: 'asset.upload', group: 'asset', action: 'upload' },
    { label: 'asset.delete', group: 'asset', action: 'delete' },
    { label: 'asset.move', group: 'asset', action: 'move' },
    { label: 'blueprint.delete', group: 'blueprint', action: 'delete' },
    { label: 'user.create', group: 'user', action: 'create' },
    { label: 'user.delete', group: 'user', action: 'delete' },
    { label: 'cache.clear', group: 'cache', action: 'clear' },
    { label: 'stache.warm', group: 'stache', action: 'warm' },
    { label: 'static.warm', group: 'static', action: 'warm' },
];


const tagInputs = reactive({
    'denied_fields.entry': '',
    'denied_fields.global': '',
    'denied_fields.term': '',
    'asset_extensions': '',
});

const logChannelOptions = computed(() => {
    return [
        { label: 'Default', value: '' },
        ...props.logChannels.map(ch => ({ label: ch, value: ch })),
    ];
});

function markDirty() { dirty.value = true; }

function toggleReveal() {
    if (tokenRevealed.value) {
        form.token = props.maskedToken;
    } else {
        form.token = realToken.value;
    }
    tokenRevealed.value = !tokenRevealed.value;
}

function generateToken() {
    const bytes = new Uint8Array(32);
    crypto.getRandomValues(bytes);
    const token = Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
    form.token = token;
    realToken.value = token;
    tokenRevealed.value = true;
    markDirty();
}

function toggleAllowlistItem(listKey, handle) {
    const idx = form[listKey].indexOf(handle);
    if (idx >= 0) {
        form[listKey].splice(idx, 1);
    } else {
        form[listKey].push(handle);
    }
    markDirty();
}

function addTag(listKey) {
    const val = tagInputs[listKey]?.trim();
    if (!val) return;
    if (listKey.startsWith('denied_fields.')) {
        const [, type] = listKey.split('.');
        if (!form.denied_fields[type].includes(val)) {
            form.denied_fields[type].push(val);
            markDirty();
        }
    } else if (listKey === 'asset_extensions') {
        if (!form.allowed_asset_extensions.includes(val)) {
            form.allowed_asset_extensions.push(val);
            markDirty();
        }
    }
    tagInputs[listKey] = '';
}

function removeTag(listKey, index) {
    if (listKey.startsWith('denied_fields.')) {
        const [, type] = listKey.split('.');
        form.denied_fields[type].splice(index, 1);
    } else if (listKey === 'asset_extensions') {
        form.allowed_asset_extensions.splice(index, 1);
    }
    markDirty();
}

function getTagList(listKey) {
    if (listKey.startsWith('denied_fields.')) {
        const [, type] = listKey.split('.');
        return form.denied_fields[type];
    }
    if (listKey === 'asset_extensions') {
        return form.allowed_asset_extensions;
    }
    return [];
}

function toggleCacheTarget(target) {
    const idx = form.allowed_cache_targets.indexOf(target);
    if (idx >= 0) form.allowed_cache_targets.splice(idx, 1);
    else form.allowed_cache_targets.push(target);
    markDirty();
}

function getConfirmationEnvs(group, action) {
    return (form.confirmation.tools?.[group]?.[action] ?? []).join(', ');
}

function setConfirmationEnvs(group, action, value) {
    if (!form.confirmation.tools[group]) form.confirmation.tools[group] = {};
    form.confirmation.tools[group][action] = value.split(',').map(s => s.trim()).filter(Boolean);
    markDirty();
}

function addCustomCommand() {
    form.custom_commands.push({
        alias: '',
        description: '',
        command: '',
        confirmation_environments: [],
    });
    markDirty();
}

function removeCustomCommand(index) {
    form.custom_commands.splice(index, 1);
    markDirty();
}

function getCommandConfirmEnvs(index) {
    return (form.custom_commands[index].confirmation_environments ?? []).join(', ');
}

function setCommandConfirmEnvs(index, value) {
    form.custom_commands[index].confirmation_environments = value.split(',').map(s => s.trim()).filter(Boolean);
    markDirty();
}

async function fetchResources() {
    loadingResources.value = true;
    try {
        const response = await fetch(props.resourcesUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (response.ok) {
            const data = await response.json();
            availableResources.collections = data.collections ?? [];
            availableResources.globals = data.globals ?? [];
            availableResources.navigations = data.navigations ?? [];
            availableResources.taxonomies = data.taxonomies ?? [];
            availableResources.asset_containers = data.asset_containers ?? [];
            availableResources.forms = data.forms ?? [];
        }
    } catch (e) {
        console.error('Failed to fetch resources:', e);
    } finally {
        loadingResources.value = false;
    }
}


async function save() {
    saving.value = true;
    const payload = JSON.parse(JSON.stringify(form));
    if (payload.token === props.maskedToken) delete payload.token;
    if (payload.confirmation?.tools) {
        for (const [group, actions] of Object.entries(payload.confirmation.tools)) {
            for (const [action, envs] of Object.entries(actions)) {
                if (Array.isArray(envs)) payload.confirmation.tools[group][action] = envs.join(', ');
            }
        }
    }
    try {
        const xsrfToken = document.cookie.split('; ')
            .find(row => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        const response = await fetch(props.updateUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': xsrfToken ? decodeURIComponent(xsrfToken) : '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });
        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            if (response.status === 422) {
                Statamic.$toast.error('Validation failed. Check the form for errors.');
            } else {
                Statamic.$toast.error(data.message || 'Failed to save settings.');
            }
            return;
        }
        dirty.value = false;
        Statamic.$toast.success('Settings saved successfully.');
        window.location.reload();
    } catch (error) {
        console.error('Save error:', error);
        Statamic.$toast.error('Failed to save settings.');
    } finally {
        saving.value = false;
    }
}

function beforeUnload(e) { if (dirty.value) e.preventDefault(); }
onMounted(() => {
    window.addEventListener('beforeunload', beforeUnload);
    if (props.successMessage) Statamic.$toast.success(props.successMessage);
    fetchResources();
});
onBeforeUnmount(() => { window.removeEventListener('beforeunload', beforeUnload); });
</script>


<template>
    <div>
        <Header title="AI Gateway">
            <template #actions>
                <Button variant="primary" @click="save" :disabled="saving">
                    {{ saving ? 'Saving...' : 'Save' }}
                </Button>
            </template>
        </Header>

        <div class="max-w-3xl mx-auto mt-6 space-y-6">

            <!-- General -->
            <Panel>
                <PanelHeader>General</PanelHeader>
                <div class="p-4 space-y-4">
                    <Checkbox v-model="form.enabled" label="Enable AI Gateway" @update:model-value="markDirty" />

                    <Field label="Bearer Token">
                        <div class="flex items-center gap-2">
                            <div class="flex-1">
                                <Input v-model="form.token" @update:model-value="markDirty" placeholder="Bearer token" />
                            </div>
                            <Button size="sm" @click="toggleReveal">{{ tokenRevealed ? 'Hide' : 'Reveal' }}</Button>
                            <Button size="sm" @click="generateToken">Generate</Button>
                        </div>
                    </Field>
                </div>
            </Panel>

            <!-- Rate Limits -->
            <Panel>
                <PanelHeader>Rate Limits</PanelHeader>
                <div class="p-4 grid grid-cols-2 gap-4">
                    <Field label="Execute (requests/min)">
                        <Input type="number" v-model="form.rate_limits.execute" @update:model-value="markDirty" :min="1" />
                    </Field>
                    <Field label="Capabilities (requests/min)">
                        <Input type="number" v-model="form.rate_limits.capabilities" @update:model-value="markDirty" :min="1" />
                    </Field>
                </div>
            </Panel>

            <!-- Request Limits -->
            <Panel>
                <PanelHeader>Request Limits</PanelHeader>
                <div class="p-4">
                    <Field label="Max Request Size (bytes)">
                        <Input type="number" v-model="form.max_request_size" @update:model-value="markDirty" :min="1024" />
                    </Field>
                </div>
            </Panel>

            <!-- Tools -->
            <Panel>
                <PanelHeader>Tools</PanelHeader>
                <div class="p-4 space-y-4">
                    <div v-for="(actions, group) in toolGroups" :key="group">
                        <Subheading>{{ group }}</Subheading>
                        <div class="mt-2 space-y-1.5">
                            <Checkbox
                                v-for="action in actions"
                                :key="`${group}.${action}`"
                                v-model="form.tools[group][action]"
                                :label="`${group}.${action}`"
                                @update:model-value="markDirty"
                            />
                        </div>
                    </div>
                </div>
            </Panel>

            <!-- Allowlists -->
            <Panel>
                <PanelHeader>Allowlists</PanelHeader>
                <div class="p-4 space-y-5">

                    <Field label="Collections">
                        <div v-if="loadingResources" class="text-sm text-gray-400">Loading...</div>
                        <div v-else-if="availableResources.collections.length === 0" class="text-sm text-gray-400">No collections found.</div>
                        <div v-else class="space-y-1.5">
                            <Checkbox
                                v-for="item in availableResources.collections"
                                :key="item.handle"
                                :model-value="form.allowed_collections.includes(item.handle)"
                                :label="`${item.title} (${item.handle})`"
                                @update:model-value="toggleAllowlistItem('allowed_collections', item.handle)"
                            />
                        </div>
                    </Field>

                    <Field label="Globals">
                        <div v-if="loadingResources" class="text-sm text-gray-400">Loading...</div>
                        <div v-else-if="availableResources.globals.length === 0" class="text-sm text-gray-400">No global sets found.</div>
                        <div v-else class="space-y-1.5">
                            <Checkbox
                                v-for="item in availableResources.globals"
                                :key="item.handle"
                                :model-value="form.allowed_globals.includes(item.handle)"
                                :label="`${item.title} (${item.handle})`"
                                @update:model-value="toggleAllowlistItem('allowed_globals', item.handle)"
                            />
                        </div>
                    </Field>

                    <Field label="Navigations">
                        <div v-if="loadingResources" class="text-sm text-gray-400">Loading...</div>
                        <div v-else-if="availableResources.navigations.length === 0" class="text-sm text-gray-400">No navigations found.</div>
                        <div v-else class="space-y-1.5">
                            <Checkbox
                                v-for="item in availableResources.navigations"
                                :key="item.handle"
                                :model-value="form.allowed_navigations.includes(item.handle)"
                                :label="`${item.title} (${item.handle})`"
                                @update:model-value="toggleAllowlistItem('allowed_navigations', item.handle)"
                            />
                        </div>
                    </Field>

                    <Field label="Taxonomies">
                        <div v-if="loadingResources" class="text-sm text-gray-400">Loading...</div>
                        <div v-else-if="availableResources.taxonomies.length === 0" class="text-sm text-gray-400">No taxonomies found.</div>
                        <div v-else class="space-y-1.5">
                            <Checkbox
                                v-for="item in availableResources.taxonomies"
                                :key="item.handle"
                                :model-value="form.allowed_taxonomies.includes(item.handle)"
                                :label="`${item.title} (${item.handle})`"
                                @update:model-value="toggleAllowlistItem('allowed_taxonomies', item.handle)"
                            />
                        </div>
                    </Field>

                    <Field label="Asset Containers">
                        <div v-if="loadingResources" class="text-sm text-gray-400">Loading...</div>
                        <div v-else-if="availableResources.asset_containers.length === 0" class="text-sm text-gray-400">No asset containers found.</div>
                        <div v-else class="space-y-1.5">
                            <Checkbox
                                v-for="item in availableResources.asset_containers"
                                :key="item.handle"
                                :model-value="form.allowed_asset_containers.includes(item.handle)"
                                :label="`${item.title} (${item.handle})`"
                                @update:model-value="toggleAllowlistItem('allowed_asset_containers', item.handle)"
                            />
                        </div>
                    </Field>

                    <Field label="Forms">
                        <div v-if="loadingResources" class="text-sm text-gray-400">Loading...</div>
                        <div v-else-if="availableResources.forms.length === 0" class="text-sm text-gray-400">No forms found.</div>
                        <div v-else class="space-y-1.5">
                            <Checkbox
                                v-for="item in availableResources.forms"
                                :key="item.handle"
                                :model-value="form.allowed_forms.includes(item.handle)"
                                :label="`${item.title} (${item.handle})`"
                                @update:model-value="toggleAllowlistItem('allowed_forms', item.handle)"
                            />
                        </div>
                    </Field>

                    <Field label="Cache Targets">
                        <div class="space-y-1.5">
                            <Checkbox
                                v-for="target in cacheTargetOptions"
                                :key="target"
                                :model-value="form.allowed_cache_targets.includes(target)"
                                :label="target"
                                @update:model-value="toggleCacheTarget(target)"
                            />
                        </div>
                    </Field>
                </div>
            </Panel>

            <!-- User Management -->
            <Panel>
                <PanelHeader>User Management</PanelHeader>
                <div class="p-4 space-y-4">
                    <Checkbox v-model="form.allowed_user_operations" label="Enable user management operations" @update:model-value="markDirty" />
                    <p class="text-sm text-gray-500">When enabled, user tools (list, get, create, update, delete) are permitted. When disabled, all user tools are blocked.</p>
                </div>
            </Panel>

            <!-- Asset Upload Settings -->
            <Panel>
                <PanelHeader>Asset Upload Settings</PanelHeader>
                <div class="p-4 space-y-4">
                    <Field label="Max Asset Size (bytes)">
                        <Input type="number" v-model="form.max_asset_size" @update:model-value="markDirty" :min="1" />
                    </Field>

                    <Field label="Allowed Asset Extensions">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="flex-1">
                                <Input v-model="tagInputs['asset_extensions']"
                                    @keydown.enter.prevent="addTag('asset_extensions')"
                                    placeholder="Add extension (e.g. jpg)..." />
                            </div>
                            <Button size="sm" @click="addTag('asset_extensions')">Add</Button>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="(item, i) in getTagList('asset_extensions')" :key="i"
                                class="inline-flex items-center gap-1 bg-gray-200 dark:bg-gray-700 text-sm rounded-full px-3 py-1">
                                {{ item }}
                                <button type="button" @click="removeTag('asset_extensions', i)"
                                    class="text-gray-400 hover:text-red-500 ml-1">&times;</button>
                            </span>
                        </div>
                    </Field>
                </div>
            </Panel>

            <!-- Custom Commands -->
            <Panel>
                <PanelHeader>Custom Commands</PanelHeader>
                <div class="p-4 space-y-4">
                    <p class="text-sm text-gray-500">Define custom artisan commands that AI agents can execute through the Gateway.</p>

                    <div v-for="(cmd, index) in form.custom_commands" :key="index"
                        class="border rounded-lg p-4 space-y-3 bg-gray-50 dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-sm">Command {{ index + 1 }}</span>
                            <Button size="sm" variant="danger" @click="removeCustomCommand(index)">Remove</Button>
                        </div>
                        <Field label="Alias (kebab-case)">
                            <Input v-model="cmd.alias" @update:model-value="markDirty" placeholder="e.g. rebuild-search" />
                        </Field>
                        <Field label="Description">
                            <Input v-model="cmd.description" @update:model-value="markDirty" placeholder="Human-readable description" />
                        </Field>
                        <Field label="Artisan Command">
                            <Input v-model="cmd.command" @update:model-value="markDirty" placeholder="e.g. statamic:search:update --all" />
                        </Field>
                        <Field label="Confirmation Environments">
                            <Input
                                :model-value="getCommandConfirmEnvs(index)"
                                @update:model-value="setCommandConfirmEnvs(index, $event)"
                                placeholder="e.g. production, staging" />
                        </Field>
                    </div>

                    <Button size="sm" @click="addCustomCommand">Add Command</Button>
                </div>
            </Panel>

            <!-- Field Deny Lists -->
            <Panel>
                <PanelHeader>Field Deny Lists</PanelHeader>
                <div class="p-4 space-y-5">
                    <div v-for="(label, key) in {
                        'denied_fields.entry': 'Entry',
                        'denied_fields.global': 'Global',
                        'denied_fields.term': 'Term',
                    }" :key="key">
                        <Field :label="label">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="flex-1">
                                    <Input v-model="tagInputs[key]"
                                        @keydown.enter.prevent="addTag(key)"
                                        placeholder="Add field name..." />
                                </div>
                                <Button size="sm" @click="addTag(key)">Add</Button>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="(item, i) in getTagList(key)" :key="i"
                                    class="inline-flex items-center gap-1 bg-gray-200 dark:bg-gray-700 text-sm rounded-full px-3 py-1">
                                    {{ item }}
                                    <button type="button" @click="removeTag(key, i)"
                                        class="text-gray-400 hover:text-red-500 ml-1">&times;</button>
                                </span>
                            </div>
                        </Field>
                    </div>
                </div>
            </Panel>

            <!-- Confirmation Flow -->
            <Panel>
                <PanelHeader>Confirmation Flow</PanelHeader>
                <div class="p-4 space-y-4">
                    <Field label="Token TTL (seconds)">
                        <Input type="number" v-model="form.confirmation.ttl" @update:model-value="markDirty" :min="1" />
                    </Field>

                    <Field label="Per-Tool Environment Rules">
                        <p class="text-sm text-gray-500 mb-3">Comma-separate environments that require confirmation.</p>
                        <div class="space-y-2">
                            <div v-for="tool in confirmationTools" :key="tool.label"
                                class="flex items-center gap-3">
                                <span class="w-40 font-mono text-sm text-gray-600 dark:text-gray-400">{{ tool.label }}</span>
                                <div class="flex-1">
                                    <Input
                                        :model-value="getConfirmationEnvs(tool.group, tool.action)"
                                        @update:model-value="setConfirmationEnvs(tool.group, tool.action, $event)"
                                        placeholder="e.g. production, staging" />
                                </div>
                            </div>
                        </div>
                    </Field>
                </div>
            </Panel>

            <!-- Audit -->
            <Panel>
                <PanelHeader>Audit</PanelHeader>
                <div class="p-4">
                    <Field label="Log Channel">
                        <Select v-model="form.audit.channel" :options="logChannelOptions" @update:model-value="markDirty" />
                    </Field>
                </div>
            </Panel>

        </div>
    </div>
</template>