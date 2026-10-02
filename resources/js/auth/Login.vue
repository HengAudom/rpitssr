<template>
  <PublicLayout>
    <Card padding="none" class="p-5 sm:p-8 shadow-soft-lg border border-slate-200/80 rounded-2xl sm:rounded-3xl">
      <!-- Form Header -->
      <div class="mb-4 sm:mb-6 space-y-1 sm:space-y-1.5">
        <div class="flex items-center justify-between gap-2">
          <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">
            {{ isAdminMode ? (lang === 'kh' ? 'ចូលផ្ទាំងគ្រប់គ្រង' : 'Admin Sign In') : (lang === 'kh' ? 'ចូលប្រឡង' : 'Candidate Sign In') }}
          </h2>
          <button
            type="button"
            @click="toggleMode"
            class="text-[11px] sm:text-xs font-semibold px-2.5 py-1 rounded-lg border border-slate-200 text-slate-600 hover:text-blue-600 hover:border-blue-300 hover:bg-slate-50 transition-all flex items-center gap-1 select-none"
          >
            <span class="material-symbols-outlined text-xs">{{ isAdminMode ? 'school' : 'admin_panel_settings' }}</span>
            <span>{{ isAdminMode ? (lang === 'kh' ? 'ទម្រង់សិស្ស' : 'Student Mode') : (lang === 'kh' ? 'ទម្រង់ Admin' : 'Admin Mode') }}</span>
          </button>
        </div>
        <p class="text-xs sm:text-sm text-slate-500">
          {{ isAdminMode ? (lang === 'kh' ? 'សូមបញ្ចូលពាក្យសម្ងាត់របស់អ្នកគ្រប់គ្រងដើម្បីបន្ត' : 'Please enter admin password to continue') : (lang === 'kh' ? 'បញ្ចូលលេខសម្គាល់សិស្ស ដើម្បីចូលបន្ទប់ប្រឡង' : 'Enter your Student ID to access the examination portal') }}
        </p>
      </div>

      <!-- Error Alert -->
      <div
        v-if="errorMessage"
        class="mb-4 sm:mb-6 p-3 bg-red-50/90 border border-red-200/90 rounded-xl sm:rounded-2xl flex items-center gap-2.5 text-xs text-red-600 font-medium"
      >
        <span class="material-symbols-outlined text-base text-red-500 shrink-0">error</span>
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Login Form -->
      <form @submit.prevent="handleLogin" class="space-y-3 sm:space-y-4">
        <!-- Student ID / Username Input (Single Universal Input) -->
        <Input
          ref="usernameInputRef"
          v-model="form.username"
          :label="isAdminMode ? (lang === 'kh' ? 'ឈ្មោះគណនី' : 'Username') : (lang === 'kh' ? 'លេខសម្គាល់សិស្ស' : 'Student ID')"
          :icon="isAdminMode ? 'person' : 'badge'"
          required
          :placeholder="isAdminMode ? (lang === 'kh' ? 'បញ្ចូលឈ្មោះគណនី' : 'Enter admin username') : (lang === 'kh' ? 'ឧ. SRXXXXXXXXXX' : 'e.g. SRXXXXXXXXXX')"
          @input="onUsernameInput"
        />

        <!-- Password (Auto-revealed smoothly when in admin mode) -->
        <Transition
          enter-active-class="transition-all duration-200 ease-out"
          enter-from-class="opacity-0 -translate-y-2 max-h-0 overflow-hidden"
          enter-to-class="opacity-100 translate-y-0 max-h-40 overflow-visible"
          leave-active-class="transition-all duration-150 ease-in"
          leave-from-class="opacity-100 translate-y-0 max-h-40 overflow-visible"
          leave-to-class="opacity-0 -translate-y-2 max-h-0 overflow-hidden"
        >
          <div v-if="isAdminMode" class="space-y-1">
            <PasswordInput
              ref="passwordInputRef"
              v-model="form.password"
              :label="lang === 'kh' ? 'ពាក្យសម្ងាត់' : 'Password'"
              :placeholder="lang === 'kh' ? 'បញ្ចូលពាក្យសម្ងាត់' : 'Enter admin password'"
              required
              @input="errorMessage = ''"
            />
          </div>
        </Transition>

        <!-- Dynamic Guidance Note -->
        <div class="p-2.5 sm:p-3 rounded-xl sm:rounded-2xl bg-slate-50 border border-slate-100 text-[11px] text-slate-500 flex items-start gap-2 leading-relaxed">
          <span class="material-symbols-outlined text-blue-600 text-sm shrink-0 mt-0.5">
            {{ isAdminMode ? 'lock' : 'info' }}
          </span>
          <span v-if="isAdminMode">
            {{ lang === 'kh' ? '🔑 គណនីអ្នកគ្រប់គ្រង៖ សូមបញ្ចូលពាក្យសម្ងាត់ដើម្បីផ្ទៀងផ្ទាត់ និងចូលគ្រប់គ្រងប្រព័ន្ធ។' : '🔑 Admin Account: Password is required to authenticate.' }}
          </span>
          <span v-else>
            {{ lang === 'kh' ? '💡 សិស្សប្រឡង៖ គ្រាន់តែវាយបញ្ចូលលេខសម្គាល់សិស្ស (Student ID) រួចចុចចូលប្រឡង (មិនចាំបាច់មានពាក្យសម្ងាត់ទេ)។' : '💡 Students: Enter your assigned Student ID and click login (no password needed).' }}
          </span>
        </div>

        <!-- Remember Identifier Option -->
        <div class="flex items-center pt-0.5">
          <label class="flex items-center gap-2 cursor-pointer select-none group">
            <input
              type="checkbox"
              v-model="rememberUsername"
              class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 transition-colors cursor-pointer"
            />
            <span class="text-xs font-medium text-slate-600 group-hover:text-slate-900 transition-colors">
              {{ isAdminMode ? (lang === 'kh' ? 'ចងចាំឈ្មោះគណនី' : 'Remember Username') : (lang === 'kh' ? 'ចងចាំលេខសម្គាល់សិស្ស' : 'Remember Student ID') }}
            </span>
          </label>
        </div>

        <!-- Submit Button -->
        <div class="pt-0.5 sm:pt-1">
          <Button
            type="submit"
            variant="primary"
            full-width
            size="lg"
            :loading="isSubmitting"
            :icon="isAdminMode ? 'admin_panel_settings' : 'login'"
          >
            {{ isSubmitting ? t.submittingBtn : (isAdminMode ? (lang === 'kh' ? 'ចូលគ្រប់គ្រង' : 'Sign In as Admin') : (lang === 'kh' ? 'ចូលប្រឡង' : 'Enter Exam Portal')) }}
          </Button>
        </div>
      </form>
    </Card>
  </PublicLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import axios from 'axios'
import PublicLayout from '../layouts/PublicLayout.vue'
import Card from '../components/ui/Card.vue'
import Input from '../components/ui/Input.vue'
import PasswordInput from '../components/ui/PasswordInput.vue'
import Button from '../components/ui/Button.vue'
import { useLang } from '../utils/useLang'
import { setCachedUser } from '../router'

const router = useRouter()
const { lang } = useLang()

const form = reactive({
  username: '',
  password: ''
})

const rememberUsername = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref('')
const isAdminMode = ref(false)
const passwordInputRef = ref(null)

let checkDebounceTimer = null
let currentRequestId = 0

// Robust student code pattern heuristic (SR..., RTC..., STD... or pure digits >= 4)
const isStudentCode = (val) => {
  if (!val) return false
  const clean = val.trim()
  return /^(?:rtc|sr|std)[\-_]?\d+/i.test(clean) || /^(?:rtc|sr)/i.test(clean) || /^\d{4,}$/.test(clean)
}

const toggleMode = () => {
  isAdminMode.value = !isAdminMode.value
  errorMessage.value = ''
  if (isAdminMode.value) {
    nextTick(() => passwordInputRef.value?.focus?.())
  }
}

const onUsernameInput = () => {
  errorMessage.value = ''
  const val = form.username.trim()
  if (!val) {
    isAdminMode.value = false
    return
  }

  // Automatic format-based detection (zero account enumeration leaks)
  const isStudent = isStudentCode(val)
  isAdminMode.value = !isStudent

  const reqId = ++currentRequestId
  if (checkDebounceTimer) clearTimeout(checkDebounceTimer)
  checkDebounceTimer = setTimeout(async () => {
    try {
      const res = await axios.post('/api/check-identifier', { identifier: val })
      if (reqId !== currentRequestId) return
      isAdminMode.value = Boolean(res.data?.requiresPassword)
    } catch (e) {
      // Ignore background check errors
    }
  }, 100)
}

onMounted(() => {
  const saved = localStorage.getItem('saved_login_username')
  // Only restore student IDs to prevent sensitive username harvesting (F-04 Remediation)
  if (saved && isStudentCode(saved)) {
    form.username = saved
    rememberUsername.value = true
    onUsernameInput()
  } else if (saved) {
    localStorage.removeItem('saved_login_username')
  }
})

const t = computed(() => {
  if (lang.value === 'kh') {
    return {
      signIn: 'ចូលប្រព័ន្ធ',
      forgotPassword: 'ភ្លេចពាក្យសម្ងាត់?',
      submittingBtn: 'កំពុងផ្ទៀងផ្ទាត់...',
      noAccount: 'មិនទាន់មានគណនី?',
      createAccount: 'ចុះឈ្មោះបង្កើតគណនីថ្មី'
    }
  }
  return {
    signIn: 'Sign In',
    forgotPassword: 'Forgot password?',
    submittingBtn: 'Signing in...',
    noAccount: "Don't have an account?",
    createAccount: 'Create an account'
  }
})

const handleLogin = async () => {
  const identifier = form.username.trim()
  if (!identifier) {
    errorMessage.value = isAdminMode.value
      ? (lang.value === 'kh' ? 'សូមបញ្ចូលឈ្មោះគណនី' : 'Please enter admin username.')
      : (lang.value === 'kh' ? 'សូមបញ្ចូល Student ID' : 'Please enter Student ID.')
    return
  }

  if (isAdminMode.value && !form.password) {
    errorMessage.value = lang.value === 'kh' ? 'សូមបញ្ចូលពាក្យសម្ងាត់' : 'Please enter your password.'
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''

  try {
    const res = await axios.post('/api/login', {
      identifier: identifier,
      username: identifier,
      password: (isAdminMode.value || form.password) ? form.password : '',
      lang: lang.value
    })

    // Handle Remember Identifier persistence (F-04 Remediation):
    // Only persist Student IDs when explicitly requested by user.
    // Never persist admin usernames in plaintext to prevent credential harvesting.
    if (rememberUsername.value && !isAdminMode.value) {
      localStorage.setItem('saved_login_username', identifier)
    } else {
      localStorage.removeItem('saved_login_username')
    }

    localStorage.setItem('isAuthenticated', 'true')

    const userObj = res.data.user || null
    if (userObj) {
      setCachedUser(userObj)
    }

    const role = res.data.role || res.data.user?.role || (res.data.loginType === 'student' ? 'Student' : 'Admin')
    if (role) {
      localStorage.setItem('userRole', role)
    }

    if (res.data.redirect) {
      router.push(res.data.redirect)
    } else if (['Admin', 'SuperAdmin', 'Super Admin'].includes(role)) {
      router.push('/admin/dashboard')
    } else {
      router.push('/student')
    }
  } catch (err) {
    const rawMsg = err.response?.data?.message || ''

    if (rawMsg && (rawMsg.includes('Password is required') || rawMsg.includes('ពាក្យសម្ងាត់') || rawMsg.includes('Admin'))) {
      isAdminMode.value = true
      errorMessage.value = lang.value === 'kh'
        ? 'សូមបញ្ចូលពាក្យសម្ងាត់សម្រាប់គណនី Admin'
        : 'Password is required for Admin login.'
      nextTick(() => {
        passwordInputRef.value?.focus?.()
      })
      return
    }

    if (err.response?.status === 422 || err.response?.status === 404) {
      if (rawMsg.includes('suspended') || rawMsg.includes('ផ្អាក')) {
        errorMessage.value = lang.value === 'kh'
          ? 'គណនីនេះត្រូវបានផ្អាកជាបណ្ដោះអាសន្ន'
          : 'Account is suspended.'
      } else if (rawMsg.includes('inactive') || rawMsg.includes('មិនអាច')) {
        errorMessage.value = lang.value === 'kh'
          ? 'គណនីសិស្សនេះមិនអាច Login បានទេ'
          : 'This student account is inactive.'
      } else {
        // Uniform error response prevents account enumeration (F-01 Remediation)
        errorMessage.value = isAdminMode.value
          ? (lang.value === 'kh' ? 'ឈ្មោះគណនី ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវ' : 'Invalid username or password.')
          : (lang.value === 'kh' ? 'លេខសម្គាល់សិស្ស ឬព័ត៌មានមិនត្រឹមត្រូវ' : 'Invalid Student ID or credentials.')
      }
    } else {
      if (rawMsg.includes('Database') || rawMsg.includes('MySQL') || rawMsg.includes('មូលដ្ឋានទិន្នន័យ') || err.response?.status === 503 || err.response?.status === 500) {
        errorMessage.value = lang.value === 'kh'
          ? 'មិនអាចភ្ជាប់ទៅកាន់ Database បានទេ (សូមពិនិត្យមើល MySQL Server)។'
          : 'Unable to connect to the database (Please check MySQL Server).'
      } else {
        errorMessage.value = (lang.value === 'en' && /[\u1780-\u17FF]/.test(rawMsg))
          ? 'Server connection error. Please try again.'
          : (rawMsg || (lang.value === 'kh' ? 'មានបញ្ហាតភ្ជាប់មូលដ្ឋានទិន្នន័យ សូមព្យាយាមម្តងទៀត' : 'Database connection error. Please try again.'))
      }
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>
