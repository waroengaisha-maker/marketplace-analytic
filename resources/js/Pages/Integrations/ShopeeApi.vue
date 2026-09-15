<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Tag from 'primevue/tag'

type ResearchStatus = 'available' | 'partial' | 'unavailable' | 'unverified'

type Capability = {
    name: string
    status: ResearchStatus
    description: string
    apis: string
}

type Endpoint = {
    endpoint: string
    method: string
    path: string
    purpose: string
    pagination: string
    keyParams: string
    source: string
}

type FieldRow = {
    field: string
    source: string
    status: ResearchStatus
    note: string
}

type ImportRate = {
    report: string
    rating: string
    severity: ResearchStatus
    summary: string
    caveats: string
}

type ReadinessItem = {
    item: string
    status: ResearchStatus
    note: string
}

type Scope = {
    title: string
    generated_at: string
    api_version: string
    purpose: string
    disclaimer: string
}

type ConnectionStatus = {
    configured: boolean
    missing: string[]
    environment: string
    region: string
    host: string
}

type ConnectionState = {
    configured: boolean
    missing: string[]
    environment: string
    region: string
    shop_name: string | null
    connected: boolean
    access_token_expires_at: string | null
    refresh_token_expires_at: string | null
    last_sync_at: string | null
    last_sync_status: string | null
    last_sync_error: string | null
    last_staged_at: string | null
    last_promoted_at: string | null
    staging_order_count: number
    staging_income_count: number
    staging_escrow_count: number
    staging_stale: boolean
}

type NormalizedRow = Record<string, string | number | null>

type LabResult = {
    ok: boolean
    error?: string | null
    rate_limited?: boolean
    message?: string
    raw?: unknown
    [key: string]: unknown
}

class LabError extends Error {
    rateLimited: boolean

    constructor(message: string, rateLimited = false) {
        super(message)
        this.rateLimited = rateLimited
    }
}

const props = defineProps<{
    scope: Scope
    capabilities: Capability[]
    endpoints: Endpoint[]
    fieldMatrix: {
        orders: FieldRow[]
        income: FieldRow[]
    }
    importRates: ImportRate[]
    readiness: ReadinessItem[]
    architecture: string[]
    connection: ConnectionState | null
}>()

const statusSeverity = (status: ResearchStatus) =>
    ({ available: 'success', partial: 'warn', unavailable: 'danger', unverified: 'secondary' } as const)[status] ?? 'secondary'

const statusLabel = (status: ResearchStatus) =>
    ({ available: 'Available', partial: 'Partial', unavailable: 'Unavailable', unverified: 'Unverified' } as const)[status] ?? status

const activeSection = ref<'lab' | 'research'>('lab')
const matrixTab = ref<'orders' | 'income'>('orders')

const lab = reactive({
    busy: false,
    error: null as string | null,
    rateLimited: false,
})

const configStatus = ref<ConnectionStatus | null>(null)
const conn = ref<ConnectionState | null>(props.connection ?? null)
const testResult = ref<LabResult | null>(null)
const ordersResult = ref<LabResult | null>(null)
const detailResult = ref<LabResult | null>(null)
const incomeResult = ref<LabResult | null>(null)
const syncOrdersResult = ref<LabResult | null>(null)
const syncIncomeResult = ref<LabResult | null>(null)
const authLink = ref<string | null>(null)

type ValidationRow = {
    key: string
    label: string
    status: 'matched' | 'mismatched' | 'missing_in_excel' | 'missing_in_api'
    line_identity_changed?: boolean
    differences: { field: string; api: unknown; excel: unknown }[]
}

type ValidationReport = {
    ok: boolean
    summary: {
        orders: Record<string, number>
        lines: Record<string, number>
        income: Record<string, number>
    }
    sources: {
        api: Record<string, number>
        excel: Record<string, number>
    }
    orders: ValidationRow[]
    lines: ValidationRow[]
    income: ValidationRow[]
}

const escrowLimit = ref('5')
const syncEscrowResult = ref<LabResult | null>(null)
const validateResult = ref<ValidationReport | null>(null)
const promoteDryRun = ref(true)
const promoteResult = ref<LabResult | null>(null)

const orderParams = reactive({ page_size: '10', order_status: '', date_from: '', date_to: '' })
const incomeParams = reactive({ status: '', date_from: '', date_to: '' })
const orderSn = ref('')

const connectParams = reactive({ partner_id: '', partner_key: '', environment: 'production', region: 'global' })
const syncParams = reactive({ page_size: '5', date_from: '', date_to: '' })

const orderStatusOptions = [
    { label: 'Any status', value: '' },
    ...['UNPAID', 'READY_TO_SHIP', 'PROCESSED', 'SHIPPED', 'COMPLETED', 'IN_CANCEL', 'CANCELLED'].map((value) => ({ label: value, value })),
]

const incomeStatusOptions = [
    { label: 'Any status', value: '' },
    ...['Pending', 'To Release', 'Released'].map((value) => ({ label: value, value })),
]

async function apiGet(path: string, params: Record<string, unknown> = {}, useJson = false): Promise<LabResult> {
    const qs = Object.entries(params)
        .filter(([, value]) => value !== '' && value !== null && value !== undefined)
        .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`)
        .join('&')

    const response = await fetch(`${path}${qs ? `?${qs}` : ''}`, {
        headers: useJson ? { Accept: 'application/json' } : undefined,
        redirect: useJson ? 'manual' : undefined,
    })
    const data = (await response.json()) as LabResult

    if (!response.ok && !data.ok) {
        throw new LabError(data.error ?? data.message ?? `Request failed (HTTP ${response.status})`, data.rate_limited === true)
    }

    return data
}

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
    return match ? decodeURIComponent(match[1]) : ''
}

async function apiPost(path: string, body: Record<string, unknown> = {}): Promise<LabResult> {
    const response = await fetch(path, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    })
    const data = (await response.json()) as LabResult

    if (!response.ok && !data.ok) {
        throw new LabError(data.error ?? data.message ?? `Request failed (HTTP ${response.status})`, data.rate_limited === true)
    }

    return data
}

function applyError(error: unknown) {
    if (error instanceof LabError) {
        lab.error = error.message
        lab.rateLimited = error.rateLimited
    } else {
        lab.error = error instanceof Error ? error.message : String(error)
        lab.rateLimited = false
    }
}

async function loadStatus() {
    lab.busy = true
    try {
        const result = await apiGet('/integrations/shopee-api/status')
        configStatus.value = (result.config as ConnectionStatus | undefined) ?? null
        conn.value = (result.connection as ConnectionState | undefined) ?? null
    } catch (error) {
        applyError(error)
    } finally {
        lab.busy = false
    }
}

async function saveConnect() {
    lab.busy = true
    try {
        const result = await apiPost('/integrations/shopee-api/configure', {
            partner_id: connectParams.partner_id,
            partner_key: connectParams.partner_key,
            environment: connectParams.environment,
            region: connectParams.region,
        })
        conn.value = (result.connection as ConnectionState | undefined) ?? conn.value
        lab.error = null
        lab.rateLimited = false
    } catch (error) {
        applyError(error)
    } finally {
        lab.busy = false
    }
}

async function getAuthLink() {
    lab.busy = true
    try {
        const result = await apiGet('/integrations/shopee-api/authorize')
        authLink.value = (result.url as string | undefined) ?? null
        lab.error = null
        lab.rateLimited = false
    } catch (error) {
        applyError(error)
    } finally {
        lab.busy = false
    }
}

async function runSyncOrders() {
    lab.busy = true
    try {
        syncOrdersResult.value = await apiPost('/integrations/shopee-api/sync-orders', {
            page_size: syncParams.page_size,
            date_from: syncParams.date_from,
            date_to: syncParams.date_to,
        })
        lab.error = null
        lab.rateLimited = false
    } catch (error) {
        applyError(error)
    } finally {
        await loadStatus()
        lab.busy = false
    }
}

async function runSyncIncome() {
    lab.busy = true
    try {
        syncIncomeResult.value = await apiPost('/integrations/shopee-api/sync-income')
        lab.error = null
        lab.rateLimited = false
    } catch (error) {
        applyError(error)
    } finally {
        await loadStatus()
        lab.busy = false
    }
}

async function runSyncEscrow() {
    lab.busy = true
    try {
        syncEscrowResult.value = await apiPost('/integrations/shopee-api/sync-escrow', { limit: escrowLimit.value })
        validateResult.value = null
        lab.error = null
        lab.rateLimited = false
    } catch (error) {
        applyError(error)
    } finally {
        await loadStatus()
        lab.busy = false
    }
}

async function runValidate() {
    lab.busy = true
    try {
        validateResult.value = (await apiGet('/integrations/shopee-api/validate')) as ValidationReport
        lab.error = null
        lab.rateLimited = false
    } catch (error) {
        applyError(error)
    } finally {
        lab.busy = false
    }
}

async function runPromote() {
    lab.busy = true
    try {
        promoteResult.value = await apiPost('/integrations/shopee-api/promote', { dry_run: promoteDryRun.value })
        lab.error = null
        lab.rateLimited = false
    } catch (error) {
        applyError(error)
        promoteResult.value = null
    } finally {
        await loadStatus()
        lab.busy = false
    }
}

const severityForStatus = (status: ValidationRow['status']) =>
    ({ matched: 'success', mismatched: 'warn', missing_in_excel: 'danger', missing_in_api: 'danger' } as const)[status] ?? 'secondary'

const labelForStatus = (status: ValidationRow['status']) =>
    ({ matched: 'Matched', mismatched: 'Mismatched', missing_in_excel: 'Missing in Excel', missing_in_api: 'Missing in API' } as const)[status] ?? status

async function runTest() {
    lab.busy = true
    try {
        testResult.value = await apiGet('/integrations/shopee-api/test')
        lab.error = null
        lab.rateLimited = false
    } catch (error) {
        applyError(error)
    } finally {
        lab.busy = false
    }
}

async function fetchOrders() {
    lab.busy = true
    try {
        ordersResult.value = await apiGet('/integrations/shopee-api/orders', {
            page_size: orderParams.page_size,
            order_status: orderParams.order_status,
            date_from: orderParams.date_from,
            date_to: orderParams.date_to,
        })
        testResult.value = null
        lab.error = null
        lab.rateLimited = false
    } catch (error) {
        applyError(error)
    } finally {
        lab.busy = false
    }
}

async function fetchDetail() {
    const serial = orderSn.value.trim()
    if (!serial) {
        lab.error = 'Enter an order serial number first.'
        return
    }
    lab.busy = true
    try {
        detailResult.value = await apiGet(`/integrations/shopee-api/orders/${encodeURIComponent(serial)}`)
        lab.error = null
        lab.rateLimited = false
    } catch (error) {
        applyError(error)
    } finally {
        lab.busy = false
    }
}

async function fetchIncome() {
    lab.busy = true
    try {
        incomeResult.value = await apiGet('/integrations/shopee-api/income', {
            status: incomeParams.status,
            date_from: incomeParams.date_from,
            date_to: incomeParams.date_to,
        })
        lab.error = null
        lab.rateLimited = false
    } catch (error) {
        applyError(error)
    } finally {
        lab.busy = false
    }
}

async function clearLab() {
    lab.busy = true
    try {
        await apiPost('/integrations/shopee-api/clear')
        lab.error = null
        lab.rateLimited = false
        testResult.value = null
        ordersResult.value = null
        detailResult.value = null
        incomeResult.value = null
        syncOrdersResult.value = null
        syncIncomeResult.value = null
        syncEscrowResult.value = null
        validateResult.value = null
        promoteResult.value = null
    } catch (error) {
        applyError(error)
    } finally {
        await loadStatus()
        lab.busy = false
    }
}

const orderRows = computed<NormalizedRow[]>(() => (ordersResult.value?.normalized as NormalizedRow[] | undefined) ?? [])
const detailHeaders = computed<NormalizedRow[]>(() => (detailResult.value?.headers as NormalizedRow[] | undefined) ?? [])
const detailLines = computed<NormalizedRow[]>(() => (detailResult.value?.lines as NormalizedRow[] | undefined) ?? [])
const detailEscrow = computed<Record<string, unknown>>(() => (detailResult.value?.escrow as Record<string, unknown> | undefined) ?? {})
const incomeRows = computed<NormalizedRow[]>(() => (incomeResult.value?.normalized as NormalizedRow[] | undefined) ?? [])

function tableColumns(rows: NormalizedRow[]): string[] {
    return Array.from(new Set(rows.flatMap((row) => Object.keys(row))))
}

function prettyJson(value: unknown): string {
    return JSON.stringify(value, null, 2)
}

onMounted(loadStatus)
</script>

<template>
    <Head title="Shopee API - Integration Lab" />

    <div class="flex w-full min-w-0 flex-col gap-6">
        <div class="flex flex-col gap-2">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Integrations</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-900">Shopee Open Platform API</h1>
            <p class="text-sm text-slate-500">Read-only integration lab plus feasibility research for replacing Excel report imports with the Shopee Open API v2.0.</p>
        </div>

        <Tabs v-model:value="activeSection">
            <TabList>
                <Tab value="lab">Integration Lab</Tab>
                <Tab value="research">Research</Tab>
            </TabList>
            <TabPanels>
                <TabPanel value="lab">
                    <div class="flex flex-col gap-4">
                        <Message severity="info" :closable="false">
                            <span class="font-semibold">Read-only lab.</span> Sync actions call Shopee read-only endpoints and
                            stage raw + normalized output in the sandboxed
                            <span class="font-mono">shopee_api_connections</span> row — never in the production
                            <span class="font-mono">marketplace_orders</span>, <span class="font-mono">marketplace_income</span> or
                            <span class="font-mono">order_cost_allocations</span> tables. Credentials are stored encrypted and are
                            never rendered in the UI.
                        </Message>

                        <Message v-if="lab.error" :severity="lab.rateLimited ? 'warn' : 'error'" :closable="false">
                            <span v-if="lab.rateLimited" class="font-semibold">Rate limited by Shopee. </span>
                            {{ lab.error }}
                        </Message>

                        <Card>
                            <template #title>
                                <h2 class="text-xl font-semibold">Connection Status</h2>
                            </template>
                            <template #content>
                                <div class="flex flex-col gap-4">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <Tag
                                            :value="configStatus?.configured ? 'Configured' : 'Not configured'"
                                            :severity="configStatus?.configured ? 'success' : 'warn'"
                                        />
                                        <Tag
                                            v-if="conn"
                                            :value="conn.connected ? 'Shop connected' : 'Shop not linked'"
                                            :severity="conn.connected ? 'success' : 'secondary'"
                                        />
                                        <span v-if="conn?.shop_name" class="text-sm text-slate-600">
                                            Shop: <span class="font-semibold">{{ conn.shop_name }}</span>
                                        </span>
                                        <span class="text-sm text-slate-600">
                                            Environment: <span class="font-mono">{{ conn?.environment ?? configStatus?.environment ?? '—' }}</span>
                                        </span>
                                        <span class="text-sm text-slate-600">
                                            Region: <span class="font-mono">{{ conn?.region ?? configStatus?.region ?? '—' }}</span>
                                        </span>
                                        <span class="text-sm text-slate-600">
                                            Host: <span class="font-mono">{{ configStatus?.host ?? '—' }}</span>
                                        </span>
                                    </div>

                                    <div v-if="conn" class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                                        <span>
                                            Last sync: <span class="font-mono">{{ conn.last_sync_at ?? 'never' }}</span>
                                        </span>
                                        <Tag
                                            v-if="conn.last_sync_status"
                                            :value="conn.last_sync_status"
                                            :severity="({ success: 'success', error: 'danger', rate_limited: 'warn' } as const)[conn.last_sync_status as 'success' | 'error' | 'rate_limited'] ?? 'secondary'"
                                        />
                                        <span>
                                            Access token expiry: <span class="font-mono">{{ conn.access_token_expires_at ?? '—' }}</span>
                                        </span>
                                        <span>
                                            Staged: {{ conn.staging_order_count }} orders / {{ conn.staging_income_count }} income rows
                                        </span>
                                    </div>

                                    <Message v-if="conn?.last_sync_error" severity="warn" :closable="false">
                                        Last sync error: {{ conn.last_sync_error }}
                                    </Message>

                                    <Message v-if="conn?.staging_stale" severity="warn" :closable="false">
                                        <span class="font-semibold">Staged data is stale.</span>
                                        Last staging was {{ conn.last_staged_at ?? 'never' }}
                                        <template v-if="conn.last_sync_status === 'error' || conn.last_sync_status === 'rate_limited'">
                                            and the last sync {{ conn.last_sync_status === 'rate_limited' ? 'was rate limited' : 'failed' }}.
                                        </template>
                                        Re-sync before promoting.
                                    </Message>

                                    <Message
                                        v-if="conn && !conn.configured"
                                        severity="warn"
                                        :closable="false"
                                    >
                                        Missing: <span class="font-mono">{{ conn.missing.join(', ') }}</span>. Use
                                        <span class="font-semibold">Connect / Configure</span> below.
                                    </Message>

                                    <div class="flex items-center gap-3">
                                        <Button label="Test API Connection" icon="pi pi-plug" size="small" :loading="lab.busy" :disabled="lab.busy" @click="runTest" />
                                        <Button label="Clear" icon="pi pi-times" size="small" severity="secondary" :loading="lab.busy" :disabled="lab.busy" @click="clearLab" />
                                    </div>
                                    <Message
                                        v-if="testResult"
                                        :severity="testResult.ok ? 'success' : 'error'"
                                        :closable="false"
                                    >
                                        {{ testResult.ok ? (testResult.message ?? 'Connected to Shopee Open API.') : (testResult.error ?? 'Connection failed.') }}
                                    </Message>
                                    <pre v-if="testResult?.raw" class="max-h-80 overflow-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-100">{{ prettyJson(testResult.raw) }}</pre>
                                </div>
                            </template>
                        </Card>

                        <Card class="border-t-4 border-t-emerald-500">
                            <template #title>
                                <h2 class="text-xl font-semibold">Connect / Configure</h2>
                            </template>
                            <template #content>
                                <div class="flex flex-col gap-4">
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">Partner ID</label>
                                            <InputText v-model="connectParams.partner_id" placeholder="Shopee Open Platform partner_id" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">Partner Key</label>
                                            <InputText v-model="connectParams.partner_key" type="password" placeholder="partner_key" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">Environment</label>
                                            <Select v-model="connectParams.environment" :options="[{ label: 'Production', value: 'production' }, { label: 'Sandbox', value: 'sandbox' }]" option-label="label" option-value="value" class="w-full" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">Region</label>
                                            <InputText v-model="connectParams.region" placeholder="e.g. global, my, id" />
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <Button label="Save Configuration" icon="pi pi-save" size="small" :loading="lab.busy" :disabled="lab.busy" @click="saveConnect" />
                                        <Button label="Get Authorization Link" icon="pi pi-external-link" size="small" severity="secondary" :loading="lab.busy" :disabled="lab.busy" @click="getAuthLink" />
                                    </div>
                                    <div v-if="authLink" class="flex flex-col gap-2">
                                        <a
                                            :href="authLink"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-sm font-medium text-emerald-600 underline"
                                        >
                                            Open Shopee authorization (new tab) 
                                        </a>
                                        <p class="text-xs text-slate-500">
                                            After authorizing, Shopee redirects back and the shop is linked automatically.
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <Card>
                            <template #title>
                                <h2 class="text-xl font-semibold">Sync Sample Data</h2>
                            </template>
                            <template #content>
                                <div class="flex flex-col gap-4">
                                    <div class="grid grid-cols-3 gap-3">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">Page size (orders)</label>
                                            <InputText v-model="syncParams.page_size" inputmode="numeric" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">From</label>
                                            <InputText v-model="syncParams.date_from" type="date" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">To</label>
                                            <InputText v-model="syncParams.date_to" type="date" />
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <Button label="Sync Sample Orders" icon="pi pi-download" size="small" :loading="lab.busy" :disabled="lab.busy" @click="runSyncOrders" />
                                        <Button label="Sync Sample Income" icon="pi pi-download" size="small" severity="secondary" :loading="lab.busy" :disabled="lab.busy" @click="runSyncIncome" />
                                    </div>
                                    <Message
                                        v-if="syncOrdersResult"
                                        :severity="syncOrdersResult.ok ? 'success' : 'error'"
                                        :closable="false"
                                    >
                                        Orders staged: {{ syncOrdersResult.order_count ?? 0 }} ({{ syncOrdersResult.pages ?? 0 }} page(s))
                                        <span v-if="syncOrdersResult.capped">— sample capped at {{ syncOrdersResult.page_size ?? 5 }}/page; re-sync continues from the saved cursor.</span>
                                        <span v-if="syncOrdersResult.error">— {{ syncOrdersResult.error }}</span>
                                    </Message>
                                    <Message
                                        v-if="syncIncomeResult"
                                        :severity="syncIncomeResult.ok ? 'success' : 'error'"
                                        :closable="false"
                                    >
                                        Income rows staged: {{ syncIncomeResult.row_count ?? 0 }}
                                        <span v-if="syncIncomeResult.error">— {{ syncIncomeResult.error }}</span>
                                    </Message>
                                    <div v-if="syncOrdersResult?.normalized?.length" class="flex flex-col gap-2">
                                        <p class="text-sm text-slate-500">Staged orders (normalized preview):</p>
                                        <DataTable :value="(syncOrdersResult.normalized as NormalizedRow[])" striped-rows scrollable scroll-height="16rem" class="text-sm">
                                            <Column v-for="col in tableColumns((syncOrdersResult.normalized as NormalizedRow[]))" :key="col" :field="col" :header="col" />
                                        </DataTable>
                                    </div>
                                    <div v-if="syncIncomeResult?.normalized?.length" class="flex flex-col gap-2">
                                        <p class="text-sm text-slate-500">Staged income (normalized preview):</p>
                                        <DataTable :value="(syncIncomeResult.normalized as NormalizedRow[])" striped-rows scrollable scroll-height="16rem" class="text-sm">
                                            <Column v-for="col in tableColumns((syncIncomeResult.normalized as NormalizedRow[]))" :key="col" :field="col" :header="col" />
                                        </DataTable>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <Card class="border-t-4 border-t-indigo-500">
                            <template #title>
                                <h2 class="text-xl font-semibold">Shadow Validation</h2>
                            </template>
                            <template #content>
                                <div class="flex flex-col gap-4">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <div class="flex items-center gap-2">
                                            <label class="text-xs font-medium text-slate-500">Escrow limit</label>
                                            <InputText v-model="escrowLimit" inputmode="numeric" class="w-24" />
                                        </div>
                                        <Button label="Sync Escrow Details" icon="pi pi-download" size="small" severity="secondary" :loading="lab.busy" :disabled="lab.busy" @click="runSyncEscrow" />
                                        <Button label="Compare vs Imported Data" icon="pi pi-eye" size="small" severity="info" :loading="lab.busy" :disabled="lab.busy" @click="runValidate" />
                                    </div>
                                    <Message
                                        v-if="syncEscrowResult"
                                        :severity="syncEscrowResult.ok ? 'success' : 'error'"
                                        :closable="false"
                                    >
                                        Escrow staged: {{ syncEscrowResult.escrow_count ?? 0 }} / {{ syncEscrowResult.total_orders ?? 0 }} orders
                                        <span v-if="syncEscrowResult.capped">— sample capped at {{ syncEscrowResult.limit ?? 20 }} orders.</span>
                                        <span v-if="syncEscrowResult.error">— {{ syncEscrowResult.error }}</span>
                                        <span v-if="syncEscrowResult.rate_limited">(rate limited)</span>
                                    </Message>
                                    <div class="flex flex-col gap-3 border-t border-slate-200 pt-4">
                                        <Message v-if="conn?.staging_stale" severity="warn" :closable="false">
                                            Staged data is stale
                                            <template v-if="conn.last_sync_status === 'error' || conn.last_sync_status === 'rate_limited'">
                                                (last sync {{ conn.last_sync_status === 'rate_limited' ? 'rate limited' : 'failed' }})
                                            </template>.
                                            Promotion will be blocked until a fresh sync completes.
                                        </Message>
                                        <div class="flex flex-wrap items-center gap-3">
                                        <Checkbox v-model="promoteDryRun" :binary="true" input-id="promote-sg" />
                                        <label for="promote-sg" class="text-sm text-slate-600">Dry-run preview (writes nothing)</label>
                                        <Button
                                            label="Promote Staged Data to Production"
                                            icon="pi pi-database"
                                            size="small"
                                            severity="success"
                                            :loading="lab.busy"
                                            :disabled="lab.busy"
                                            @click="runPromote"
                                        />
                                        </div>
                                    </div>
                                    <Message
                                        v-if="promoteResult"
                                        :severity="promoteResult.ok ? 'success' : 'error'"
                                        :closable="false"
                                    >
                                        <template v-if="promoteResult.ok">
                                            <template v-if="promoteResult.dry_run">
                                                Dry-run approved: would promote {{ promoteResult.promoted?.lines ?? 0 }} line(s) / {{ promoteResult.promoted?.income ?? 0 }} income row(s).
                                            </template>
                                            <template v-else>
                                                Promoted {{ promoteResult.promoted?.lines ?? 0 }} line(s) / {{ promoteResult.promoted?.income ?? 0 }} income row(s).
                                                <span v-if="promoteResult.audit_id">Audit #{{ promoteResult.audit_id }}.</span>
                                            </template>
                                            <span v-if="promoteResult.error">— {{ promoteResult.error }}</span>
                                        </template>
                                        <template v-else>
                                            Promotion blocked: {{ promoteResult.error }}
                                        </template>
                                    </Message>
                                    <div v-if="validateResult" class="flex flex-col gap-4">
                                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                            <div v-for="view in (['orders', 'lines', 'income'] as const)" :key="view" class="flex flex-col gap-2 rounded-lg border border-slate-200 p-3">
                                                <p class="text-sm font-semibold capitalize text-slate-600">{{ view }} summary</p>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <Tag v-for="(count, status) in validateResult.summary[view]" :key="status" :value="`${labelForStatus(status as ValidationRow['status'])}: ${count}`" :severity="status === 'matched' ? 'success' : status === 'mismatched' ? 'warn' : 'danger'" />
                                                </div>
                                                <p class="text-xs text-slate-500">
                                                    API {{ validateResult.sources.api[view] ?? 0 }} · Excel {{ validateResult.sources.excel[view] ?? 0 }}
                                                </p>
                                            </div>
                                        </div>
                                        <div v-for="view in (['orders', 'lines', 'income'] as const)" :key="view" class="flex flex-col gap-2">
                                            <p class="text-sm font-semibold text-slate-600 capitalize">{{ view }} ({{ validateResult[view].length }})</p>
                                            <DataTable :value="validateResult[view]" striped-rows scrollable :scroll-height="view === 'lines' ? '20rem' : '12rem'" class="text-sm">
                                                <Column field="label" header="Reference" />
                                                <Column header="Status">
                                                    <template #body="{ data }">
                                                        <Tag :value="labelForStatus(data.status as ValidationRow['status'])" :severity="severityForStatus(data.status as ValidationRow['status'])" />
                                                    </template>
                                                </Column>
                                                <template v-if="view === 'lines'">
                                                    <Column header="Product">
                                                        <template #body="{ data }">
                                                            <span class="text-xs">{{ data.api_product_name ?? data.excel_product_name }}</span>
                                                        </template>
                                                    </Column>
                                                    <Column header="Line Identity">
                                                        <template #body="{ data }">
                                                            <div class="flex flex-col gap-0.5">
                                                                <span class="font-mono text-xs">{{ data.line_identity }}</span>
                                                                <span v-if="data.line_identity_changed" class="text-xs text-amber-600">Identity changed (from {{ data.changed_from_identity }})</span>
                                                            </div>
                                                        </template>
                                                    </Column>
                                                </template>
                                                <Column header="Differences">
                                                    <template #body="{ data }">
                                                        <span v-if="data.status === 'matched'" class="text-xs text-slate-400">—</span>
                                                        <ul v-else class="flex flex-col gap-0.5 text-xs">
                                                            <li v-for="diff in data.differences" :key="diff.field">
                                                                {{ diff.field }}: <span class="font-mono text-amber-700">{{ diff.api }}</span> vs <span class="font-mono text-slate-600">{{ diff.excel }}</span>
                                                            </li>
                                                            <li v-if="!data.differences?.length" class="text-slate-400">no field diff</li>
                                                        </ul>
                                                    </template>
                                                </Column>
                                            </DataTable>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </Card>

                        <Card>
                            <template #title>
                                <h2 class="text-xl font-semibold">Fetch Sample Orders</h2>
                            </template>
                            <template #content>
                                <div class="flex flex-col gap-4">
                                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">Status</label>
                                            <Select v-model="orderParams.order_status" :options="orderStatusOptions" option-label="label" option-value="value" class="w-full" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">Page size</label>
                                            <InputText v-model="orderParams.page_size" inputmode="numeric" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">Created from</label>
                                            <InputText v-model="orderParams.date_from" type="date" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">Created to</label>
                                            <InputText v-model="orderParams.date_to" type="date" />
                                        </div>
                                    </div>
                                    <div>
                                        <Button label="Fetch Sample Orders" icon="pi pi-download" size="small" :loading="lab.busy" :disabled="lab.busy" @click="fetchOrders" />
                                    </div>
                                    <p v-if="ordersResult" class="text-sm text-slate-500">
                                        {{ ordersResult.order_count ?? 0 }} order(s) returned. Rows are normalized to the
                                        <span class="font-mono">marketplace_orders</span> column names for comparison only — not persisted.
                                    </p>
                                    <DataTable v-if="orderRows.length" :value="orderRows" striped-rows scrollable scroll-height="20rem" class="text-sm">
                                        <Column v-for="col in tableColumns(orderRows)" :key="col" :field="col" :header="col" />
                                    </DataTable>
                                    <pre v-if="ordersResult?.raw" class="max-h-80 overflow-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-100">{{ prettyJson(ordersResult.raw) }}</pre>
                                </div>
                            </template>
                        </Card>

                        <Card>
                            <template #title>
                                <h2 class="text-xl font-semibold">Order Detail + Escrow</h2>
                            </template>
                            <template #content>
                                <div class="flex flex-col gap-4">
                                    <div class="flex flex-wrap items-end gap-3">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">Order serial number</label>
                                            <InputText v-model="orderSn" placeholder="e.g. 220404NF3CFFNY" />
                                        </div>
                                        <Button label="Fetch Order + Escrow" icon="pi pi-search" size="small" :loading="lab.busy" :disabled="lab.busy" @click="fetchDetail" />
                                    </div>
                                    <p v-if="detailResult" class="text-sm text-slate-500">
                                        Detail + escrow for <span class="font-mono">{{ detailResult.order_number }}</span>. This performs
                                        two API calls: <span class="font-mono">get_order_detail</span> and
                                        <span class="font-mono">get_escrow_detail</span>. Escrow amount and buyer total are shown for
                                        comparison with existing schema columns — not persisted.
                                    </p>
                                    <div v-if="Object.keys(detailEscrow).length" class="flex flex-wrap gap-3">
                                        <Tag v-for="(value, key) in detailEscrow" :key="key" :value="`${key}: ${value ?? '—'}`" severity="contrast" />
                                    </div>
                                    <DataTable v-if="detailHeaders.length" :value="detailHeaders" striped-rows class="text-sm">
                                        <Column v-for="col in tableColumns(detailHeaders)" :key="col" :field="col" :header="col" />
                                    </DataTable>
                                    <DataTable v-if="detailLines.length" :value="detailLines" striped-rows scrollable scroll-height="20rem" class="text-sm">
                                        <Column v-for="col in tableColumns(detailLines)" :key="col" :field="col" :header="col" />
                                    </DataTable>
                                    <pre v-if="detailResult?.raw" class="max-h-80 overflow-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-100">{{ prettyJson(detailResult.raw) }}</pre>
                                </div>
                            </template>
                        </Card>

                        <Card>
                            <template #title>
                                <h2 class="text-xl font-semibold">Income Detail</h2>
                            </template>
                            <template #content>
                                <div class="flex flex-col gap-4">
                                    <div class="flex flex-wrap items-end gap-3">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">Status</label>
                                            <Select v-model="incomeParams.status" :options="incomeStatusOptions" option-label="label" option-value="value" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">From</label>
                                            <InputText v-model="incomeParams.date_from" type="date" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-xs font-medium text-slate-500">To</label>
                                            <InputText v-model="incomeParams.date_to" type="date" />
                                        </div>
                                        <Button label="Fetch Income Detail" icon="pi pi-download" size="small" :loading="lab.busy" :disabled="lab.busy" @click="fetchIncome" />
                                    </div>
                                    <p v-if="incomeResult" class="text-sm text-slate-500">
                                        Rows are normalized to the <span class="font-mono">marketplace_income</span> column names for
                                        comparison only — not persisted.
                                    </p>
                                    <DataTable v-if="incomeRows.length" :value="incomeRows" striped-rows scrollable scroll-height="20rem" class="text-sm">
                                        <Column v-for="col in tableColumns(incomeRows)" :key="col" :field="col" :header="col" />
                                    </DataTable>
                                    <pre v-if="incomeResult?.raw" class="max-h-80 overflow-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-100">{{ prettyJson(incomeResult.raw) }}</pre>
                                </div>
                            </template>
                        </Card>
                    </div>
                </TabPanel>

                <TabPanel value="research">
                    <Card>
                        <template #title>
                            <h2 class="text-xl font-semibold">Capability Overview</h2>
                        </template>
                        <template #content>
                            <DataTable :value="props.capabilities" striped-rows table-style="min-width: 100%" class="text-sm">
                                <Column field="name" header="Capability" style="width: 18rem" />
                                <Column header="Status">
                                    <template #body="slotProps">
                                        <Tag :value="statusLabel(slotProps.data.status)" :severity="statusSeverity(slotProps.data.status)" />
                                    </template>
                                </Column>
                                <Column field="description" header="Description" />
                                <Column field="apis" header="Primary APIs" style="width: 22rem" />
                            </DataTable>
                        </template>
                    </Card>

                    <Card>
                        <template #title>
                            <h2 class="text-xl font-semibold">Endpoint Explorer</h2>
                        </template>
                        <template #content>
                            <DataTable :value="props.endpoints" striped-rows table-style="min-width: 100%" class="text-sm">
                                <Column field="endpoint" header="Endpoint" style="width: 18rem" />
                                <Column header="Method" style="width: 6rem">
                                    <template #body="slotProps">
                                        <Tag :value="slotProps.data.method" severity="contrast" />
                                    </template>
                                </Column>
                                <Column field="path" header="Path" style="width: 22rem" />
                                <Column field="purpose" header="Purpose" />
                                <Column field="pagination" header="Pagination / Params" style="width: 18rem" />
                            </DataTable>
                        </template>
                    </Card>

                    <Card>
                        <template #title>
                            <h2 class="text-xl font-semibold">Field Coverage Matrix</h2>
                        </template>
                        <template #content>
                            <Tabs v-model:value="matrixTab">
                                <TabList>
                                    <Tab value="orders">Order Report Fields</Tab>
                                    <Tab value="income">Income Report Fields</Tab>
                                </TabList>
                                <TabPanels>
                                    <TabPanel value="orders">
                                        <DataTable :value="props.fieldMatrix.orders" striped-rows table-style="min-width: 100%" class="text-sm">
                                            <Column field="field" header="Canonical Field" style="width: 16rem" />
                                            <Column field="source" header="Shopee API Source" style="width: 24rem" />
                                            <Column header="Coverage">
                                                <template #body="slotProps">
                                                    <Tag :value="statusLabel(slotProps.data.status)" :severity="statusSeverity(slotProps.data.status)" />
                                                </template>
                                            </Column>
                                            <Column field="note" header="Note" />
                                        </DataTable>
                                    </TabPanel>
                                    <TabPanel value="income">
                                        <DataTable :value="props.fieldMatrix.income" striped-rows table-style="min-width: 100%" class="text-sm">
                                            <Column field="field" header="Canonical Field" style="width: 16rem" />
                                            <Column field="source" header="Shopee API Source" style="width: 24rem" />
                                            <Column header="Coverage">
                                                <template #body="slotProps">
                                                    <Tag :value="statusLabel(slotProps.data.status)" :severity="statusSeverity(slotProps.data.status)" />
                                                </template>
                                            </Column>
                                            <Column field="note" header="Note" />
                                        </DataTable>
                                    </TabPanel>
                                </TabPanels>
                            </Tabs>
                        </template>
                    </Card>

                    <Card>
                        <template #title>
                            <h2 class="text-xl font-semibold">Importer Replacement Analysis</h2>
                        </template>
                        <template #content>
                            <div class="flex flex-col gap-4">
                                <DataTable :value="props.importRates" striped-rows table-style="min-width: 100%" class="text-sm">
                                    <Column field="report" header="Report" style="width: 14rem" />
                                    <Column header="Feasibility" style="width: 10rem">
                                        <template #body="slotProps">
                                            <Tag :value="slotProps.data.rating" :severity="statusSeverity(slotProps.data.severity)" />
                                        </template>
                                    </Column>
                                    <Column field="summary" header="Summary" />
                                    <Column field="caveats" header="Caveats" />
                                </DataTable>
                                <p class="text-sm text-slate-500">
                                    Conclusion: the Shopee Open API can replace both Excel imports. Order data is pulled via a list-then-detail fan-out,
                                    while income data maps almost 1:1 to Seller Center Income Details. The main gaps are the masked delivery-address fields
                                    (sensitive-data approval) and the absence of <span class="font-mono">voucher_code</span>/<span class="font-mono">product_id</span>
                                    at income granularity. The recommended architecture normalizes API output into the existing
                                    <span class="font-mono">marketplace_orders</span> / <span class="font-mono">marketplace_income</span> schema so the Phase 3
                                    reconciliation and Phase 4 HPP/Mapping/allocation pipeline runs unchanged, with Excel import retained as a fallback.
                                </p>
                            </div>
                        </template>
                    </Card>

                    <Card>
                        <template #title>
                            <h2 class="text-xl font-semibold">Integration Readiness</h2>
                        </template>
                        <template #content>
                            <DataTable :value="props.readiness" striped-rows table-style="min-width: 100%" class="text-sm">
                                <Column field="item" header="Checklist Item" />
                                <Column header="Status">
                                    <template #body="slotProps">
                                        <Tag :value="statusLabel(slotProps.data.status)" :severity="statusSeverity(slotProps.data.status)" />
                                    </template>
                                </Column>
                                <Column field="note" header="Note" />
                            </DataTable>
                        </template>
                    </Card>

                    <Card>
                        <template #title>
                            <h2 class="text-xl font-semibold">Recommended Architecture</h2>
                        </template>
                        <template #content>
                            <ol class="flex list-decimal flex-col gap-3 pl-6 text-sm text-slate-600">
                                <li v-for="step in props.architecture" :key="step">{{ step }}</li>
                            </ol>
                        </template>
                    </Card>

                    <Message severity="secondary" :closable="false">
                        {{ props.scope.disclaimer }}
                    </Message>
                </TabPanel>
            </TabPanels>
        </Tabs>
    </div>
</template>