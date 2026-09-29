import { reactive, ref } from 'vue'
import axios from 'axios'
import { fastCache } from '../stores/fastCache'
import { useLang } from '../utils/useLang'

const defaultSettings = {
  institutionName: 'RPITSSR',
  portalTitle: 'RPITSSR',
  portalSubtitle: 'SCHOLARSHIP',
  logoUrl: '/logo.png',
  academicYear: '2026-2027',
  timezone: 'Asia/Phnom_Penh',
  defaultLanguage: 'kh',
  sessionTimeoutMinutes: 60,
  allowRegistration: false,
  forceStrongPassword: true,
  antiCheatPause: true,
  autosaveIntervalSeconds: 3,
  autoSubmitOnTimeout: true,
  phpVersion: '',
  laravelVersion: '',
  databaseDriver: 'MySQL',
  serverTime: ''
}

let localPersisted = {}
if (typeof window !== 'undefined') {
  try {
    const raw = localStorage.getItem('app_public_settings_cache')
    if (raw) localPersisted = JSON.parse(raw)
  } catch (e) {}
}

const cached = fastCache.get('system_settings') || localPersisted
const settings = reactive({ ...defaultSettings, ...cached })
const isLoaded = ref(false)
const isLoading = ref(false)

const normalizeBoolean = (val, defaultVal = false) => {
  if (val === undefined || val === null) return defaultVal
  if (typeof val === 'boolean') return val
  if (typeof val === 'string') {
    const lower = val.trim().toLowerCase()
    if (lower === 'false' || lower === '0' || lower === 'off' || lower === 'no') return false
    if (lower === 'true' || lower === '1' || lower === 'on' || lower === 'yes') return true
  }
  if (typeof val === 'number') return val !== 0
  return Boolean(val)
}

const syncLocalSettings = (newSettings) => {
  if (!newSettings || typeof newSettings !== 'object') return
  const normalized = { ...newSettings }
  if ('allowRegistration' in normalized) {
    normalized.allowRegistration = normalizeBoolean(normalized.allowRegistration, false)
  }
  if ('forceStrongPassword' in normalized) {
    normalized.forceStrongPassword = normalizeBoolean(normalized.forceStrongPassword, true)
  }
  if ('antiCheatPause' in normalized) {
    normalized.antiCheatPause = normalizeBoolean(normalized.antiCheatPause, true)
  }
  if ('autoSubmitOnTimeout' in normalized) {
    normalized.autoSubmitOnTimeout = normalizeBoolean(normalized.autoSubmitOnTimeout, true)
  }
  Object.assign(settings, normalized)
  if (normalized.institutionName && !normalized.portalTitle) {
    settings.portalTitle = normalized.institutionName
  }
  if (typeof document !== 'undefined' && settings.institutionName) {
    document.title = settings.institutionName
  }
  fastCache.set('system_settings', { ...settings })
  if (typeof window !== 'undefined') {
    try {
      localStorage.setItem('app_public_settings_cache', JSON.stringify(settings))
    } catch (e) {}
  }
}

if (typeof window !== 'undefined') {
  window.addEventListener('storage', (e) => {
    if (e.key === 'app_public_settings_cache' && e.newValue) {
      try {
        const fresh = JSON.parse(e.newValue)
        syncLocalSettings(fresh)
      } catch (err) {}
    }
  })

  window.addEventListener('settings_updated', (e) => {
    if (e.detail) {
      syncLocalSettings(e.detail)
    }
  })

  if (typeof BroadcastChannel !== 'undefined') {
    try {
      const channel = new BroadcastChannel('rtc_exam_realtime_sync')
      channel.addEventListener('message', (event) => {
        if (event.data?.type === 'settings_updated' && event.data?.data) {
          syncLocalSettings(event.data.data)
        }
      })
    } catch (e) {}
  }
}

export function useSettings() {
  const { setLang } = useLang()

  const fetchSettings = async (force = false) => {
    if (isLoaded.value && !force) return settings
    isLoading.value = true
    try {
      const res = await axios.get('/api/public-settings')
      const data = res.data.settings || {}
      syncLocalSettings(data)
      isLoaded.value = true

      // If user hasn't set explicit language preference, sync with system default
      if (!localStorage.getItem('app_lang') && data.defaultLanguage) {
        setLang(data.defaultLanguage)
      }
    } catch (e) {
      // Fallback to cache or defaults
    } finally {
      isLoading.value = false
    }
    return settings
  }

  return {
    settings,
    isLoaded,
    isLoading,
    fetchSettings
  }
}
