import { ref, computed } from 'vue'
import axios from 'axios'

const currentUser = ref(null)
const userPermissions = ref([])
const isLoadingUser = ref(false)

export function usePermissions() {
  const fetchUser = async (force = false) => {
    if (currentUser.value && !force) return currentUser.value
    isLoadingUser.value = true
    try {
      const res = await axios.get('/api/profile')
      currentUser.value = res.data.user || null
      userPermissions.value = res.data.permissions || []
    } catch (e) {
      currentUser.value = null
      userPermissions.value = []
    } finally {
      isLoadingUser.value = false
    }
    return currentUser.value
  }

  const isSuperAdmin = computed(() => {
    const role = (currentUser.value?.role || '').toLowerCase().replace(/\s+/g, '')
    return role === 'superadmin'
  })

  const isAdmin = computed(() => {
    const role = (currentUser.value?.role || '').toLowerCase().replace(/\s+/g, '')
    return role === 'admin' || role === 'superadmin' || isSuperAdmin.value
  })

  const isStudent = computed(() => {
    const role = (currentUser.value?.role || '').toLowerCase()
    return role === 'student'
  })

  const roleName = computed(() => {
    if (isSuperAdmin.value) return 'Super Admin'
    if (isAdmin.value) return 'Admin'
    return currentUser.value?.role || 'Student'
  })

  /**
   * Permission checker:
   * SuperAdmin has full unrestricted access.
   * Admin is dynamically evaluated against the saved Permission Matrix.
   * Student has student examinee permissions.
   */
  const can = (moduleName, action = 'view') => {
    if (isSuperAdmin.value) return true

    const role = (currentUser.value?.role || '').toLowerCase().replace(/\s+/g, '')
    if (role === 'admin' || role === '') {
      const matrix = userPermissions.value || []
      if (!matrix.length) return true

      const moduleAliases = {
        'exam sessions': 'skills & groups',
        'examsessions': 'skills & groups',
        'skills and groups': 'skills & groups',
        'tests': 'exams'
      }

      let target = (moduleName || '').toLowerCase().trim().replace(/&/g, 'and').replace(/\s+/g, ' ')
      if (moduleAliases[target]) {
        target = moduleAliases[target].replace(/&/g, 'and')
      }

      const item = matrix.find(m => {
        let mod = (m.module || '').toLowerCase().trim().replace(/&/g, 'and').replace(/\s+/g, ' ')
        if (moduleAliases[mod]) {
          mod = moduleAliases[mod].replace(/&/g, 'and')
        }
        return mod === target
      })

      if (item && item[action] !== undefined) {
        return !!item[action]
      }
      return true
    }

    if (role === 'student') {
      const mod = (moduleName || '').toLowerCase()
      return mod === 'exam' || mod === 'results'
    }

    return true
  }

  return {
    currentUser,
    userPermissions,
    isLoadingUser,
    isSuperAdmin,
    isAdmin,
    isStudent,
    roleName,
    fetchUser,
    can
  }
}
