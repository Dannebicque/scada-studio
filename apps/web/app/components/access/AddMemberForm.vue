<script setup lang="ts">
import type { WorkspaceRole } from '~/types/access'
defineProps<{ busy: boolean }>()
const emit = defineEmits<{ add: [email: string, role: WorkspaceRole] }>()
const { locale } = useAppLocale()
const email = ref('')
const role = ref<WorkspaceRole>('viewer')
function submit() { if (email.value.trim()) emit('add', email.value.trim(), role.value) }
defineExpose({ reset: () => { email.value = ''; role.value = 'viewer' } })
</script>
<template>
  <form class="flex flex-wrap items-end gap-3" @submit.prevent="submit">
    <div class="min-w-56 flex-1">
      <label for="new-member-email" class="block text-sm font-medium">{{ locale === 'fr' ? 'Adresse e-mail' : 'Email address' }}</label>
      <input id="new-member-email" v-model.trim="email" required type="email" autocomplete="off" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">
    </div>
    <div>
      <label for="new-member-role" class="block text-sm font-medium">{{ locale === 'fr' ? 'Rôle' : 'Role' }}</label>
      <AccessRoleSelect id="new-member-role" v-model="role" :disabled="busy" class="mt-1" />
    </div>
    <button type="submit" :disabled="busy" class="rounded-lg bg-sky-800 px-4 py-2.5 font-medium text-white disabled:opacity-50">{{ locale === 'fr' ? 'Ajouter' : 'Add member' }}</button>
  </form>
</template>
