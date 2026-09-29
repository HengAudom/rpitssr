import { onMounted, onUnmounted } from 'vue'

const SYNC_CHANNEL_NAME = 'online_exam_realtime_sync'

// Global broadcast channel for cross-tab instant synchronization
let broadcastChannel = null
try {
  if (typeof window !== 'undefined' && 'BroadcastChannel' in window) {
    broadcastChannel = new BroadcastChannel(SYNC_CHANNEL_NAME)
  }
} catch (e) {
  console.warn('BroadcastChannel not supported', e)
}

/**
 * Trigger an instant cross-tab refresh signal across all open tabs.
 * @param {string} eventName - e.g. 'tests_updated', 'students_updated', 'results_updated'
 */
export function broadcastSync(eventName = 'data_updated') {
  if (broadcastChannel) {
    try {
      broadcastChannel.postMessage({ type: eventName, timestamp: Date.now() })
    } catch (e) {}
  }
}

/**
 * Real-time polling & live sync composable for Vue components.
 * 
 * @param {Function} fetchCallback - The function to call to refresh data (async or sync).
 * @param {Object} options
 * @param {number} [options.interval=5000] - Polling interval in milliseconds (default: 5000ms).
 * @param {boolean} [options.immediate=true] - Whether to call fetchCallback immediately on mount.
 * @param {string[]} [options.listenEvents] - Specific broadcast events to listen to.
 */
export function useRealtimePoll(fetchCallback, options = {}) {
  const {
    interval = 5000,
    immediate = true,
    listenEvents = []
  } = options

  let timerId = null
  let isFetching = false

  const executeFetch = async (isBackground = true) => {
    if (isFetching) return
    if (typeof document !== 'undefined' && document.hidden) return

    try {
      isFetching = true
      await fetchCallback(isBackground)
    } catch (err) {
      // Silently catch polling errors so user experience is not disrupted
    } finally {
      isFetching = false
    }
  }

  const startPolling = () => {
    stopPolling()
    timerId = setInterval(() => {
      executeFetch(true)
    }, interval)
  }

  const stopPolling = () => {
    if (timerId) {
      clearInterval(timerId)
      timerId = null
    }
  }

  const handleVisibilityChange = () => {
    if (typeof document !== 'undefined' && !document.hidden) {
      executeFetch(true)
    }
  }

  const handleWindowFocus = () => {
    executeFetch(true)
  }

  const handleBroadcastMessage = (event) => {
    if (!event?.data) return
    const eventType = event.data.type
    if (!listenEvents.length || listenEvents.includes(eventType) || eventType === 'data_updated') {
      executeFetch(true)
    }
  }

  onMounted(() => {
    if (immediate) {
      executeFetch(false)
    }

    startPolling()

    if (typeof window !== 'undefined') {
      window.addEventListener('focus', handleWindowFocus)
      document.addEventListener('visibilitychange', handleVisibilityChange)
    }

    if (broadcastChannel) {
      broadcastChannel.addEventListener('message', handleBroadcastMessage)
    }
  })

  onUnmounted(() => {
    stopPolling()

    if (typeof window !== 'undefined') {
      window.removeEventListener('focus', handleWindowFocus)
      document.removeEventListener('visibilitychange', handleVisibilityChange)
    }

    if (broadcastChannel) {
      broadcastChannel.removeEventListener('message', handleBroadcastMessage)
    }
  })

  return {
    refreshNow: () => executeFetch(false),
    broadcastSync
  }
}
