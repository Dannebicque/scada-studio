<script setup lang="ts">
import type { WorkspaceRole } from '~/types/access'
defineProps<{ role: WorkspaceRole }>()
const { labels } = useWorkspaceRoles()
</script>

<template>
  <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="role === 'owner' ? 'bg-sky-100 text-sky-900' : role === 'editor' ? 'bg-indigo-50 text-indigo-800' : 'bg-slate-100 text-slate-700'">{{ labels[role] }}</span>
</template>
