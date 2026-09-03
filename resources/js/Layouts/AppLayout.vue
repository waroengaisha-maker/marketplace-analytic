<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex h-16 items-center justify-between px-6">
                <Link href="/" class="font-bold text-slate-900">
                    Marketplace Analytics
                </Link>

                <nav class="flex items-center gap-4 text-sm">
                    <Link href="/" class="text-slate-600 hover:text-slate-900">
                        Dashboard
                    </Link>
                </nav>
            </div>
        </header>

        <main class="p-6">
            <slot />
        </main>
    </div>
</template>
