<script setup lang="ts">
import type { WorkspaceMember, WorkspaceRole } from '~/types/access'
defineProps<{ members: WorkspaceMember[]; busy: boolean; ownerCount: number }>()
const emit = defineEmits<{ changeRole: [id: string, role: WorkspaceRole]; remove: [member: WorkspaceMember] }>()
const { locale } = useAppLocale()
const editing = ref('')
const editRole = ref<WorkspaceRole>('viewer')
function edit(member: WorkspaceMember) { editing.value = member.userId; editRole.value = member.role }
function save(member: WorkspaceMember) {
  if (editRole.value !== member.role) emit('changeRole', member.userId, editRole.value)
  editing.value = ''
}
</script>
<template>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[32rem] text-left text-sm">
      <thead class="bg-slate-50 text-slate-600"><tr>
        <th scope="col" class="px-5 py-3">{{ locale === 'fr' ? 'Utilisateur' : 'User' }}</th>
        <th scope="col" class="px-5 py-3">{{ locale === 'fr' ? 'Autorisation' : 'Permission' }}</th>
        <th scope="col" class="px-5 py-3">{{ locale === 'fr' ? 'Actions' : 'Actions' }}</th>
      </tr></thead>
      <tbody class="divide-y divide-slate-100">
        <tr v-for="member in members" :key="member.userId">
          <td class="px-5 py-4">{{ member.email }}</td>
          <td class="px-5 py-4"><AccessRoleBadge :role="member.role" /></td>
          <td class="px-5 py-4">
            <div v-if="editing === member.userId" class="flex flex-wrap gap-2">
              <label :for="'role-' + member.userId" class="sr-only">{{ locale === 'fr' ? 'Nouveau rôle' : 'New role' }}</label>
              <AccessRoleSelect :id="'role-' + member.userId" v-model="editRole" :disabled="busy" />
              <button type="button" :disabled="busy || (member.role === 'owner' && ownerCount <= 1 && editRole !== 'owner')" class="rounded-lg bg-sky-800 px-3 py-2 text-white disabled:opacity-50" @click="save(member)">{{ locale === 'fr' ? 'Enregistrer' : 'Save' }}</button>
              <button type="button" :disabled="busy" class="rounded-lg border px-3 py-2" @click="editing = ''">{{ locale === 'fr' ? 'Annuler' : 'Cancel' }}</button>
            </div>
            <div v-else class="flex flex-wrap gap-2">
              <button type="button" :disabled="busy" class="rounded-lg border px-3 py-2" @click="edit(member)">{{ locale === 'fr' ? 'Modifier' : 'Edit' }}</button>
              <button type="button" :disabled="busy || (member.role === 'owner' && ownerCount <= 1)" class="rounded-lg border border-red-200 px-3 py-2 text-red-800 disabled:opacity-40" @click="emit('remove', member)">{{ locale === 'fr' ? 'Retirer' : 'Remove' }}</button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
