<template>
  <router-view />
  <Toast />
</template>

<script setup>
import { onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { setupAutoLogout } from './utils/autoLogout'
import { useSettings } from './composables/useSettings'
import Toast from './components/ui/Toast.vue'

const router = useRouter()
const route = useRoute()
const { fetchSettings } = useSettings()

onMounted(() => {
  fetchSettings(true)
  const result = setupAutoLogout(router)
  const cleanup = result.cleanup
  const resetTimer = result.resetTimer

  // Watch for route changes to ensure the inactivity timer is active
  watch(() => route.path, () => {
    resetTimer()
  })

  onUnmounted(() => {
    if (cleanup) cleanup()
  })
})
</script>
