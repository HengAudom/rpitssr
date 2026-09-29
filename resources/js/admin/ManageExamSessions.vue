<template>
  <div class="space-y-6">
    <!-- Page Header (Matching Image 3 style) -->
    <div>
      <h1 class="text-2xl font-black text-slate-900 tracking-tight">
        {{ t.pageTitle }}
      </h1>
      <p class="text-xs sm:text-sm text-slate-500 mt-1 font-medium">
        {{ t.pageSubtitle }}
      </p>
    </div>

    <!-- ── 3 CARDS LAYOUT ────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-start">
      <!-- ── CARD 1: EXAM SHIFTS (វេនប្រឡង) ────────────────────────────── -->
      <div class="bg-white rounded-3xl p-6 shadow-soft-sm border border-slate-100 flex flex-col space-y-4">
        <!-- Card Header -->
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-base font-extrabold text-slate-900">{{ t.shiftsTitle }}</h2>
            <p class="text-xs text-slate-400 font-medium mt-0.5">
              {{ sessions.length }} {{ t.shiftsSubtitle }}
            </p>
          </div>
          <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-600 border border-blue-100/60">
            {{ sessions.length }}
          </span>
        </div>

        <!-- Add / Edit Input Bar (Matching Image 3) -->
        <div class="flex items-center gap-2">
          <input
            ref="shiftInputRef"
            v-model="quickShiftName"
            type="text"
            :placeholder="editingSessionId ? t.editShiftPlaceholder : t.newShiftPlaceholder"
            @keydown.enter.prevent="submitShift"
            @keydown.esc="cancelEditShift"
            :class="[
              'flex-1 px-3.5 py-2.5 text-xs font-semibold rounded-xl border transition placeholder:text-slate-400 text-slate-800 focus:outline-none',
              editingSessionId
                ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50/30'
                : 'border-slate-200 bg-slate-50/70 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600'
            ]"
          />
          <template v-if="editingSessionId">
            <button
              type="button"
              @click="submitShift"
              :disabled="isSavingShift"
              class="flex items-center gap-1 px-3.5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold transition shadow-sm shrink-0 disabled:opacity-50"
              title="Save"
            >
              <span class="material-symbols-outlined text-base">check</span>
              <span>{{ t.save }}</span>
            </button>
            <button
              type="button"
              @click="cancelEditShift"
              class="flex items-center gap-1 px-2.5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition shrink-0"
              title="Cancel"
            >
              <span class="material-symbols-outlined text-base">close</span>
            </button>
          </template>
          <template v-else>
            <button
              type="button"
              @click="submitShift"
              :disabled="isSavingShift"
              class="flex items-center gap-1 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold transition shadow-sm shrink-0 disabled:opacity-50"
            >
              <span class="material-symbols-outlined text-base">add</span>
              <span>{{ t.addBtn }}</span>
            </button>
          </template>
        </div>

        <!-- Table Header -->
        <div class="flex items-center justify-between pt-2 border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider pb-2">
          <span>{{ t.colShiftName }}</span>
          <span>{{ t.colActions }}</span>
        </div>

        <!-- Items List -->
        <div class="space-y-1 divide-y divide-slate-50">
          <div
            v-for="session in sessions"
            :key="session.SessionId"
            :class="[
              'flex items-center justify-between py-2 px-2 rounded-xl transition-colors group',
              editingSessionId === session.SessionId
                ? 'bg-blue-50/80 border border-blue-200'
                : 'hover:bg-slate-50/70'
            ]"
          >
            <div class="flex items-center gap-2.5 min-w-0 pr-2">
              <span class="h-2 w-2 rounded-full bg-blue-600 shrink-0"></span>
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-bold text-slate-800 truncate block">{{ session.SessionName }}</span>
                  <span
                    v-if="editingSessionId === session.SessionId"
                    class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-blue-100 text-blue-700 shrink-0"
                  >
                    {{ lang === 'kh' ? 'កំពុងកែ...' : 'Editing...' }}
                  </span>
                </div>
                <span v-if="session.students_count" class="text-[11px] text-indigo-600 font-semibold block truncate mt-0.5">
                  {{ session.students_count }} {{ t.candidates }}
                </span>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0 opacity-80 group-hover:opacity-100 transition-opacity">
              <button
                type="button"
                @click="startEditShift(session)"
                class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition"
                title="Edit"
              >
                <span class="material-symbols-outlined text-sm">edit</span>
              </button>
              <button
                type="button"
                @click="confirmDelete(session)"
                class="p-1 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition"
                title="Delete"
              >
                <span class="material-symbols-outlined text-sm">delete</span>
              </button>
            </div>
          </div>

          <div v-if="sessions.length === 0" class="py-6 text-center text-xs text-slate-400">
            {{ t.noSessionsFound }}
          </div>
        </div>
      </div>

      <!-- ── CARD 2: EXAM DAYS (កាលវិភាគថ្ងៃ) ───────────────────────────── -->
      <div class="bg-white rounded-3xl p-6 shadow-soft-sm border border-slate-100 flex flex-col space-y-4">
        <!-- Card Header -->
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-base font-extrabold text-slate-900">{{ t.daysTitle }}</h2>
            <p class="text-xs text-slate-400 font-medium mt-0.5">
              {{ examDays.length }} {{ t.daysSubtitle }}
            </p>
          </div>
          <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-fuchsia-50 text-fuchsia-600 border border-fuchsia-100/60">
            {{ examDays.length }}
          </span>
        </div>

        <!-- Add / Edit Input Bar (Matching Image 3) -->
        <div class="flex items-center gap-2">
          <input
            ref="dayInputRef"
            v-model="newDayName"
            type="text"
            :placeholder="editingDayId ? t.editDayPlaceholder : t.newDayPlaceholder"
            @keydown.enter.prevent="submitDay"
            @keydown.esc="cancelEditDay"
            :class="[
              'flex-1 px-3.5 py-2.5 text-xs font-semibold rounded-xl border transition placeholder:text-slate-400 text-slate-800 focus:outline-none',
              editingDayId
                ? 'border-purple-500 ring-2 ring-purple-500/20 bg-purple-50/30'
                : 'border-slate-200 bg-slate-50/70 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600'
            ]"
          />
          <template v-if="editingDayId">
            <button
              type="button"
              @click="submitDay"
              :disabled="isSavingDay"
              class="flex items-center gap-1 px-3.5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold transition shadow-sm shrink-0 disabled:opacity-50"
              title="Save"
            >
              <span class="material-symbols-outlined text-base">check</span>
              <span>{{ t.save }}</span>
            </button>
            <button
              type="button"
              @click="cancelEditDay"
              class="flex items-center gap-1 px-2.5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition shrink-0"
              title="Cancel"
            >
              <span class="material-symbols-outlined text-base">close</span>
            </button>
          </template>
          <template v-else>
            <button
              type="button"
              @click="submitDay"
              :disabled="isSavingDay"
              class="flex items-center gap-1 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold transition shadow-sm shrink-0 disabled:opacity-50"
            >
              <span class="material-symbols-outlined text-base">add</span>
              <span>{{ t.addBtn }}</span>
            </button>
          </template>
        </div>

        <!-- Table Header -->
        <div class="flex items-center justify-between pt-2 border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider pb-2">
          <span>{{ t.colDayName }}</span>
          <span>{{ t.colActions }}</span>
        </div>

        <!-- Items List -->
        <div class="space-y-1 divide-y divide-slate-50">
          <div
            v-for="day in examDays"
            :key="day.id"
            :class="[
              'flex items-center justify-between py-2 px-2 rounded-xl transition-colors group',
              editingDayId === day.id
                ? 'bg-purple-50/80 border border-purple-200'
                : 'hover:bg-slate-50/70'
            ]"
          >
            <div class="flex items-center gap-2.5 min-w-0 pr-2">
              <span class="h-2 w-2 rounded-full bg-purple-600 shrink-0"></span>
              <div class="flex items-center gap-2 min-w-0">
                <span class="text-xs font-bold text-slate-800 truncate block">{{ day.name }}</span>
                <span
                  v-if="editingDayId === day.id"
                  class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-purple-100 text-purple-700 shrink-0"
                >
                  {{ lang === 'kh' ? 'កំពុងកែ...' : 'Editing...' }}
                </span>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0 opacity-80 group-hover:opacity-100 transition-opacity">
              <button
                type="button"
                @click="startEditDay(day)"
                class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition"
                title="Edit"
              >
                <span class="material-symbols-outlined text-sm">edit</span>
              </button>
              <button
                type="button"
                @click="deleteDay(day.id)"
                class="p-1 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition"
                title="Delete"
              >
                <span class="material-symbols-outlined text-sm">delete</span>
              </button>
            </div>
          </div>

          <div v-if="examDays.length === 0" class="py-6 text-center text-xs text-slate-400">
            {{ t.noDaysFound }}
          </div>
        </div>
      </div>

      <!-- ── CARD 3: ACADEMIC YEARS (ឆ្នាំសិក្សា) ────────────────────────── -->
      <div class="bg-white rounded-3xl p-6 shadow-soft-sm border border-slate-100 flex flex-col space-y-4">
        <!-- Card Header -->
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-base font-extrabold text-slate-900">{{ t.yearsTitle }}</h2>
            <p class="text-xs text-slate-400 font-medium mt-0.5">
              {{ academicYears.length }} {{ t.yearsSubtitle }}
            </p>
          </div>
          <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-600 border border-emerald-100/60">
            {{ academicYears.length }}
          </span>
        </div>

        <!-- Add / Edit Input Bar (Matching Image 3) -->
        <div class="flex items-center gap-2">
          <input
            ref="yearInputRef"
            v-model="newYearName"
            type="text"
            :placeholder="editingYearId ? t.editYearPlaceholder : t.newYearPlaceholder"
            @keydown.enter.prevent="submitYear"
            @keydown.esc="cancelEditYear"
            :class="[
              'flex-1 px-3.5 py-2.5 text-xs font-semibold rounded-xl border transition placeholder:text-slate-400 text-slate-800 focus:outline-none',
              editingYearId
                ? 'border-emerald-500 ring-2 ring-emerald-500/20 bg-emerald-50/30'
                : 'border-slate-200 bg-slate-50/70 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600'
            ]"
          />
          <template v-if="editingYearId">
            <button
              type="button"
              @click="submitYear"
              :disabled="isSavingYear"
              class="flex items-center gap-1 px-3.5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold transition shadow-sm shrink-0 disabled:opacity-50"
              title="Save"
            >
              <span class="material-symbols-outlined text-base">check</span>
              <span>{{ t.save }}</span>
            </button>
            <button
              type="button"
              @click="cancelEditYear"
              class="flex items-center gap-1 px-2.5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition shrink-0"
              title="Cancel"
            >
              <span class="material-symbols-outlined text-base">close</span>
            </button>
          </template>
          <template v-else>
            <button
              type="button"
              @click="submitYear"
              :disabled="isSavingYear"
              class="flex items-center gap-1 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-bold transition shadow-sm shrink-0 disabled:opacity-50"
            >
              <span class="material-symbols-outlined text-base">add</span>
              <span>{{ t.addBtn }}</span>
            </button>
          </template>
        </div>

        <!-- Table Header -->
        <div class="flex items-center justify-between pt-2 border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider pb-2">
          <span>{{ t.colYearName }}</span>
          <span>{{ t.colActions }}</span>
        </div>

        <!-- Items List -->
        <div class="space-y-1 divide-y divide-slate-50">
          <div
            v-for="ay in academicYears"
            :key="ay.id"
            :class="[
              'flex items-center justify-between py-2 px-2 rounded-xl transition-colors group',
              editingYearId === ay.id
                ? 'bg-emerald-50/80 border border-emerald-200'
                : 'hover:bg-slate-50/70'
            ]"
          >
            <div class="flex items-center gap-2.5 min-w-0 pr-2">
              <span class="h-2 w-2 rounded-full bg-emerald-600 shrink-0"></span>
              <div class="flex items-center gap-2 min-w-0">
                <span class="text-xs font-bold text-slate-800 truncate font-mono">{{ ay.year || ay.name }}</span>
                <span
                  v-if="editingYearId === ay.id"
                  class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 shrink-0"
                >
                  {{ lang === 'kh' ? 'កំពុងកែ...' : 'Editing...' }}
                </span>
                <span
                  v-else-if="ay.isDefault"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0"
                >
                  {{ t.currentYearBadge }}
                </span>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0 opacity-80 group-hover:opacity-100 transition-opacity">
              <button
                v-if="!ay.isDefault && editingYearId !== ay.id"
                type="button"
                @click="setDefaultYear(ay.id)"
                class="p-1 rounded-lg text-slate-400 hover:text-emerald-700 hover:bg-emerald-50 transition"
                title="Set as Default"
              >
                <span class="material-symbols-outlined text-sm">check_circle</span>
              </button>
              <button
                type="button"
                @click="startEditYear(ay)"
                class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition"
                title="Edit"
              >
                <span class="material-symbols-outlined text-sm">edit</span>
              </button>
              <button
                type="button"
                @click="deleteYear(ay.id)"
                class="p-1 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition"
                title="Delete"
              >
                <span class="material-symbols-outlined text-sm">delete</span>
              </button>
            </div>
          </div>

          <div v-if="academicYears.length === 0" class="py-6 text-center text-xs text-slate-400">
            {{ t.noYearsFound }}
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirm Dialog -->
    <ConfirmDialog
      v-model="showDeleteDialog"
      :title="t.deleteTitle"
      :message="t.deleteMessage"
      :confirm-text="t.delete"
      :cancel-text="t.cancel"
      confirm-variant="danger"
      icon="delete"
      @confirm="executeDelete"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import axios from 'axios'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import { useLang } from '../utils/useLang'
import { useToast } from '../composables/useToast'
import { useRealtimeSync, notifyRealtimeChange } from '../composables/useRealtimeSync'
import { fastCache } from '../stores/fastCache'

const { lang } = useLang()
const { success: toastSuccess, error: toastError } = useToast()

// ─── 1. SHIFTS STATE ──────────────────────────────────────────────────────────
const sessions = ref(fastCache.get('exam_sessions') || [])
const quickShiftName = ref('')
const isSavingShift = ref(false)
const editingSessionId = ref(null)
const shiftInputRef = ref(null)

const showDeleteDialog = ref(false)
const deletingSession = ref(null)

// ─── 2. DAYS STATE ────────────────────────────────────────────────────────────
const examDays = ref(fastCache.get('exam_days') || [
  { id: '1', name: 'ថ្ងៃទី ១ (Day 1)' },
  { id: '2', name: 'ថ្ងៃទី ២ (Day 2)' },
  { id: '3', name: 'ថ្ងៃទី ៣ (Day 3)' }
])
const newDayName = ref('')
const isSavingDay = ref(false)
const editingDayId = ref(null)
const dayInputRef = ref(null)

// ─── 3. ACADEMIC YEARS STATE ──────────────────────────────────────────────────
const normalizeYears = (years) => {
  if (!Array.isArray(years)) return []
  return years.map(ay => {
    const val = (ay.year || ay.name || '').toString().trim()
    return {
      ...ay,
      year: val,
      name: val
    }
  })
}

const academicYears = ref(normalizeYears(fastCache.get('academic_years') || [
  { id: '1', year: '2025-2026', name: '2025-2026', isDefault: false },
  { id: '2', year: '2026-2027', name: '2026-2027', isDefault: true },
  { id: '3', year: '2027-2028', name: '2027-2028', isDefault: false }
]))
const newYearName = ref('')
const isSavingYear = ref(false)
const editingYearId = ref(null)
const yearInputRef = ref(null)

// ─── TRANSLATIONS ─────────────────────────────────────────────────────────────
const t = computed(() => {
  if (lang.value === 'kh') {
    return {
      pageTitle: 'កាលវិភាគ វេន និង ឆ្នាំប្រឡង',
      pageSubtitle: 'គ្រប់គ្រងវេនប្រឡង កាលវិភាគថ្ងៃ និងឆ្នាំសិក្សាសម្រាប់ការប្រឡង',
      shiftsTitle: 'វេនប្រឡង',
      shiftsSubtitle: 'វេនប្រឡងសរុប',
      newShiftPlaceholder: 'ឈ្មោះវេនថ្មី (ឧ. វេនទី១: ព្រឹក)...',
      editShiftPlaceholder: 'កែសម្រួលឈ្មោះវេន (ឧ. វេនទី១: ព្រឹក)...',
      colShiftName: 'ឈ្មោះវេន',
      daysTitle: 'កាលវិភាគថ្ងៃប្រឡង',
      daysSubtitle: 'ថ្ងៃកំណត់',
      newDayPlaceholder: 'ឈ្មោះថ្ងៃថ្មី (ឧ. ថ្ងៃទី ១)...',
      editDayPlaceholder: 'កែសម្រួលឈ្មោះថ្ងៃ (ឧ. ថ្ងៃទី ១)...',
      colDayName: 'ឈ្មោះថ្ងៃ',
      yearsTitle: 'ឆ្នាំសិក្សា & ឆ្នាំប្រឡង',
      yearsSubtitle: 'ឆ្នាំសិក្សា',
      newYearPlaceholder: 'ឆ្នាំថ្មី (ឧ. 2026-2027)...',
      editYearPlaceholder: 'កែសម្រួលឆ្នាំសិក្សា (ឧ. 2026-2027)...',
      colYearName: 'ឆ្នាំសិក្សា',
      addBtn: 'បន្ថែម',
      colActions: 'សកម្មភាព',
      candidates: 'បេក្ខជន',
      currentYearBadge: 'បច្ចុប្បន្ន',
      noSessionsFound: 'មិនទាន់មានវេនប្រឡងទេ',
      noDaysFound: 'មិនទាន់មានថ្ងៃប្រឡងទេ',
      noYearsFound: 'មិនទាន់មានឆ្នាំសិក្សាទេ',
      cancel: 'បោះបង់',
      save: 'រក្សាទុក',
      saving: 'កំពុងរក្សាទុក...',
      delete: 'លុប',
      deleteTitle: 'លុបវេនប្រឡងនេះ?',
      deleteMessage: 'តើអ្នកពិតជាចង់លុបវេនប្រឡងនេះមែនទេ? បេក្ខជនដែលស្ថិតក្នុងវេននេះនឹងត្រូវដកវេនចេញ។'
    }
  }
  return {
    pageTitle: 'Exam Schedules, Shifts & Years',
    pageSubtitle: 'Manage exam shifts, scheduled days, and academic years for examinations',
    shiftsTitle: 'Exam Shifts',
    shiftsSubtitle: 'total shifts',
    newShiftPlaceholder: 'New shift (e.g. Shift 1: Morning)...',
    editShiftPlaceholder: 'Edit shift name...',
    colShiftName: 'Shift Name',
    daysTitle: 'Exam Days',
    daysSubtitle: 'scheduled days',
    newDayPlaceholder: 'New day (e.g. Day 1)...',
    editDayPlaceholder: 'Edit day name...',
    colDayName: 'Day Name',
    yearsTitle: 'Academic Years',
    yearsSubtitle: 'academic cohorts',
    newYearPlaceholder: 'New year (e.g. 2026-2027)...',
    editYearPlaceholder: 'Edit academic year...',
    colYearName: 'Academic Year',
    addBtn: 'Add',
    colActions: 'Actions',
    candidates: 'candidates',
    currentYearBadge: 'Current',
    noSessionsFound: 'No exam shifts yet',
    noDaysFound: 'No exam days yet',
    noYearsFound: 'No academic years yet',
    cancel: 'Cancel',
    save: 'Save',
    saving: 'Saving...',
    delete: 'Delete',
    deleteTitle: 'Delete Exam Shift?',
    deleteMessage: 'Are you sure you want to delete this exam shift? Candidates assigned will be unassigned.'
  }
})

// ─── SHIFTS CRUD ──────────────────────────────────────────────────────────────
const loadSessions = async () => {
  try {
    const res = await axios.get('/api/admin/exam-sessions')
    sessions.value = res.data.sessions || []
    fastCache.set('exam_sessions', sessions.value)
  } catch (e) {
    console.error('Failed to load exam sessions', e)
  }
}

const startEditShift = (session) => {
  cancelEditDay()
  cancelEditYear()
  editingSessionId.value = session.SessionId
  quickShiftName.value = session.SessionName
  nextTick(() => {
    shiftInputRef.value?.focus()
    shiftInputRef.value?.select()
  })
}

const cancelEditShift = () => {
  editingSessionId.value = null
  quickShiftName.value = ''
}

const submitShift = async () => {
  const name = quickShiftName.value.trim()
  if (!name) return

  isSavingShift.value = true
  try {
    if (editingSessionId.value) {
      // Update existing shift directly from top bar
      const sId = editingSessionId.value
      const res = await axios.put(`/api/admin/exam-sessions/${sId}`, {
        sessionName: name,
        status: 'Active'
      })
      const idx = sessions.value.findIndex(s => s.SessionId === sId)
      if (idx >= 0) {
        sessions.value[idx] = {
          ...sessions.value[idx],
          SessionName: name,
          ...(res.data?.session || {})
        }
        sessions.value = [...sessions.value]
      }
      fastCache.set('exam_sessions', sessions.value)
      notifyRealtimeChange('session_updated')
      toastSuccess(lang.value === 'kh' ? 'បានកែប្រែវេនប្រឡងដោយជោគជ័យ' : 'Exam shift updated successfully')
      cancelEditShift()
      await loadSessions()
    } else {
      // Add new shift
      const res = await axios.post('/api/admin/exam-sessions', {
        sessionName: name,
        status: 'Active'
      })
      if (res.data?.session) {
        sessions.value = [res.data.session, ...sessions.value]
      }
      quickShiftName.value = ''
      toastSuccess(lang.value === 'kh' ? 'បានបន្ថែមវេនប្រឡងដោយជោគជ័យ' : 'Exam shift created successfully')
      fastCache.set('exam_sessions', sessions.value)
      notifyRealtimeChange('session_updated')
      await loadSessions()
    }
  } catch (e) {
    toastError(e.response?.data?.message || (lang.value === 'kh' ? 'មានបញ្ហាក្នុងការរក្សាទុក' : 'Failed to save shift'))
  } finally {
    isSavingShift.value = false
  }
}

const confirmDelete = (session) => {
  deletingSession.value = session
  showDeleteDialog.value = true
}

const executeDelete = async () => {
  if (!deletingSession.value) return
  const sId = deletingSession.value.SessionId
  try {
    sessions.value = sessions.value.filter(s => s.SessionId !== sId)
    fastCache.set('exam_sessions', sessions.value)
    showDeleteDialog.value = false
    await axios.delete(`/api/admin/exam-sessions/${sId}`)
    toastSuccess(lang.value === 'kh' ? 'បានលុបវេនប្រឡងជោគជ័យ' : 'Exam session deleted successfully')
    notifyRealtimeChange('session_deleted')
    await loadSessions()
  } catch (e) {
    toastError(e.response?.data?.message || (lang.value === 'kh' ? 'មិនអាចលុបបានទេ' : 'Failed to delete session'))
    await loadSessions()
  }
}

// ─── DAYS & YEARS PERSISTENCE ─────────────────────────────────────────────────
const loadDaysYears = async () => {
  try {
    const res = await axios.get('/api/admin/schedule-days-years')
    if (res.data.examDays && Array.isArray(res.data.examDays)) {
      examDays.value = [...res.data.examDays]
      fastCache.set('exam_days', examDays.value)
    }
    if (res.data.academicYears && Array.isArray(res.data.academicYears)) {
      academicYears.value = normalizeYears(res.data.academicYears)
      fastCache.set('academic_years', academicYears.value)
    }
  } catch (e) {
    console.error('Failed to load schedule days & years', e)
  }
}

const persistDaysYears = async () => {
  try {
    const res = await axios.post('/api/admin/schedule-days-years', {
      examDays: examDays.value,
      academicYears: normalizeYears(academicYears.value)
    })
    if (res.data?.data?.examDays) {
      examDays.value = [...res.data.data.examDays]
    }
    if (res.data?.data?.academicYears) {
      academicYears.value = normalizeYears(res.data.data.academicYears)
    }
    fastCache.set('exam_days', examDays.value)
    fastCache.set('academic_years', academicYears.value)
    notifyRealtimeChange('schedule_days_years_updated')
  } catch (e) {
    toastError(lang.value === 'kh' ? 'បរាជ័យក្នុងការរក្សាទុក' : 'Failed to save changes')
  }
}

// ─── DAYS ACTIONS ─────────────────────────────────────────────────────────────
const startEditDay = (day) => {
  cancelEditShift()
  cancelEditYear()
  editingDayId.value = day.id
  newDayName.value = day.name
  nextTick(() => {
    dayInputRef.value?.focus()
    dayInputRef.value?.select()
  })
}

const cancelEditDay = () => {
  editingDayId.value = null
  newDayName.value = ''
}

const submitDay = async () => {
  const name = newDayName.value.trim()
  if (!name) return

  isSavingDay.value = true
  try {
    if (editingDayId.value) {
      // Update existing day
      const item = examDays.value.find(d => String(d.id) === String(editingDayId.value))
      if (item) {
        item.name = name
      }
      examDays.value = [...examDays.value]
      cancelEditDay()
      fastCache.set('exam_days', examDays.value)
      await persistDaysYears()
      toastSuccess(lang.value === 'kh' ? 'បានកែប្រែថ្ងៃប្រឡងជោគជ័យ' : 'Exam day updated successfully')
    } else {
      // Add new day
      const newDay = {
        id: Date.now().toString(),
        name: name
      }
      examDays.value = [...examDays.value, newDay]
      newDayName.value = ''
      fastCache.set('exam_days', examDays.value)
      await persistDaysYears()
      toastSuccess(lang.value === 'kh' ? 'បានបន្ថែមថ្ងៃប្រឡងថ្មី' : 'Exam day added successfully')
    }
  } catch (e) {
    toastError(lang.value === 'kh' ? 'មានបញ្ហាក្នុងការរក្សាទុកថ្ងៃប្រឡង' : 'Failed to save exam day')
  } finally {
    isSavingDay.value = false
  }
}

const deleteDay = async (id) => {
  if (!confirm(lang.value === 'kh' ? 'តើអ្នកចង់លុបថ្ងៃប្រឡងនេះមែនទេ?' : 'Delete this exam day?')) return
  examDays.value = examDays.value.filter(d => String(d.id) !== String(id))
  fastCache.set('exam_days', examDays.value)
  await persistDaysYears()
  toastSuccess(lang.value === 'kh' ? 'បានលុបថ្ងៃប្រឡងជោគជ័យ' : 'Exam day deleted')
}

// ─── ACADEMIC YEARS ACTIONS ───────────────────────────────────────────────────
const startEditYear = (ay) => {
  cancelEditShift()
  cancelEditDay()
  editingYearId.value = ay.id
  newYearName.value = ay.year || ay.name || ''
  nextTick(() => {
    yearInputRef.value?.focus()
    yearInputRef.value?.select()
  })
}

const cancelEditYear = () => {
  editingYearId.value = null
  newYearName.value = ''
}

const submitYear = async () => {
  const year = newYearName.value.trim()
  if (!year) return

  isSavingYear.value = true
  try {
    if (editingYearId.value) {
      // Update existing year
      const item = academicYears.value.find(y => String(y.id) === String(editingYearId.value))
      if (item) {
        item.year = year
        item.name = year
      }
      academicYears.value = [...academicYears.value]
      cancelEditYear()
      fastCache.set('academic_years', academicYears.value)
      await persistDaysYears()
      toastSuccess(lang.value === 'kh' ? 'បានកែប្រែឆ្នាំសិក្សាជោគជ័យ' : 'Academic year updated successfully')
    } else {
      // Add new year
      const newYear = {
        id: Date.now().toString(),
        year,
        name: year,
        isDefault: academicYears.value.length === 0
      }
      academicYears.value = [...academicYears.value, newYear]
      newYearName.value = ''
      fastCache.set('academic_years', academicYears.value)
      await persistDaysYears()
      toastSuccess(lang.value === 'kh' ? 'បានបន្ថែមឆ្នាំសិក្សាថ្មី' : 'Academic year added')
    }
  } catch (e) {
    toastError(lang.value === 'kh' ? 'មានបញ្ហាក្នុងការរក្សាទុកឆ្នាំសិក្សា' : 'Failed to save academic year')
  } finally {
    isSavingYear.value = false
  }
}

const setDefaultYear = async (id) => {
  academicYears.value.forEach(y => {
    y.isDefault = String(y.id) === String(id)
  })
  academicYears.value = [...academicYears.value]
  fastCache.set('academic_years', academicYears.value)
  await persistDaysYears()
  toastSuccess(lang.value === 'kh' ? 'បានកំណត់ជាឆ្នាំសិក្សាសកម្ម' : 'Set as active academic year')
}

const deleteYear = async (id) => {
  if (!confirm(lang.value === 'kh' ? 'តើអ្នកចង់លុបឆ្នាំសិក្សានេះមែនទេ?' : 'Delete this academic year?')) return
  academicYears.value = academicYears.value.filter(y => String(y.id) !== String(id))
  fastCache.set('academic_years', academicYears.value)
  await persistDaysYears()
  toastSuccess(lang.value === 'kh' ? 'បានលុបឆ្នាំសិក្សា' : 'Academic year deleted')
}

useRealtimeSync(() => {
  if (!editingSessionId.value && !editingDayId.value && !editingYearId.value) {
    loadSessions()
    loadDaysYears()
  }
}, 4000)

onMounted(() => {
  loadSessions()
  loadDaysYears()
})
</script>
