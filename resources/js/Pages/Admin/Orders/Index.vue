<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import Card from 'primevue/card'
</script>

<template>
    <Head title="Orders" />

    <div class="flex flex-col gap-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Super Admin</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-900 dark:text-slate-100">Orders</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Contoh awal menu default yang akan tersedia konsisten di setiap page.
            </p>
        </div>

        <Card>
            <template #content>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        <i class="pi pi-shopping-cart" aria-hidden="true" />
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">Orders</div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Konten minimal untuk review struktur page dan pola menu.
                        </div>
                    </div>
                </div>
            </template>
        </Card>
    </div>
</template>
