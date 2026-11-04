<script setup lang="ts">
import type { WorkspaceRole } from '~/types/access'
defineProps<{ id: string; disabled?: boolean }>()
const model = defineModel<WorkspaceRole>({ required: true })
const { roles, labels } = useWorkspaceRoles()
</script>
<template>
  <select :id="id" v-model="model" :disabled="disabled" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 focus-visible:outline-2 focus-visible:outline-sky-600 disabled:opacity-50">
    <option v-for="role in roles" :key="role" :value="role">{{ labels[role] }}</option>
  </select>
</template>
