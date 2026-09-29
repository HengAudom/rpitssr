<template>
  <StudentLayout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

      <!-- Header & Back Button -->
      <div class="flex items-center justify-between">
        <Button
          variant="outline"
          size="sm"
          icon="arrow_back"
          @click="router.push('/student')"
        >
          {{ lang === 'kh' ? 'ត្រឡប់ទៅផ្ទាំងដើម' : 'Back to Dashboard' }}
        </Button>
      </div>

      <!-- Loading Skeleton -->
      <div v-if="loading" class="space-y-6">
        <Skeleton height="240px" customClass="rounded-3xl" />
        <div class="grid grid-cols-3 gap-4">
          <Skeleton v-for="n in 3" :key="n" height="90px" customClass="rounded-2xl" />
        </div>
        <Skeleton height="400px" customClass="rounded-2xl" />
      </div>

      <!-- Main Result Content -->
      <div v-else-if="result" class="space-y-6 animate-fade-in">

        <!-- ── Result Celebration Hero Card ────────────────────────── -->
        <div class="rounded-3xl bg-gradient-to-br from-blue-700 via-blue-800 to-indigo-900 text-white p-6 sm:p-10 shadow-soft-xl relative overflow-hidden text-center">
          <div class="absolute -top-32 -right-32 w-80 h-80 rounded-full bg-white/10 blur-3xl pointer-events-none"></div>

          <!-- Icon / Badge -->
          <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15 text-white mb-4 backdrop-blur-xs border border-white/20 shadow-soft-sm">
            <span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">
              {{ result.accuracy >= 50 ? 'military_tech' : 'assignment_late' }}
            </span>
          </div>

          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/15 text-xs font-bold text-blue-100 border border-white/20 backdrop-blur-xs">
            <span class="h-2 w-2 rounded-full" :class="result.accuracy >= 50 ? 'bg-emerald-400' : 'bg-amber-400'"></span>
            {{ result.accuracy >= 50 ? (lang === 'kh' ? 'ការប្រឡងជាប់' : 'Exam Passed') : (lang === 'kh' ? 'ការប្រឡងធ្លាក់' : 'Needs Improvement') }}
          </span>

          <h1 class="text-3xl sm:text-4xl font-black text-white mt-3 tracking-tight">
            {{ result.testName }}
          </h1>

          <!-- Main Score Display -->
          <div class="my-6">
            <p class="text-xs font-bold uppercase tracking-wider text-blue-200">{{ lang === 'kh' ? 'ពិន្ទុរបស់អ្នក' : 'Your Score' }}</p>
            <div class="text-5xl sm:text-6xl font-black text-white tracking-tight mt-1">
              {{ result.score }}
              <span class="text-2xl sm:text-3xl text-blue-300 font-bold">/ {{ result.totalMarks }}</span>
            </div>
          </div>

          <!-- 3-Column Metrics Footer -->
          <div class="pt-6 border-t border-white/15 grid grid-cols-3 gap-4 max-w-lg mx-auto">
            <div>
              <p class="text-2xl font-black text-white">{{ result.accuracy }}%</p>
              <p class="text-[11px] font-bold uppercase tracking-wider text-blue-200 mt-0.5">{{ lang === 'kh' ? 'អត្រាត្រឹមត្រូវ' : 'Accuracy' }}</p>
            </div>
            <div>
              <p class="text-2xl font-black text-white">{{ result.elapsedMinutes }}m</p>
              <p class="text-[11px] font-bold uppercase tracking-wider text-blue-200 mt-0.5">{{ lang === 'kh' ? 'រយៈពេលប្រើ' : 'Duration' }}</p>
            </div>
            <div>
              <p class="text-2xl font-black text-white">{{ result.totalCorrect }} / {{ result.questions?.length }}</p>
              <p class="text-[11px] font-bold uppercase tracking-wider text-blue-200 mt-0.5">{{ lang === 'kh' ? 'ឆ្លើយត្រូវ' : 'Correct' }}</p>
            </div>
          </div>
        </div>

        <!-- ── Answer Performance Breakdown ────────────────────────── -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white shrink-0 shadow-soft-xs">
              <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
            </div>
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-emerald-800">{{ lang === 'kh' ? 'ត្រឹមត្រូវ' : 'Correct' }}</p>
              <p class="text-xl font-extrabold text-emerald-900 mt-0.5">{{ result.totalCorrect }}</p>
            </div>
          </div>

          <div class="p-4 rounded-2xl bg-rose-50 border border-rose-100 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-600 text-white shrink-0 shadow-soft-xs">
              <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">cancel</span>
            </div>
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-rose-800">{{ lang === 'kh' ? 'មិនត្រឹមត្រូវ' : 'Incorrect' }}</p>
              <p class="text-xl font-extrabold text-rose-900 mt-0.5">{{ result.incorrect }}</p>
            </div>
          </div>

          <div
            :class="[
              'p-4 rounded-2xl border flex items-center gap-3 transition-all',
              (result.interruptions || 0) > 0
                ? 'bg-amber-50/80 border-amber-200'
                : 'bg-slate-50 border-slate-200/80'
            ]"
          >
            <div
              :class="[
                'flex h-10 w-10 items-center justify-center rounded-xl text-white shrink-0 shadow-soft-xs',
                (result.interruptions || 0) > 0 ? 'bg-amber-600' : 'bg-slate-400'
              ]"
            >
              <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">
                {{ (result.interruptions || 0) > 0 ? 'visibility_off' : 'verified_user' }}
              </span>
            </div>
            <div>
              <p
                class="text-xs font-bold uppercase tracking-wider"
                :class="(result.interruptions || 0) > 0 ? 'text-amber-800' : 'text-slate-500'"
              >
                {{ lang === 'kh' ? 'ចំនួនបើកចាកចេញ' : 'Tab Switches' }}
              </p>
              <p
                class="text-xl font-extrabold mt-0.5"
                :class="(result.interruptions || 0) > 0 ? 'text-amber-900' : 'text-slate-700'"
              >
                {{ result.interruptions || 0 }} <span class="text-xs font-bold">{{ lang === 'kh' ? 'លើក' : 'times' }}</span>
              </p>
            </div>
          </div>
        </div>

        <!-- ── Detailed Question-by-Question Review ─────────────────── -->
        <Card
          :title="lang === 'kh' ? 'ពិនិត្យចម្លើយឡើងវិញ' : 'Question-by-Question Review'"
          :subtitle="`${result.questions?.length || 0} questions evaluated`"
          padding="none"
          class="shadow-soft-sm overflow-hidden"
        >
          <div class="divide-y divide-slate-100">
            <div
              v-for="(q, i) in result.questions"
              :key="q.id"
              class="p-5 sm:p-6 hover:bg-slate-50/50 transition-colors space-y-3"
            >
              <div class="flex items-start gap-3">
                <!-- Status Number Circle -->
                <span
                  :class="[
                    'h-7 w-7 rounded-xl flex items-center justify-center font-bold text-xs shrink-0',
                    q.isCorrect ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'
                  ]"
                >
                  {{ i + 1 }}
                </span>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-2 mb-2">
                    <div class="flex items-center gap-1.5">
                      <span class="text-xs font-bold uppercase tracking-wider text-slate-400">
                        {{ lang === 'kh' ? 'សំណួរទី' : 'Question' }} {{ i + 1 }}
                      </span>
                      <span v-if="q.passage" class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded bg-blue-100 text-blue-800 text-[9px] font-bold">
                        <span class="material-symbols-outlined text-[10px]">menu_book</span> Reading
                      </span>
                    </div>
                    <Badge
                      :variant="q.isCorrect ? 'success' : 'danger'"
                      size="xs"
                    >
                      {{ q.isCorrect ? (lang === 'kh' ? 'ត្រឹមត្រូវ' : 'Correct') : (lang === 'kh' ? 'មិនត្រឹមត្រូវ' : 'Incorrect') }}
                    </Badge>
                  </div>

                  <!-- Reading Passage Context in Review (if applicable) -->
                  <div v-if="q.passage && (i === 0 || result.questions[i - 1]?.passage !== q.passage)" class="p-3 my-2.5 rounded-xl bg-blue-50/80 border border-blue-200/70 text-xs text-slate-800 space-y-1">
                    <div class="flex items-center gap-1.5 font-bold text-blue-800">
                      <span class="material-symbols-outlined text-sm">menu_book</span>
                      <span>{{ lang === 'kh' ? 'អត្ថបទអាន (Reading Passage)' : 'Reading Passage' }}</span>
                    </div>
                    <p class="whitespace-pre-line text-slate-700 italic leading-relaxed">{{ q.passage }}</p>
                  </div>

                  <p class="text-sm sm:text-base font-bold text-slate-900 leading-relaxed" v-html="renderMath(q.text)"></p>

                  <!-- Answer Choices -->
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 mt-4">
                    <div
                      v-for="ans in q.answers"
                      :key="ans.id"
                      :class="[
                        'flex items-center gap-3 p-3.5 rounded-2xl text-xs sm:text-sm border transition-all',
                        ans.isCorrect
                          ? 'bg-emerald-50 border-emerald-300 text-emerald-900 font-bold ring-1 ring-emerald-400'
                          : ans.id === q.selectedId && !ans.isCorrect
                          ? 'bg-rose-50 border-rose-300 text-rose-900 font-bold ring-1 ring-rose-400'
                          : 'bg-white border-slate-200 text-slate-600'
                      ]"
                    >
                      <span
                        class="material-symbols-outlined text-base shrink-0"
                        style="font-variation-settings: 'FILL' 1;"
                        :class="ans.isCorrect ? 'text-emerald-600' : ans.id === q.selectedId ? 'text-rose-600' : 'text-slate-300'"
                      >
                        {{ ans.isCorrect ? 'check_circle' : ans.id === q.selectedId ? 'cancel' : 'radio_button_unchecked' }}
                      </span>

                      <span class="flex-1 font-medium" v-html="renderMath(ans.text)"></span>

                      <span
                        v-if="ans.id === q.selectedId && !ans.isCorrect"
                        class="text-[10px] bg-rose-100 text-rose-800 px-2 py-0.5 rounded font-extrabold uppercase shrink-0"
                      >
                        {{ lang === 'kh' ? 'ចម្លើយរបស់អ្នក' : 'Your Choice' }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Card>
      </div>
    </div>
  </StudentLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import StudentLayout from '../layouts/StudentLayout.vue'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Badge from '../components/ui/Badge.vue'
import { renderMath } from '../utils/mathRender'
import Skeleton from '../components/ui/Skeleton.vue'
import { useLang } from '../utils/useLang'
import { useToast } from '../composables/useToast'

const route = useRoute()
const router = useRouter()
const { lang } = useLang()
const { error: toastError } = useToast()

const submissionId = route.params.submissionId
const result = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await axios.get(`/api/student/results/${submissionId}`)
    result.value = res.data
  } catch (e) {
    toastError('Failed to load exam results.')
  } finally {
    loading.value = false
  }
})
</script>
