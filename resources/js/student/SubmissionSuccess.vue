<template>
  <StudentLayout>
    <div class="min-h-[75vh] flex items-center justify-center px-4 sm:px-6 lg:px-8 py-10">
      <div class="max-w-md w-full text-center space-y-6 animate-fade-in">

        <!-- Main Card -->
        <Card padding="lg" class="shadow-soft-xl border-slate-100/80 rounded-3xl relative overflow-hidden bg-white/95 backdrop-blur-sm">
          <!-- Background Glow Accent -->
          <div class="absolute -top-24 -right-24 w-48 h-48 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>
          <div class="absolute -bottom-24 -left-24 w-48 h-48 rounded-full bg-blue-500/10 blur-3xl pointer-events-none"></div>

          <!-- Success Icon with Pulse -->
          <div class="relative mx-auto my-4 flex h-20 w-20 items-center justify-center">
            <div class="absolute inset-0 rounded-full bg-emerald-500/20 animate-ping opacity-75"></div>
            <div class="relative flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white shadow-soft-lg border-2 border-white">
              <span class="material-symbols-outlined text-4xl" style="font-variation-settings: 'FILL' 1;">
                check_circle
              </span>
            </div>
          </div>

          <!-- Status Badge -->
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/80 text-xs font-bold tracking-wide uppercase">
            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
            {{ t.submissionConfirmed }}
          </div>

          <!-- Main Title & Message -->
          <div class="space-y-2 mt-3">
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
              {{ t.title }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 leading-relaxed max-w-sm mx-auto">
              {{ t.description }}
            </p>
          </div>

          <!-- Info Box -->
          <div class="mt-6 p-4 rounded-2xl bg-slate-50/80 border border-slate-100 text-left space-y-2.5">
            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-500 font-medium">{{ t.statusLabel }}</span>
              <span class="font-bold text-emerald-700 bg-emerald-100/70 px-2 py-0.5 rounded-md">
                {{ t.submittedStatus }}
              </span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-500 font-medium">{{ t.timeLabel }}</span>
              <span class="font-bold text-slate-700">
                {{ submissionTime }}
              </span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-500 font-medium">{{ t.evaluationNotice }}</span>
              <span class="font-bold text-blue-600">
                {{ t.evaluationAdmin }}
              </span>
            </div>
          </div>

          <!-- Return CTA Button -->
          <div class="mt-8 pt-4 border-t border-slate-100">
            <Button
              variant="primary"
              size="lg"
              full-width
              icon="dashboard"
              @click="goToDashboard"
            >
              {{ t.returnBtn }}
            </Button>
          </div>
        </Card>

      </div>
    </div>
  </StudentLayout>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import StudentLayout from '../layouts/StudentLayout.vue'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import { useLang } from '../utils/useLang'

const router = useRouter()
const { lang } = useLang()

const submissionTime = ref('')

onMounted(() => {
  submissionTime.value = new Date().toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true
  })
})

const t = computed(() => {
  if (lang.value === 'kh') {
    return {
      submissionConfirmed: 'ការប្រគល់វិញ្ញាសាត្រឹមត្រូវ',
      title: 'Submit ជោគជ័យ',
      description: 'វិញ្ញាសារបស់អ្នកត្រូវបានប្រគល់ជូនប្រព័ន្ធដោយជោគជ័យ។ សូមអរគុណសម្រាប់ការចូលរួមប្រឡង!',
      statusLabel: 'ស្ថានភាព',
      submittedStatus: 'បានប្រគល់រួច ✓',
      timeLabel: 'ម៉ោងប្រគល់',
      evaluationNotice: 'ការត្រួតពិនិត្យ',
      evaluationAdmin: 'ដោយគ្រូបង្រៀន / Admin',
      returnBtn: 'ត្រឡប់ទៅ Student Dashboard'
    }
  }
  return {
    submissionConfirmed: 'Submission Confirmed',
    title: 'Submitted Successfully',
    description: 'Your examination answers have been safely received and recorded into the system. Thank you!',
    statusLabel: 'Status',
    submittedStatus: 'Completed & Recorded ✓',
    timeLabel: 'Recorded Time',
    evaluationNotice: 'Review Process',
    evaluationAdmin: 'Evaluated by Instructor',
    returnBtn: 'Return to Student Dashboard'
  }
})

const goToDashboard = () => {
  router.replace('/student')
}
</script>
