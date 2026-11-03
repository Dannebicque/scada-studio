<script setup lang="ts">
const { locale } = useAppLocale()
watch(locale, language => navigateTo('/docs/' + language + '/index', { replace: true }), { immediate: true })
</script>
<template>
  <p role="status">Documentation…</p>
</template>
