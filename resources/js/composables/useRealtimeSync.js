import { onMounted, onUnmounted } from 'vue'

const channel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('rtc_exam_realtime_sync') : null

/**
 * Broadcast an event to all open tabs / windows across the browser instantly.
 * @param {string} type - Event identifier (e.g. 'test_updated', 'student_updated', 'session_updated', 'exam_submitted')
 * @param {any} data - Optional payload
 */
export function notifyRealtimeChange(type = 'general', data = null) {
  try {
    if (channel) {
      channel.postMessage({ type, data, timestamp: Date.now() })
    }
  } catch (e) {
    console.warn('Realtime broadcast error:', e)
  }
}

/**
 * Realtime synchronization composable.
 * Listens to BroadcastChannel events, window focus, visibility changes, and runs smart silent polling.
 * @param {Function} refreshCallback - Function to re-fetch data
 * @param {number} intervalMs - Polling interval in ms (default 3000ms = 3s)
 */
export function useRealtimeSync(refreshCallback, intervalMs = 3000) {
  let timer = null
  let isFetching = false

  const triggerRefresh = async (reason = 'sync') => {
    if (isFetching || !refreshCallback || typeof refreshCallback !== 'function') return
    try {
      isFetching = true
      await refreshCallback(reason)
    } catch (e) {
      // ignore background refresh errors
    } finally {
      isFetching = false
    }
  }

  const handleMessage = (event) => {
    triggerRefresh(event.data?.type || 'broadcast')
  }

  const handleFocus = () => {
    triggerRefresh('focus')
  }

  const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
      triggerRefresh('visibility')
    }
  }

  onMounted(() => {
    if (channel) {
      channel.addEventListener('message', handleMessage)
    }
    window.addEventListener('focus', handleFocus)
    document.addEventListener('visibilitychange', handleVisibilityChange)

    if (intervalMs && intervalMs > 0) {
      timer = setInterval(() => {
        if (document.visibilityState === 'visible') {
          triggerRefresh('polling')
        }
      }, intervalMs)
    }
  })

  onUnmounted(() => {
    if (channel) {
      channel.removeEventListener('message', handleMessage)
    }
    window.removeEventListener('focus', handleFocus)
    document.removeEventListener('visibilitychange', handleVisibilityChange)
    if (timer) clearInterval(timer)
  })

  return {
    triggerRefresh,
    notifyChange: notifyRealtimeChange
  }
}
