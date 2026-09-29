<template>
  <PublicLayout>
    <!-- Self-Registration Disabled State -->
    <Card v-if="!isRegistrationAllowed" padding="lg" class="shadow-soft-lg max-w-xl mx-auto text-center py-8 space-y-4">
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 border border-amber-200">
        <span class="material-symbols-outlined text-4xl">person_off</span>
      </div>
      <div>
        <h2 class="text-xl font-extrabold text-slate-900">
          {{ lang === 'kh' ? 'ការចុះឈ្មោះត្រូវបានបិទ' : 'Self-Registration Closed' }}
        </h2>
        <p class="text-xs sm:text-sm text-slate-500 mt-2 max-w-sm mx-auto leading-relaxed">
          {{ lang === 'kh' ? 'ការចុះឈ្មោះបង្កើតគណនីដោយខ្លួនឯងត្រូវបានបិទជាបណ្ដោះអាសន្នដោយ Administrator។' : 'Candidate self-registration is currently disabled by administrator.' }}
        </p>
      </div>
      <div class="pt-2">
        <Button variant="primary" icon="login" size="md" @click="router.push('/login')">
          {{ lang === 'kh' ? 'ត្រឡប់ទៅទំព័រចូល' : 'Return to Sign In' }}
        </Button>
      </div>
    </Card>

    <Card v-else padding="normal" class="shadow-soft-lg max-w-lg mx-auto border border-slate-200/80 p-4 sm:p-6">
      <!-- Wizard Step Header -->
      <div class="mb-4 sm:mb-5">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold uppercase tracking-wider text-blue-600">
            {{ t.step }} {{ currentStep }} {{ t.of }} 3: {{ currentStepTitle }}
          </span>
          <span class="text-xs font-semibold text-slate-400">
            {{ Math.round((currentStep / 3) * 100) }}%
          </span>
        </div>

        <!-- Step Indicator Track -->
        <div class="grid grid-cols-3 gap-1.5 h-1.5 rounded-full overflow-hidden bg-slate-100">
          <div
            v-for="step in 3"
            :key="step"
            :class="[
              'h-full rounded-full transition-all duration-300',
              step <= currentStep ? 'bg-blue-600' : 'bg-slate-200'
            ]"
          ></div>
        </div>
      </div>

      <!-- Error Alert -->
      <div
        v-if="errorMessage"
        class="mb-5 flex items-center gap-2.5 p-3.5 rounded-2xl bg-red-50 text-red-700 border border-red-200/80 text-xs font-semibold animate-fade-in"
      >
        <span class="material-symbols-outlined text-lg shrink-0">error</span>
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Step 1: Personal Info -->
      <div v-if="currentStep === 1" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Input
            v-model="form.firstName"
            :label="t.firstName"
            required
            :placeholder="lang === 'kh' ? 'ឧទាហរណ៍៖ សុខា' : 'e.g. Sokha'"
            :error="errors.firstName?.[0]"
          />
          <Input
            v-model="form.lastName"
            :label="t.lastName"
            required
            :placeholder="lang === 'kh' ? 'ឧទាហរណ៍៖ ចាន់' : 'e.g. Chan'"
            :error="errors.lastName?.[0]"
          />
        </div>

        <Input
          v-model="form.phone"
          :label="t.phone"
          icon="call"
          required
          placeholder="012 345 678"
          :error="errors.phone?.[0]"
        />

        <div class="space-y-1.5">
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
            {{ t.gender }} <span class="text-red-500">*</span>
          </label>
          <CustomDropdown
            v-model="form.gender"
            :options="genderOptions"
            labelKey="label"
            valueKey="value"
            :placeholder="t.gender"
            :hasError="!!errors.gender"
          />
          <p v-if="errors.gender" class="text-xs text-red-500 font-semibold mt-1">{{ errors.gender[0] }}</p>
        </div>
      </div>

      <!-- Step 2: Exam Shift & Schedule -->
      <div v-if="currentStep === 2" class="space-y-4">
        <!-- Auto-Generated Student ID Badge Preview -->
        <div class="p-3.5 rounded-2xl bg-gradient-to-r from-blue-50/90 to-indigo-50/90 border border-blue-100 flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-sm">
              <span class="material-symbols-outlined text-xl">badge</span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold tracking-wider text-blue-600 block">{{ lang === 'kh' ? 'Student ID ស្វ័យប្រវត្តិ (មិនអាចកែប្រែបាន)' : 'Auto-Generated Student ID (Fixed)' }}</span>
              <span class="text-sm font-black text-slate-900 font-mono tracking-wider">{{ form.studentCode }}</span>
            </div>
          </div>
          <div class="flex items-center gap-1 text-slate-400 text-xs font-semibold px-2.5 py-1 bg-white/80 rounded-xl border border-blue-100/80">
            <span class="material-symbols-outlined text-sm text-blue-600">lock</span>
            <span class="text-[11px] text-slate-500 font-medium">{{ lang === 'kh' ? 'ថេរ' : 'Fixed' }}</span>
          </div>
        </div>

        <!-- Exam Shift Selection -->
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
              {{ t.examShift }} <span class="text-red-500">*</span>
            </label>
            <span v-if="sessionsList.length > 0" class="text-[11px] text-slate-400 font-medium">
              {{ sessionsList.length }} {{ lang === 'kh' ? 'វេនមាន' : 'shifts available' }}
            </span>
          </div>

          <!-- Shift Interactive Cards -->
          <div v-if="sessionsList.length > 0" class="space-y-2.5">
            <div
              v-for="session in sessionsList"
              :key="session.SessionId"
              :class="[
                'p-4 rounded-2xl border-2 cursor-pointer transition-all duration-200 relative',
                form.sessionId === session.SessionId
                  ? 'border-blue-600 bg-blue-50/50 shadow-soft-xs ring-2 ring-blue-500/10'
                  : 'border-slate-200/90 bg-white hover:border-blue-300 hover:bg-slate-50/60'
              ]"
              @click="form.sessionId = session.SessionId"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="space-y-1.5 flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-black text-slate-900 text-sm tracking-tight">
                      {{ session.SessionName }}
                    </span>
                    <span
                      v-if="form.sessionId === session.SessionId"
                      class="px-2 py-0.5 rounded-full bg-blue-600 text-white text-[10px] font-bold inline-flex items-center gap-1"
                    >
                      <span class="material-symbols-outlined text-xs">check</span>
                      {{ lang === 'kh' ? 'បានជ្រើសរើស' : 'Selected' }}
                    </span>
                  </div>

                  <!-- Date & Time Row -->
                  <div class="flex items-center gap-3 text-xs text-slate-600 flex-wrap pt-0.5">
                    <div class="inline-flex items-center gap-1 font-semibold text-slate-700">
                      <span class="material-symbols-outlined text-sm text-blue-600">event</span>
                      <span>{{ formatDate(session.ExamDate) }}</span>
                    </div>

                    <div class="inline-flex items-center gap-1 font-mono text-[11px] text-blue-800 bg-blue-100/70 px-2 py-0.5 rounded-lg border border-blue-200/60">
                      <span class="material-symbols-outlined text-xs">schedule</span>
                      <span>{{ formatTime(session.StartTime) }} - {{ formatTime(session.EndTime) }}</span>
                    </div>
                  </div>

                  <!-- Venue / Description -->
                  <div v-if="session.Description" class="flex items-center gap-1.5 text-xs text-slate-500 pt-0.5">
                    <span class="material-symbols-outlined text-sm text-slate-400">location_on</span>
                    <span class="truncate">{{ session.Description }}</span>
                  </div>
                </div>

                <!-- Radio Check Indicator -->
                <div
                  :class="[
                    'h-6 w-6 rounded-full border-2 flex items-center justify-center shrink-0 mt-0.5 transition-all',
                    form.sessionId === session.SessionId
                      ? 'border-blue-600 bg-blue-600 text-white'
                      : 'border-slate-300 bg-white text-transparent'
                  ]"
                >
                  <span class="material-symbols-outlined text-sm font-bold">check</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Fallback when no shifts configured -->
          <div v-else class="p-6 rounded-2xl bg-amber-50/70 border border-amber-200/80 text-center space-y-2">
            <span class="material-symbols-outlined text-amber-600 text-3xl">event_busy</span>
            <p class="text-xs text-amber-800 font-semibold">
              {{ lang === 'kh' ? 'មិនទាន់មានកាលវិភាគវេនប្រឡងបើកនៅឡើយទេ' : 'No exam shifts currently available.' }}
            </p>
          </div>

          <p v-if="errors.sessionId" class="text-xs text-red-500 font-semibold mt-1">{{ errors.sessionId[0] }}</p>
        </div>
      </div>

      <!-- Step 3: Summary & Review -->
      <div v-if="currentStep === 3" class="space-y-3">
        <!-- Assigned Student ID Highlight -->
        <div class="p-3 sm:p-3.5 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white shadow-soft-sm flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-xl text-white">badge</span>
            </div>
            <div>
              <span class="text-[10px] font-bold uppercase tracking-wider text-blue-100 block">
                {{ lang === 'kh' ? 'Student ID សម្រាប់ចូលប្រឡង' : 'Your Student ID' }}
              </span>
              <span class="text-base sm:text-lg font-black font-mono tracking-wider">{{ form.studentCode }}</span>
            </div>
          </div>
          <button
            type="button"
            class="px-2.5 py-1.5 rounded-xl bg-white/20 hover:bg-white/30 text-white text-xs font-bold inline-flex items-center gap-1 transition-colors cursor-pointer"
            @click="copyToClipboard(form.studentCode)"
          >
            <span class="material-symbols-outlined text-sm">{{ isCopied ? 'check' : 'content_copy' }}</span>
            <span>{{ isCopied ? (lang === 'kh' ? 'បានចម្លង' : 'Copied') : (lang === 'kh' ? 'ចម្លង' : 'Copy') }}</span>
          </button>
        </div>

        <!-- Combined Review Details Card -->
        <div class="p-3 sm:p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 divide-y divide-slate-200/60 space-y-2.5">
          <!-- Personal Info -->
          <div class="space-y-1">
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
              {{ t.personalInfo }}
            </h4>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
              <div><span class="text-slate-400 block text-[10px]">{{ t.fullName }}</span> <strong class="text-slate-800 font-semibold truncate block">{{ form.lastName }} {{ form.firstName }}</strong></div>
              <div><span class="text-slate-400 block text-[10px]">{{ t.phone }}</span> <strong class="text-slate-800 font-semibold truncate block">{{ form.phone }}</strong></div>
              <div><span class="text-slate-400 block text-[10px]">{{ t.gender }}</span> <strong class="text-slate-800 font-semibold block">{{ form.gender === 'Female' ? (lang === 'kh' ? 'ស្រី' : 'Female') : (lang === 'kh' ? 'ប្រុស' : 'Male') }}</strong></div>
            </div>
          </div>

          <!-- Exam Shift & Schedule -->
          <div class="pt-2 space-y-1">
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
              {{ t.examShift }}
            </h4>
            <div v-if="selectedSessionInfo" class="text-xs space-y-1">
              <div class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                <span class="material-symbols-outlined text-blue-600 text-sm">calendar_clock</span>
                <span class="truncate">{{ selectedSessionInfo.SessionName }}</span>
              </div>
              <div class="flex items-center gap-2 text-slate-600 flex-wrap text-[11px]">
                <div class="flex items-center gap-1">
                  <span class="material-symbols-outlined text-xs text-slate-400">event</span>
                  <span>{{ formatDate(selectedSessionInfo.ExamDate) }}</span>
                </div>
                <div class="font-mono text-[10px] font-semibold text-blue-700 bg-blue-100/60 px-1.5 py-0.5 rounded border border-blue-200/60">
                  {{ formatTime(selectedSessionInfo.StartTime) }} - {{ formatTime(selectedSessionInfo.EndTime) }}
                </div>
              </div>
              <div v-if="selectedSessionInfo.Description" class="flex items-center gap-1 text-slate-500 text-[11px] truncate">
                <span class="material-symbols-outlined text-xs text-slate-400 shrink-0">location_on</span>
                <span class="truncate">{{ selectedSessionInfo.Description }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Note Notice -->
        <div class="p-2 sm:p-2.5 bg-amber-50/80 border border-amber-200/80 rounded-xl text-[11px] text-amber-800 flex items-center gap-2">
          <span class="material-symbols-outlined text-amber-600 text-sm shrink-0">info</span>
          <span>{{ lang === 'kh' ? 'ចំណាំ៖ ចូលប្រឡងដោយប្រើតែ Student ID ខាងលើ (គ្មានលេខសម្ងាត់)។' : 'Note: After registration, sign in using your Student ID only (no password needed).' }}</span>
        </div>
      </div>

      <!-- Stepper Controls -->
      <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between gap-3">
        <Button
          v-if="currentStep > 1"
          variant="outline"
          size="sm"
          icon="arrow_back"
          @click="prevStep"
        >
          {{ t.back }}
        </Button>
        <div v-else></div>

        <Button
          v-if="currentStep < 3"
          variant="primary"
          size="sm"
          trailing-icon="arrow_forward"
          @click="nextStep"
        >
          {{ t.continue }}
        </Button>

        <Button
          v-else
          variant="primary"
          size="sm"
          icon="how_to_reg"
          :loading="isSubmitting"
          @click="handleRegister"
        >
          {{ isSubmitting ? t.submittingBtn : t.createAccountBtn }}
        </Button>
      </div>

      <!-- Login Link -->
      <div class="mt-3 text-center">
        <p class="text-xs text-slate-500 font-medium">
          {{ t.alreadyHaveAccount }}
          <RouterLink
            to="/login"
            class="font-bold text-blue-600 hover:text-blue-700 hover:underline ml-1"
          >
            {{ t.signInLink }}
          </RouterLink>
        </p>
      </div>
    </Card>

    <!-- Success Modal -->
    <Modal v-model="showSuccess" max-width="md" :show-close="false" :close-on-backdrop="false">
      <div class="text-center py-4 space-y-4">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-100">
          <span class="material-symbols-outlined text-4xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
        </div>
        <div>
          <h3 class="text-xl font-extrabold text-slate-900">{{ t.successTitle }}</h3>
          <p class="text-sm text-slate-500 mt-1 leading-relaxed">
            {{ t.successDesc }}
          </p>

          <!-- Student ID Highlight Card -->
          <div v-if="createdStudentCode" class="mt-4 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl border border-blue-200/80 shadow-sm text-center relative group">
            <span class="text-xs text-blue-600 font-bold uppercase tracking-wider block mb-1">{{ lang === 'kh' ? 'Student ID របស់អ្នក' : 'Your Student ID' }}</span>
            <div class="flex items-center justify-center gap-2">
              <span class="text-2xl font-black text-blue-900 font-mono tracking-widest">{{ createdStudentCode }}</span>
              <button
                type="button"
                class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-100 transition-colors"
                :title="lang === 'kh' ? 'ចម្លងកូដ' : 'Copy ID'"
                @click="copyToClipboard(createdStudentCode)"
              >
                <span class="material-symbols-outlined text-lg">{{ isCopied ? 'check' : 'content_copy' }}</span>
              </button>
            </div>
            <span class="text-[11px] text-slate-400 block mt-1.5">{{ lang === 'kh' ? 'សូមរក្សាទុក Student ID នេះ ដើម្បីចូលប្រឡង' : 'Please save this ID to sign in to exams' }}</span>
          </div>

          <!-- Shift Info Summary in Success Modal -->
          <div v-if="selectedSessionInfo" class="mt-3 p-3 rounded-xl bg-slate-50 border border-slate-200/80 text-left text-xs space-y-1">
            <div class="font-bold text-slate-800 flex items-center gap-1.5">
              <span class="material-symbols-outlined text-sm text-blue-600">calendar_clock</span>
              <span>{{ selectedSessionInfo.SessionName }}</span>
            </div>
            <div class="text-slate-600 text-[11px] flex items-center gap-2">
              <span>{{ formatDate(selectedSessionInfo.ExamDate) }}</span>
              <span>•</span>
              <span>{{ formatTime(selectedSessionInfo.StartTime) }} - {{ formatTime(selectedSessionInfo.EndTime) }}</span>
            </div>
            <div v-if="selectedSessionInfo.Description" class="text-slate-500 text-[11px] flex items-center gap-1">
              <span class="material-symbols-outlined text-xs text-slate-400">location_on</span>
              <span>{{ selectedSessionInfo.Description }}</span>
            </div>
          </div>
        </div>
        <div class="pt-2">
          <Button variant="primary" full-width size="lg" icon="login" @click="router.push('/login')">
            {{ t.goToLogin }}
          </Button>
        </div>
      </div>
    </Modal>
  </PublicLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import axios from 'axios'
import PublicLayout from '../layouts/PublicLayout.vue'
import Card from '../components/ui/Card.vue'
import Input from '../components/ui/Input.vue'
import Button from '../components/ui/Button.vue'
import Modal from '../components/ui/Modal.vue'
import CustomDropdown from '../components/CustomDropdown.vue'
import { useLang } from '../utils/useLang'
import { useSettings } from '../composables/useSettings'

const router = useRouter()
const { lang } = useLang()
const { settings, fetchSettings } = useSettings()

const isRegistrationAllowed = computed(() => {
  return settings.allowRegistration === true || settings.allowRegistration === 'true' || settings.allowRegistration === 1 || settings.allowRegistration === '1'
})

const currentStep = ref(1)
const isSubmitting = ref(false)
const errorMessage = ref('')
const showSuccess = ref(false)
const createdStudentCode = ref('')
const isCopied = ref(false)
const errors = reactive({})

const copyToClipboard = async (text) => {
  if (!text) return
  try {
    await navigator.clipboard.writeText(text)
    isCopied.value = true
    setTimeout(() => {
      isCopied.value = false
    }, 2000)
  } catch (e) {
    console.error('Failed to copy to clipboard', e)
  }
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString(lang.value === 'kh' ? 'km-KH' : 'en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const formatTime = (timeStr) => {
  if (!timeStr) return ''
  const parts = timeStr.split(':')
  if (parts.length >= 2) {
    let hour = parseInt(parts[0], 10)
    const min = parts[1]
    const ampm = hour >= 12 ? 'PM' : 'AM'
    hour = hour % 12 || 12
    return `${hour.toString().padStart(2, '0')}:${min} ${ampm}`
  }
  return timeStr
}

const form = reactive({
  studentCode: '',
  firstName: '',
  lastName: '',
  phone: '',
  gender: 'Male',
  sessionId: ''
})

const sessionsList = ref([])

const generateRandomCode = (year = null) => {
  const currentYear = year || new Date().getFullYear()
  const rand = Math.floor(10000 + Math.random() * 90000)
  return `SR${currentYear}${rand}`
}

const randomizeStudentCode = () => {
  form.studentCode = generateRandomCode()
}

const genderOptions = computed(() => [
  { label: lang.value === 'kh' ? 'ប្រុស' : 'Male', value: 'Male' },
  { label: lang.value === 'kh' ? 'ស្រី' : 'Female', value: 'Female' },
  { label: lang.value === 'kh' ? 'ផ្សេងៗ' : 'Other', value: 'Other' }
])

const sessionOptions = computed(() => {
  return sessionsList.value.map(s => {
    let timeText = ''
    if (s.StartTime) {
      const start = s.StartTime.substring(0, 5)
      const end = s.EndTime ? s.EndTime.substring(0, 5) : ''
      timeText = end ? `${start} - ${end}` : start
    }
    return {
      label: s.SessionName,
      value: s.SessionId,
      subLabel: timeText || null
    }
  })
})

const selectedSessionInfo = computed(() => {
  return sessionsList.value.find(s => s.SessionId === form.sessionId) || null
})

const selectedSessionName = computed(() => {
  if (selectedSessionInfo.value) {
    return selectedSessionInfo.value.SessionName
  }
  return lang.value === 'kh' ? 'មិនទាន់ជ្រើសរើស' : 'Not selected'
})

const t = computed(() => {
  if (lang.value === 'kh') {
    return {
      step: 'ជំហានទី',
      of: 'នៃ',
      personalInfo: 'ព័ត៌មានផ្ទាល់ខ្លួន',
      examShift: 'វេន & កាលវិភាគប្រឡង',
      review: 'ផ្ទៀងផ្ទាត់ និង បញ្ជាក់',
      firstName: 'នាមខ្លួន',
      lastName: 'គោត្តនាម',
      fullName: 'ឈ្មោះពេញ',
      phone: 'លេខទូរស័ព្ទ',
      gender: 'ភេទ',
      studentId: 'Student ID',
      selectExamShift: 'ជ្រើសរើសវេនប្រឡង',
      back: 'ថយក្រោយ',
      continue: 'បន្តទៅមុខ',
      createAccountBtn: 'ចុះឈ្មោះប្រឡងឥឡូវនេះ',
      submittingBtn: 'កំពុងចុះឈ្មោះ...',
      alreadyHaveAccount: 'មានគណនីរួចហើយ?',
      signInLink: 'ចូលប្រឡងទីនេះ',
      successTitle: 'ចុះឈ្មោះជោគជ័យ!',
      successDesc: 'ការចុះឈ្មោះប្រឡងរបស់អ្នកត្រូវបានបញ្ចប់។ សូមរក្សាទុក Student ID ខាងក្រោមដើម្បីចូលប្រឡង។',
      goToLogin: 'ទៅកាន់ទំព័រចូលប្រឡង'
    }
  }
  return {
    step: 'Step',
    of: 'of',
    personalInfo: 'Personal Information',
    examShift: 'Exam Shift & Schedule',
    review: 'Review & Confirm',
    firstName: 'First Name',
    lastName: 'Last Name',
    fullName: 'Full Name',
    phone: 'Phone Number',
    gender: 'Gender',
    studentId: 'Student ID',
    selectExamShift: 'Select Exam Shift',
    back: 'Back',
    continue: 'Continue',
    createAccountBtn: 'Register for Exam Now',
    submittingBtn: 'Registering...',
    alreadyHaveAccount: 'Already have an account?',
    signInLink: 'Sign in here',
    successTitle: 'Registration Successful!',
    successDesc: 'Your candidate profile has been registered. Please keep your Student ID to log in.',
    goToLogin: 'Go to Exam Sign In'
  }
})

const currentStepTitle = computed(() => {
  switch (currentStep.value) {
    case 1: return t.value.personalInfo
    case 2: return t.value.examShift
    case 3: return t.value.review
    default: return ''
  }
})

const validateStep = (step) => {
  errorMessage.value = ''
  if (step === 1) {
    if (!form.firstName.trim()) {
      errors.firstName = [lang.value === 'kh' ? 'សូមបំពេញនាមខ្លួន (First Name)' : 'Please enter your first name.']
      errorMessage.value = errors.firstName[0]
      return false
    }
    if (!form.lastName.trim()) {
      errors.lastName = [lang.value === 'kh' ? 'សូមបំពេញគោត្តនាម (Last Name)' : 'Please enter your last name.']
      errorMessage.value = errors.lastName[0]
      return false
    }
    if (!form.phone.trim()) {
      errors.phone = [lang.value === 'kh' ? 'សូមបំពេញលេខទូរស័ព្ទ' : 'Please enter your phone number.']
      errorMessage.value = errors.phone[0]
      return false
    }
  }
  if (step === 2) {
    if (!form.sessionId && sessionsList.value.length > 0) {
      errors.sessionId = [lang.value === 'kh' ? 'សូមជ្រើសរើសវេនប្រឡង' : 'Please select an exam shift.']
      errorMessage.value = errors.sessionId[0]
      return false
    }
  }
  return true
}

const nextStep = () => {
  if (validateStep(currentStep.value)) {
    currentStep.value++
    errorMessage.value = ''
  }
}

const prevStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--
    errorMessage.value = ''
  }
}

const loadMetadata = async () => {
  try {
    const res = await axios.get('/api/public-settings')
    if (res.data.sessions) {
      sessionsList.value = res.data.sessions || []
    }
    if (!sessionsList.value.length) {
      const sessRes = await axios.get('/api/admin/exam-sessions')
      sessionsList.value = sessRes.data.sessions || []
    }
    if (sessionsList.value.length && !form.sessionId) {
      form.sessionId = sessionsList.value[0].SessionId
    }
  } catch (e) {
    console.error('Failed to load exam sessions metadata', e)
  }
}

const handleRegister = async () => {
  errorMessage.value = ''
  Object.keys(errors).forEach(key => delete errors[key])

  if (!validateStep(1)) {
    currentStep.value = 1
    return
  }
  if (!validateStep(2)) {
    currentStep.value = 2
    return
  }

  isSubmitting.value = true

  try {
    const res = await axios.post('/api/register', {
      studentCode: form.studentCode,
      firstName: form.firstName.trim(),
      lastName: form.lastName.trim(),
      phone: form.phone.trim(),
      gender: form.gender,
      sessionId: form.sessionId || null
    })

    createdStudentCode.value = res.data.studentCode || form.studentCode
    showSuccess.value = true
  } catch (err) {
    if (err.response?.data?.errors) {
      Object.assign(errors, err.response.data.errors)
      const errorKeys = Object.keys(err.response.data.errors)
      if (errorKeys.length > 0) {
        const firstKey = errorKeys[0]
        if (['firstName', 'lastName', 'phone', 'gender'].includes(firstKey)) {
          currentStep.value = 1
        } else {
          currentStep.value = 2
        }

        const firstMsg = err.response.data.errors[firstKey]?.[0] || ''
        errorMessage.value = firstMsg || (lang.value === 'kh' ? 'សូមកែសម្រួលព័ត៌មានដែលមិនត្រឹមត្រូវខាងក្រោម' : 'Please fix the errors below.')
      }
    } else {
      const rawMsg = err.response?.data?.message || ''
      if (rawMsg && typeof rawMsg === 'string' && (rawMsg.includes('SQLSTATE') || rawMsg.includes('Connection:') || rawMsg.includes('Unknown column') || rawMsg.includes('Table ') || rawMsg.includes('database'))) {
        errorMessage.value = lang.value === 'kh' ? 'មានបញ្ហាតភ្ជាប់មូលដ្ឋានទិន្នន័យ (Database error)' : 'Database connection error.'
      } else {
        errorMessage.value = rawMsg || (lang.value === 'kh' ? 'ការចុះឈ្មោះមិនបានជោគជ័យ សូមពិនិត្យព័ត៌មានម្តងទៀត' : 'Registration failed. Please check your input.')
      }
    }
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  randomizeStudentCode()
  fetchSettings()
  loadMetadata()
})
</script>
