<template>
  <StudentLayout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

      <!-- Skeleton Shimmer Loading -->
      <div v-if="initialLoading" class="space-y-6">
        <Skeleton height="140px" customClass="rounded-3xl" />
        <Skeleton height="120px" customClass="rounded-3xl" />
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
          <Skeleton height="350px" customClass="rounded-2xl lg:col-span-7" />
          <Skeleton height="350px" customClass="rounded-2xl lg:col-span-5" />
        </div>
      </div>

      <div v-else class="space-y-6">
        <!-- ── Candidate Welcome & Exam Shift Card ───────────────────────────── -->
        <div class="rounded-3xl bg-gradient-to-r from-slate-900 via-blue-950 to-slate-900 text-white p-5 sm:p-8 shadow-soft-lg relative overflow-hidden">
        <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-blue-500/10 blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-5 sm:gap-6">
          <div class="flex items-start sm:items-center gap-3.5 sm:gap-5 min-w-0">
            <!-- Profile Avatar (View-only, student cannot change photo) -->
            <div class="relative h-16 w-16 sm:h-20 sm:w-20 shrink-0 rounded-2xl bg-white/10 border-2 border-white/20 overflow-hidden flex items-center justify-center shadow-soft-sm">
              <img v-if="student.profileImage" :src="student.profileImage" class="h-full w-full object-cover" />
              <span v-else class="material-symbols-outlined text-white/60 text-3xl">person</span>
            </div>

            <div class="min-w-0 flex-1 space-y-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-blue-300 whitespace-nowrap">
                  {{ t.candidateBadge }}
                </span>
                <span v-if="student.studentCode || student.studentId" class="px-2.5 py-0.5 rounded-lg bg-blue-500/25 text-blue-200 text-xs font-mono font-bold border border-blue-400/30 whitespace-nowrap inline-flex items-center shrink-0">
                  ID: {{ student.studentCode || student.studentId }}
                </span>
              </div>
              <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight mt-0.5 truncate capitalize">
                {{ studentDisplayName }}
              </h1>

              <!-- Exam Session & Shift Details -->
              <div class="flex items-center gap-2 pt-0.5 flex-wrap">
                <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-bold bg-blue-500/20 text-blue-200 border border-blue-400/30 whitespace-nowrap">
                  <span class="material-symbols-outlined text-xs">calendar_clock</span>
                  <span>{{ student.sessionName || t.generalSession }}</span>
                </div>

                <div v-if="student.examDate" class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full text-xs font-semibold bg-white/10 text-slate-200 border border-white/10 font-mono whitespace-nowrap">
                  <span class="material-symbols-outlined text-xs">event</span>
                  <span>{{ student.examDate }}</span>
                  <span v-if="student.startTime">({{ formatTime(student.startTime) }} - {{ formatTime(student.endTime) }})</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Featured Next Urgent Exam Banner ──────────────────────── -->
      <div v-if="nextUrgentExam" class="p-6 rounded-3xl bg-blue-600 text-white shadow-soft-md flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6 relative overflow-hidden">
        <div class="space-y-2 max-w-xl">
          <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-white/20 text-xs font-extrabold tracking-wide uppercase">
            <span class="h-2 w-2 rounded-full" :class="getExamTimingStatus(nextUrgentExam).isOpen ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"></span>
            {{ t.nextExamPrompt }}
          </div>
          <h2 class="text-2xl font-extrabold tracking-tight text-white leading-snug">
            {{ nextUrgentExam.name }}
          </h2>
          <div class="flex items-center gap-4 text-xs text-blue-100 flex-wrap font-medium">
            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">schedule</span>{{ nextUrgentExam.durationMinutes }} {{ t.minutes }}</span>
            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">star</span>{{ nextUrgentExam.totalMarks }} {{ t.marks }}</span>
            <span v-if="nextUrgentExam.sessionName" class="flex items-center gap-1"><span class="material-symbols-outlined text-sm">calendar_clock</span>{{ nextUrgentExam.sessionName }}</span>
            <span v-if="getExamTimingStatus(nextUrgentExam).isUpcoming" class="flex items-center gap-1 text-amber-200 font-bold bg-amber-500/20 px-2.5 py-0.5 rounded-full border border-amber-300/30">
              <span class="material-symbols-outlined text-sm">alarm</span>
              {{ lang === 'kh' ? 'បើកនៅ៖ ' : 'Opens at: ' }}{{ getExamTimingStatus(nextUrgentExam).timeText }}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <Button
            variant="secondary"
            size="lg"
            :icon="getExamTimingStatus(nextUrgentExam).isOpen ? 'play_arrow' : 'schedule'"
            :disabled="!getExamTimingStatus(nextUrgentExam).isOpen"
            class="bg-white text-blue-700 hover:bg-blue-50 font-extrabold shadow-soft-md disabled:opacity-60"
            @click="startExam(nextUrgentExam.id)"
          >
            {{ getExamTimingStatus(nextUrgentExam).isOpen ? t.startExamNow : getExamTimingStatus(nextUrgentExam).btnText }}
          </Button>
        </div>
      </div>

      <!-- ── Main Grid: Available Exams & Exam History ─────────────── -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Available Exams (7 cols) -->
        <Card
          :title="t.availableExams"
          :subtitle="t.availableSubtitle"
          class="lg:col-span-7 shadow-soft-sm"
          padding="normal"
        >
          <template #actions>
            <span class="rounded-full bg-blue-50 text-blue-700 font-bold px-2.5 py-0.5 text-xs">
              {{ availableTests.length }}
            </span>
          </template>

          <div v-if="availableTests.length > 0" class="space-y-3">
            <div
              v-for="exam in availableTests"
              :key="exam.id"
              class="p-4 rounded-2xl border border-slate-200/80 bg-white hover:border-blue-300 hover:shadow-soft-xs transition-all flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
              <div class="space-y-1.5">
                <h3 class="font-bold text-slate-900 text-sm sm:text-base leading-snug">
                  {{ exam.name }}
                </h3>
                <div class="flex items-center gap-3 text-xs text-slate-500 flex-wrap">
                  <span class="inline-flex items-center gap-1 font-medium">
                    <span class="material-symbols-outlined text-sm text-slate-400">schedule</span>
                    {{ exam.durationMinutes }} {{ t.minutes }}
                  </span>
                  <span class="inline-flex items-center gap-1 font-medium">
                    <span class="material-symbols-outlined text-sm text-slate-400">star</span>
                    {{ exam.totalMarks }} {{ t.marks }}
                  </span>
                  <span v-if="exam.sessionName" class="inline-flex items-center gap-1 font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded">
                    <span class="material-symbols-outlined text-xs">calendar_clock</span>
                    {{ exam.sessionName }}
                  </span>
                  <span
                    v-if="getExamTimingStatus(exam).isUpcoming"
                    class="inline-flex items-center gap-1 font-bold text-amber-700 bg-amber-50 border border-amber-200/60 px-2 py-0.5 rounded-md"
                  >
                    <span class="material-symbols-outlined text-xs">alarm</span>
                    {{ lang === 'kh' ? 'បើកនៅ៖ ' : 'Starts: ' }}{{ getExamTimingStatus(exam).timeText }}
                  </span>
                </div>
              </div>

              <Button
                :variant="getExamTimingStatus(exam).isOpen ? 'primary' : 'outline'"
                size="sm"
                :icon="getExamTimingStatus(exam).isOpen ? 'arrow_forward' : 'schedule'"
                :disabled="!getExamTimingStatus(exam).isOpen"
                class="shrink-0"
                @click="startExam(exam.id)"
              >
                {{ getExamTimingStatus(exam).btnText }}
              </Button>
            </div>
          </div>

          <EmptyState
            v-else
            icon="event_available"
            :title="t.noExamsAvailable"
            :description="t.noExamsDesc"
          />
        </Card>

        <!-- Exam History (5 cols) -->
        <Card
          :title="t.examHistory"
          :subtitle="t.historySubtitle"
          class="lg:col-span-5 shadow-soft-sm"
          padding="normal"
        >
          <div v-if="examResults.length > 0" class="space-y-3">
            <div
              v-for="res in examResults"
              :key="res.submissionId"
              class="p-3.5 rounded-2xl border border-slate-100 bg-slate-50/50 flex items-center justify-between gap-3 cursor-default select-none pointer-events-none"
            >
              <div class="min-w-0">
                <h4 class="font-bold text-slate-900 text-xs sm:text-sm truncate">
                  {{ res.testName }}
                </h4>
                <p class="text-[11px] text-slate-400 mt-0.5 font-mono">
                  {{ formatDate(res.completedAt) }}
                </p>
              </div>

              <div class="flex items-center gap-2 shrink-0">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                  <span class="material-symbols-outlined text-xs" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                  {{ lang === 'kh' ? 'បានប្រគល់រួច' : 'Submitted' }}
                </span>
              </div>
            </div>
          </div>

          <EmptyState
            v-else
            icon="history_edu"
            :title="t.noHistoryYet"
            :description="t.noHistoryDesc"
          />
        </Card>

      </div>

      </div>
    </div>
  </StudentLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import StudentLayout from '../layouts/StudentLayout.vue'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Input from '../components/ui/Input.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Skeleton from '../components/ui/Skeleton.vue'
import { useLang } from '../utils/useLang'
import { useToast } from '../composables/useToast'
import { useSettings } from '../composables/useSettings'

const router = useRouter()
const { lang } = useLang()
const { success: toastSuccess, error: toastError } = useToast()
const { settings, fetchSettings } = useSettings()

const initialLoading = ref(true)

const student = reactive({
  name: '',
  firstName: '',
  lastName: '',
  studentId: '',
  studentCode: '',
  phone: '',
  sessionId: null,
  sessionName: '',
  examDate: null,
  startTime: null,
  endTime: null,
  profileImage: ''
})

const availableTests = ref([])
const examResults = ref([])

const studentDisplayName = computed(() => {
  return student.name || 'Candidate'
})

const nextUrgentExam = computed(() => {
  return availableTests.value.length > 0 ? availableTests.value[0] : null
})

const formatTime = (timeStr) => {
  if (!timeStr) return ''
  return timeStr.substring(0, 5)
}

const formatDateTime = (iso) => {
  if (!iso) return ''
  const d = new Date(String(iso).replace(' ', 'T'))
  if (isNaN(d.getTime())) return ''
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = d.getFullYear()
  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  return `${day}/${month}/${year} ${hours}:${minutes}`
}

const getExamTimingStatus = (exam) => {
  if (!exam) return { isOpen: true, isUpcoming: false, isExpired: false, label: 'Open', btnText: 'Start' }
  const now = new Date()

  let isUpcoming = exam.isUpcoming === true
  let isExpired = exam.isFinished === true

  let startDate = null
  if (exam.scheduledAt) {
    const s = new Date(String(exam.scheduledAt).replace(' ', 'T'))
    if (!isNaN(s.getTime())) {
      startDate = s
      if (now < s) {
        isUpcoming = true
      }
    }
  }

  let endDate = null
  if (exam.finishedAt) {
    const e = new Date(String(exam.finishedAt).replace(' ', 'T'))
    if (!isNaN(e.getTime())) endDate = e
  } else if (startDate) {
    endDate = new Date(startDate.getTime() + (exam.durationMinutes || 45) * 60000)
  }

  if (endDate && now > endDate) {
    isExpired = true
  }

  if (isUpcoming) {
    return {
      isOpen: false,
      isUpcoming: true,
      isExpired: false,
      label: lang.value === 'kh' ? 'មិនទាន់ដល់ម៉ោង' : 'Upcoming',
      btnText: lang.value === 'kh' ? 'មិនទាន់ដល់ម៉ោង' : 'Upcoming',
      timeText: formatDateTime(exam.scheduledAt)
    }
  }

  if (isExpired) {
    return {
      isOpen: false,
      isUpcoming: false,
      isExpired: true,
      label: lang.value === 'kh' ? 'បានផុតកំណត់' : 'Expired',
      btnText: lang.value === 'kh' ? 'បានផុតកំណត់' : 'Expired',
      timeText: formatDateTime(endDate)
    }
  }

  return {
    isOpen: true,
    isUpcoming: false,
    isExpired: false,
    label: lang.value === 'kh' ? 'កំពុងបើកដំណើរការ' : 'Open',
    btnText: lang.value === 'kh' ? 'ចូលប្រឡង' : 'Take Exam'
  }
}

const t = computed(() => {
  if (lang.value === 'kh') {
    return {
      welcomeBack: 'សូមស្វាគមន៍មកកាន់ប្រព័ន្ធប្រឡងអាហារូបករណ៍',
      candidateBadge: 'បេក្ខជនអាហារូបករណ៍',
      generalSession: 'វេនទូទៅ',
      accountSettings: 'កែសម្រួលព័ត៌មាន',
      closeSettings: 'បិទ',
      editProfile: 'កែសម្រួលព័ត៌មានផ្ទាល់ខ្លួន',
      firstName: 'នាមខ្លួន',
      lastName: 'គោត្តនាម',
      phone: 'លេខទូរស័ព្ទ',
      saveProfile: 'រក្សាទុកព័ត៌មាន',
      nextExamPrompt: 'វិញ្ញាសាប្រឡងបន្ទាប់របស់អ្នក',
      startExamNow: 'ចាប់ផ្ដើមធ្វើវិញ្ញាសាឥឡូវនេះ',
      minutes: 'នាទី',
      marks: 'ពិន្ទុ',
      availableExams: 'វិញ្ញាសាប្រឡងដែលមាន',
      availableSubtitle: 'វិញ្ញាសាប្រឡងដែលបានចេញផ្សាយសម្រាប់វេនប្រឡងរបស់អ្នក',
      noExamsAvailable: 'មិនទាន់មានវិញ្ញាសាប្រឡងថ្មីទេ',
      noExamsDesc: 'នៅពេលគណៈកម្មការចេញវិញ្ញាសាប្រឡង វានឹងបង្ហាញនៅទីនេះ។',
      startExam: 'ចូលប្រឡង',
      examHistory: 'ប្រវត្តិការប្រឡង',
      historySubtitle: 'បញ្ជីវិញ្ញាសាដែលបានប្រគល់រួចរាល់',
      noHistoryYet: 'មិនទាន់មានប្រវត្តិប្រឡងទេ',
      noHistoryDesc: 'នៅពេលអ្នកបញ្ចប់ការប្រឡង កំណត់ត្រានឹងបង្ហាញនៅទីនេះ។'
    }
  }
  return {
    welcomeBack: 'Scholarship Entrance Assessment Portal',
    candidateBadge: 'Scholarship Candidate',
    generalSession: 'General Shift',
    accountSettings: 'Edit Profile',
    closeSettings: 'Close',
    editProfile: 'Edit Candidate Profile',
    firstName: 'First Name',
    lastName: 'Last Name',
    phone: 'Phone Number',
    saveProfile: 'Save Profile',
    nextExamPrompt: 'Next Scheduled Exam',
    startExamNow: 'Start Exam Now',
    minutes: 'min',
    marks: 'marks',
    availableExams: 'Available Exams',
    availableSubtitle: 'Exams published for your assigned exam shift',
    noExamsAvailable: 'No exams currently open',
    noExamsDesc: 'When examination papers are released for your session, they will appear here.',
    startExam: 'Take Exam',
    examHistory: 'Exam History',
    historySubtitle: 'Archive of submitted examination papers',
    noHistoryYet: 'No previous exam submissions',
    noHistoryDesc: 'Completed exams and submissions will be archived here.'
  }
})

const startExam = (testId) => {
  router.push({ name: 'Exam', params: { testId } })
}

const formatDate = (iso) => {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString(lang.value === 'kh' ? 'km-KH' : 'en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const loadStudentData = async () => {
  try {
    const profileRes = await axios.get('/api/profile')
    const s = profileRes.data.student || {}
    student.name = s.name || profileRes.data.user?.name || ''
    student.firstName = s.firstName || ''
    student.lastName = s.lastName || ''
    student.studentId = s.studentId || ''
    student.studentCode = s.studentCode || s.studentId || ''
    student.phone = s.phone || ''
    student.sessionId = s.sessionId || null
    student.sessionName = s.sessionName || ''
    student.examDate = s.examDate || null
    student.startTime = s.startTime || null
    student.endTime = s.endTime || null
    student.profileImage = profileRes.data.user?.profileImage || ''

    if (!student.firstName && !student.lastName && student.name) {
      const parts = student.name.trim().split(' ')
      student.firstName = parts[0] || ''
      student.lastName = parts.slice(1).join(' ') || ''
    }

    availableTests.value = profileRes.data.tests || []

    const resultsRes = await axios.get('/api/student/results')
    examResults.value = resultsRes.data.results || []
  } catch (e) {
    console.error('Failed to load student data', e)
  } finally {
    initialLoading.value = false
  }
}

import { useRealtimeSync } from '../composables/useRealtimeSync'

useRealtimeSync(() => {
  loadStudentData()
}, 2500)

onMounted(() => {
  fetchSettings()
  loadStudentData()
})
</script>
