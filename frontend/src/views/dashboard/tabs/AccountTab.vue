<script setup>
import RoleManagementTab from '@/views/dashboard/tabs/RoleManagementTab.vue'
import UserManagementTab from '@/views/dashboard/tabs/UserManagementTab.vue'
import { ref } from 'vue'

const activeSection = ref('roles') // 'roles' or 'users'
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="bg-gray-800/70 backdrop-blur-lg rounded-xl shadow-xl p-6 space-y-6">
      <div class="flex items-center justify-between">
        <h2 class="text-2xl font-semibold text-white">Akun</h2>
        <div class="flex gap-2">
          <button
            @click="activeSection = 'roles'"
            :class="['px-4 py-2 rounded transition-colors', activeSection === 'roles' ? 'bg-indigo-600 text-white' : 'bg-slate-700 text-gray-300']"
          >
            Role & Permission
          </button>
          <button
            @click="activeSection = 'users'"
            :class="['px-4 py-2 rounded transition-colors', activeSection === 'users' ? 'bg-indigo-600 text-white' : 'bg-slate-700 text-gray-300']"
          >
            Management User
          </button>
        </div>
      </div>

      <div v-if="activeSection === 'roles'">
        <RoleManagementTab />
      </div>
      <div v-else>
        <UserManagementTab />
      </div>
    </div>
  </div>
</template>

<style scoped>
/* No extra CSS – Tailwind utilities handle styling */
</style>
