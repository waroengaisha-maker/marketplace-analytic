<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Tag from 'primevue/tag'

type User = {
    name: string
    email: string
    username: string
    phone: string | null
    account_status: string
    subscription_status: string
    payment_status: string
    trial_started_at: string | null
    trial_ends_at: string | null
    subscription_ends_at: string | null
}

const page = usePage<{ user: User }>()

const formatDate = (value: string | null) => {
    if (!value) {
        return 'Belum ditentukan'
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(value))
}

const statusLabel = (value: string) => value.replaceAll('_', ' ')
</script>

<template>
    <Head title="Akun & Langganan" />

    <div class="flex w-full min-w-0 flex-col gap-6">
        <div>
            <!-- <Tag value="ACCOUNT" severity="secondary" /> -->
            <h1 class="mt-2 text-3xl font-bold">Akun & Langganan</h1>
            <p class="mt-2 text-color-secondary">Informasi akun, masa trial, subscription, dan pembayaran.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Card>
                <template #title>Informasi Akun</template>
                <template #content>
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-color-secondary">Nama</dt><dd class="font-medium">{{ page.props.user.name }}</dd></div>
                        <div><dt class="text-color-secondary">Username</dt><dd class="font-medium">{{ page.props.user.username }}</dd></div>
                        <div><dt class="text-color-secondary">Email</dt><dd class="font-medium">{{ page.props.user.email }}</dd></div>
                        <div><dt class="text-color-secondary">Nomor handphone</dt><dd class="font-medium">{{ page.props.user.phone || 'Belum diisi' }}</dd></div>
                    </dl>
                </template>
            </Card>

            <Card>
                <template #title>Status Akses</template>
                <template #content>
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-color-secondary">Status akun</dt><dd class="font-medium capitalize">{{ statusLabel(page.props.user.account_status) }}</dd></div>
                        <div><dt class="text-color-secondary">Status subscription</dt><dd class="font-medium capitalize">{{ statusLabel(page.props.user.subscription_status) }}</dd></div>
                        <div><dt class="text-color-secondary">Status pembayaran</dt><dd class="font-medium capitalize">{{ statusLabel(page.props.user.payment_status) }}</dd></div>
                    </dl>
                </template>
            </Card>

            <Card>
                <template #title>Periode Akses</template>
                <template #content>
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-color-secondary">Trial dimulai</dt><dd class="font-medium">{{ formatDate(page.props.user.trial_started_at) }}</dd></div>
                        <div><dt class="text-color-secondary">Trial berakhir</dt><dd class="font-medium">{{ formatDate(page.props.user.trial_ends_at) }}</dd></div>
                        <div><dt class="text-color-secondary">Subscription berakhir</dt><dd class="font-medium">{{ formatDate(page.props.user.subscription_ends_at) }}</dd></div>
                    </dl>
                </template>
            </Card>
        </div>
    </div>
</template>
