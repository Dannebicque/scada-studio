<script setup lang="ts">
const { t } = useAppLocale()
</script>

<template>
  <main>
    <header class="mb-8">
      <p class="text-sm font-semibold uppercase tracking-widest text-sky-700">SCADA Studio / L3</p>
      <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ t('connectors') }}</h1>
      <p class="mt-2 max-w-2xl text-slate-600">{{ t('connectorsIntro') }}</p>
    </header>
    <div class="grid gap-5 lg:grid-cols-2">
      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="mqtt-title">
        <div class="flex items-center justify-between gap-3">
          <h2 id="mqtt-title" class="text-xl font-semibold">{{ t('mqtt') }}</h2>
          <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">{{ t('available') }}</span>
        </div>
        <p class="mt-3 text-slate-600">{{ t('mqttDescription') }}</p>
        <dl class="mt-5 space-y-4 text-sm">
          <div><dt class="font-medium text-slate-500">{{ t('mqttTopic') }}</dt><dd class="mt-1 break-all rounded-lg bg-slate-100 p-3 font-mono">scada/{workspace}/{source}/{tag}</dd></div>
          <div><dt class="font-medium text-slate-500">{{ t('mqttPort') }}</dt><dd class="mt-1 font-mono">127.0.0.1:1883</dd></div>
        </dl>
      </section>
      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="opc-title">
        <div class="flex items-center justify-between gap-3">
          <h2 id="opc-title" class="text-xl font-semibold">{{ t('opcua') }}</h2>
          <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ t('planned') }}</span>
        </div>
        <p class="mt-3 text-slate-600">{{ t('opcuaDescription') }}</p>
      </section>
    </div>
    <div class="mt-6 grid gap-5 lg:grid-cols-2">
      <section class="rounded-2xl border border-slate-200 bg-white p-6" aria-labelledby="configuration-title">
        <h2 id="configuration-title" class="text-lg font-semibold">{{ t('configuration') }}</h2>
        <p class="mt-2 text-slate-600">{{ t('configurationText') }}</p>
      </section>
      <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6" aria-labelledby="security-title">
        <h2 id="security-title" class="text-lg font-semibold text-amber-950">{{ t('security') }}</h2>
        <p class="mt-2 text-amber-900">{{ t('securityText') }}</p>
      </section>
    </div>
  </main>
</template>
