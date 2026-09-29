<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
          {{ t.title }}
        </h1>
        <p class="text-sm text-slate-500 mt-1">
          {{ t.desc }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <Button
          v-if="can('Results', 'export')"
          variant="outline"
          size="sm"
          icon="download"
          @click="openExportModal('excel')"
          class="text-blue-700 border-blue-200 hover:bg-blue-50 bg-white font-semibold"
        >
          {{ lang === 'kh' ? 'នាំចេញ' : 'Export' }}
        </Button>
      </div>
    </div>

    <!-- Skeleton Shimmer Loading -->
    <div v-if="initialLoading" class="space-y-6">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <Skeleton v-for="n in 3" :key="n" height="110px" customClass="rounded-2xl" />
      </div>
      <Skeleton height="60px" customClass="rounded-2xl" />
      <Skeleton height="400px" customClass="rounded-2xl" />
    </div>

    <!-- Loaded Content -->
    <template v-else>
      <!-- Summary KPI Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <StatCard
        :label="t.totalSubmissions"
        :value="filteredResults.length"
        icon="task_alt"
        color="blue"
      />
      <StatCard
        :label="t.avgAccuracy"
        :value="avgAccuracy"
        suffix="%"
        icon="analytics"
        color="emerald"
      />
      <StatCard
        :label="t.uniqueStudents"
        :value="uniqueStudents"
        icon="group"
        color="purple"
      />
    </div>

    <!-- Filter Toolbar -->
    <Card padding="sm" class="shadow-soft-sm">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 flex-1 max-w-3xl">
          <CustomDropdown
            v-model="selectedYear"
            :options="yearOptions"
            labelKey="label"
            valueKey="value"
            :placeholder="t.selectYear"
          />
          <CustomDropdown
            v-model="selectedSession"
            :options="[{ label: t.selectSession, value: '' }, ...sessionOptions]"
            labelKey="label"
            valueKey="value"
            :placeholder="t.selectSession"
          />
          <CustomDropdown
            v-model="selectedTest"
            :options="[{ TestName: t.selectTest, TestId: '' }, ...tests]"
            labelKey="TestName"
            valueKey="TestId"
            :placeholder="t.selectTest"
          />
        </div>

        <div class="flex items-center gap-2">
          <IconButton
            v-if="selectedYear || selectedSession || selectedTest || searchQuery"
            icon="restart_alt"
            variant="ghost"
            size="md"
            :title="t.reset"
            class="shrink-0"
            @click="resetFilters"
          />
          <div class="w-full sm:w-64">
            <SearchInput
              v-model="searchQuery"
              :placeholder="t.searchPlaceholder"
            />
          </div>
        </div>
      </div>
    </Card>

    <!-- Results Table Card -->
    <Card padding="none" class="shadow-soft-sm overflow-hidden">
      <!-- Desktop Table View -->
      <div class="hidden md:block overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50/70">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.student }}</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.test }}</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.score }}</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.accuracy }}</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.date }}</th>
              <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-for="r in paginatedResults" :key="r.id" class="hover:bg-slate-50/80 transition-colors">
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div class="h-9 w-9 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center font-bold text-xs shrink-0 border border-blue-100">
                    {{ (r.studentName || 'S').charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <span class="font-bold text-slate-900 leading-tight block">{{ r.studentName }}</span>
                    <span v-if="r.studentCode" class="text-[11px] font-mono text-blue-600 font-bold mt-0.5 block">{{ r.studentCode }}</span>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-slate-700">
                <div class="font-bold text-slate-900">{{ r.testName }}</div>
                <div class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500 mt-1">
                  <span class="inline-flex items-center text-slate-600 font-medium">
                    <span>{{ r.sessionName }}</span>
                  </span>
                  <span v-if="(r.examDay || r.days) && !isWeekdayName(r.examDay || r.days)" class="inline-flex items-center text-blue-700 font-semibold text-[11px] bg-blue-50 px-2 py-0.5 rounded-md border border-blue-100">
                    <span>{{ r.examDay || r.days }}</span>
                  </span>
                  <span v-if="r.academicYear || r.years" class="inline-flex items-center text-purple-700 font-semibold font-mono text-[11px] bg-purple-50 px-2 py-0.5 rounded-md border border-purple-100">
                    <span>{{ r.academicYear || r.years }}</span>
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 whitespace-nowrap">
                <span class="font-extrabold text-slate-900">{{ r.score }}</span>
                <span class="text-xs text-slate-400 font-semibold"> / {{ r.totalMarks }}</span>
              </td>
              <td class="px-4 py-3 min-w-[140px]">
                <div class="flex items-center gap-2">
                  <div class="w-20">
                    <ProgressBar
                      :value="r.accuracy"
                      :max="100"
                      variant="dynamic"
                      size="sm"
                    />
                  </div>
                  <span
                    :class="[
                      'text-xs font-extrabold',
                      r.accuracy >= 80 ? 'text-emerald-600' : r.accuracy >= 50 ? 'text-amber-600' : 'text-red-500'
                    ]"
                  >
                    {{ r.accuracy }}%
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">
                <span v-if="r.completedAt">{{ formatDate(r.completedAt) }}</span>
                <span v-else class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                  <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                  {{ lang === 'kh' ? 'កំពុងប្រឡង...' : 'In Progress...' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <IconButton
                    icon="visibility"
                    variant="ghost"
                    size="sm"
                    title="View Detail"
                    class="text-blue-600 hover:bg-blue-50"
                    @click="viewResult(r.id)"
                  />
                  <IconButton
                    v-if="can('Results', 'delete')"
                    icon="delete"
                    variant="ghost"
                    size="sm"
                    title="Delete Submission"
                    class="text-red-500 hover:text-red-700 hover:bg-red-50"
                    @click="confirmDeleteResult(r)"
                  />
                </div>
              </td>
            </tr>
            <tr v-if="initialLoading && filteredResults.length === 0">
              <td colspan="6" class="py-12 text-center">
                <div class="inline-flex items-center gap-2 text-slate-400 text-xs font-semibold">
                  <span class="h-4 w-4 rounded-full border-2 border-blue-600 border-t-transparent animate-spin"></span>
                  <span>{{ lang === 'kh' ? 'កំពុងផ្ទុកទិន្នន័យ...' : 'Loading data...' }}</span>
                </div>
              </td>
            </tr>
            <tr v-else-if="filteredResults.length === 0">
              <td colspan="6">
                <EmptyState
                  icon="analytics"
                  :title="t.noResults"
                  :description="t.noResultsDesc"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Card List View -->
      <div class="md:hidden divide-y divide-slate-100">
        <div v-for="r in paginatedResults" :key="r.id" class="p-4 space-y-3">
          <!-- Header: Student + Score Badge -->
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2.5">
              <div class="h-9 w-9 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center font-bold text-xs shrink-0 border border-blue-100">
                {{ (r.studentName || 'S').charAt(0).toUpperCase() }}
              </div>
              <div>
                <h4 class="font-bold text-slate-900 text-sm leading-tight">{{ r.studentName }}</h4>
                <p v-if="r.studentCode" class="text-xs font-mono text-blue-600 font-bold mt-0.5">{{ r.studentCode }}</p>
              </div>
            </div>
            <span class="text-xs font-extrabold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-lg border border-blue-100">
              {{ r.score }} / {{ r.totalMarks }}
            </span>
          </div>

          <!-- Exam & Accuracy Details -->
          <div class="text-xs font-semibold text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-100 space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="text-slate-500">{{ t.test }}:</span>
              <div class="text-right">
                <div class="text-[11px] text-slate-500 flex flex-wrap items-center gap-1 mt-0.5 justify-end">
                  <span>{{ r.sessionName }}</span>
                  <span v-if="(r.examDay || r.days) && !isWeekdayName(r.examDay || r.days)" class="bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded text-[10px] font-semibold border border-blue-100">
                    {{ r.examDay || r.days }}
                  </span>
                  <span v-if="r.academicYear || r.years" class="bg-purple-50 text-purple-700 px-1.5 py-0.2 rounded text-[10px] font-medium font-mono border border-purple-100">
                    {{ r.academicYear || r.years }}
                  </span>
                </div>
              </div>
            </div>
            <div class="flex items-center justify-between pt-1 border-t border-slate-200/60">
              <span class="text-slate-500">{{ t.accuracy }}:</span>
              <span class="font-bold" :class="r.accuracy >= 70 ? 'text-emerald-600' : 'text-amber-600'">{{ r.accuracy }}%</span>
            </div>
          </div>

          <!-- Footer: Date + Actions -->
          <div class="flex items-center justify-between pt-1 text-xs">
            <span v-if="r.completedAt" class="text-slate-400 font-mono">{{ formatDate(r.completedAt) }}</span>
            <span v-else class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
              <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
              {{ lang === 'kh' ? 'កំពុងប្រឡង' : 'In Progress' }}
            </span>
            <div class="flex items-center gap-1.5">
              <Button variant="outline" size="xs" icon="visibility" @click="viewResult(r.id)">{{ lang === 'kh' ? 'ពិនិត្យ' : 'View' }}</Button>
              <Button v-if="can('Results', 'delete')" variant="danger" size="xs" icon="delete" @click="confirmDeleteResult(r)">{{ lang === 'kh' ? 'លុប' : 'Delete' }}</Button>
            </div>
          </div>
        </div>

        <div v-if="initialLoading && filteredResults.length === 0" class="py-12 text-center">
          <div class="inline-flex items-center gap-2 text-slate-400 text-xs font-semibold">
            <span class="h-4 w-4 rounded-full border-2 border-blue-600 border-t-transparent animate-spin"></span>
            <span>{{ lang === 'kh' ? 'កំពុងផ្ទុកទិន្នន័យ...' : 'Loading data...' }}</span>
          </div>
        </div>
        <div v-else-if="filteredResults.length === 0" class="p-6">
          <EmptyState
            icon="analytics"
            :title="t.noResults"
            :description="t.noResultsDesc"
          />
        </div>
      </div>

      <Pagination
        v-if="filteredResults.length > pageSize"
        v-model:currentPage="currentPage"
        :pageSize="pageSize"
        :totalItems="filteredResults.length"
      />
    </Card>
    </template>

    <!-- ── Delete Confirm Dialog ───────────────────────────────────── -->
    <ConfirmDialog
      v-model="showDeleteDialog"
      :title="t.deleteResultTitle"
      :message="deleteConfirmMessage"
      :confirm-text="t.delete"
      :cancel-text="t.cancel"
      confirm-variant="danger"
      icon="delete"
      :loading="deleting"
      @confirm="performDelete"
    />

    <!-- ── Export Modal Dialog ───────────────────────────────────────── -->
    <Modal
      v-model="showExportModal"
      :title="t.exportTitle"
      :subtitle="t.exportDesc"
      max-width="lg"
      :overflow-visible="true"
    >
      <div class="space-y-4">
        <!-- Format Selector Buttons -->
        <div class="space-y-1.5">
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
            {{ lang === 'kh' ? 'ទម្រង់ឯកសារនាំចេញ' : 'Export File Format' }}
          </label>
          <div class="grid grid-cols-2 gap-3 p-1 bg-slate-100/90 rounded-2xl border border-slate-200/60">
            <button
              type="button"
              @click="exportFormat = 'excel'"
              :class="[
                'flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl font-bold text-sm transition-all cursor-pointer',
                exportFormat === 'excel'
                  ? 'bg-white text-emerald-700 shadow-sm border border-emerald-200 ring-2 ring-emerald-500/20'
                  : 'text-slate-600 hover:text-slate-900'
              ]"
            >
              <span class="material-symbols-outlined text-base text-emerald-600">table_view</span>
              <span>Excel (.xlsx)</span>
            </button>
            <button
              type="button"
              @click="exportFormat = 'pdf'"
              :class="[
                'flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl font-bold text-sm transition-all cursor-pointer',
                exportFormat === 'pdf'
                  ? 'bg-white text-red-700 shadow-sm border border-red-200 ring-2 ring-red-500/20'
                  : 'text-slate-600 hover:text-slate-900'
              ]"
            >
              <span class="material-symbols-outlined text-base text-red-600">picture_as_pdf</span>
              <span>PDF (.pdf)</span>
            </button>
          </div>
        </div>

        <!-- Academic Year Selector (Prominent) -->
        <div class="space-y-1.5 bg-gradient-to-r from-blue-50/80 to-indigo-50/60 p-4 rounded-2xl border border-blue-100">
          <div class="flex items-center justify-between mb-1">
            <label class="text-xs font-bold uppercase tracking-wider text-blue-950 flex items-center gap-1.5">
              <span class="material-symbols-outlined text-base text-blue-600">calendar_month</span>
              <span>{{ lang === 'kh' ? 'ជ្រើសរើសឆ្នាំសិក្សាដែលចង់ទាញយក' : 'Select Academic Year to Export' }}</span>
            </label>
            <span v-if="exportYear" class="text-[11px] font-mono font-bold text-blue-700 bg-white px-2 py-0.5 rounded-md border border-blue-200 shadow-2xs">
              {{ exportYear }}
            </span>
          </div>
          <CustomDropdown
            v-model="exportYear"
            :options="yearOptions"
            labelKey="label"
            valueKey="value"
            :placeholder="t.selectYear"
          />
          <p class="text-[11px] text-blue-600/80 mt-1">
            {{ lang === 'kh' ? 'ជ្រើសរើសឆ្នាំសិក្សាជាក់លាក់មួយ ឬជ្រើស "ឆ្នាំសិក្សាទាំងអស់" ដើម្បីទាញយកទិន្នន័យទាំងអស់' : 'Choose a specific academic year or select "All Academic Years" to export all results.' }}
          </p>
        </div>

        <!-- Session & Exam Filters -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
              {{ lang === 'kh' ? 'វេនប្រឡង' : 'Exam Shift' }}
            </label>
            <CustomDropdown
              v-model="exportSession"
              :options="[{ label: t.selectSession, value: '' }, ...sessionOptions]"
              labelKey="label"
              valueKey="value"
              :placeholder="t.selectSession"
            />
          </div>
          <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
              {{ lang === 'kh' ? 'វិញ្ញាសាប្រឡង' : 'Exam Test' }}
            </label>
            <CustomDropdown
              v-model="exportTest"
              :options="[{ TestName: t.selectTest, TestId: '' }, ...tests]"
              labelKey="TestName"
              valueKey="TestId"
              :placeholder="t.selectTest"
            />
          </div>
        </div>

        <!-- Preview Count Banner -->
        <div
          v-if="exportFilteredResults.length > 0"
          class="p-3.5 rounded-2xl bg-emerald-50/70 border border-emerald-200/80 flex items-center justify-between"
        >
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-extrabold text-base shadow-sm shrink-0">
              {{ exportFilteredResults.length }}
            </div>
            <div>
              <div class="text-xs font-bold text-emerald-950">
                {{ t.exportReady }}
              </div>
              <div class="text-[11px] text-emerald-700 mt-0.5">
                {{ lang === 'kh' ? 'ឆ្នាំសិក្សា:' : 'Academic Year:' }}
                <span class="font-bold font-mono">{{ exportYear || (lang === 'kh' ? 'គ្រប់ឆ្នាំទាំងអស់' : 'All Years') }}</span>
              </div>
            </div>
          </div>
          <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white text-emerald-800 font-bold text-xs border border-emerald-200 shadow-2xs">
            <span class="material-symbols-outlined text-xs text-emerald-600">check_circle</span>
            <span>{{ exportFilteredResults.length }} {{ lang === 'kh' ? 'នាក់' : 'records' }}</span>
          </span>
        </div>
        <div
          v-else
          class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200 flex items-center gap-2.5 text-amber-800 text-xs"
        >
          <span class="material-symbols-outlined text-amber-600 text-lg shrink-0">warning</span>
          <span v-if="!results.length">
            {{ lang === 'kh' ? 'មិនទាន់មានទិន្នន័យការប្រឡងណាមួយក្នុងប្រព័ន្ធនៅឡើយទេ (មិនទាន់មានបេក្ខជនណាបានប្រឡង)' : 'No exam submission records in the system yet (no candidates have completed an exam).' }}
          </span>
          <span v-else>
            {{ lang === 'kh' ? 'មិនមានទិន្នន័យសម្រាប់ឆ្នាំ ឬលក្ខខណ្ឌដែលបានជ្រើសរើសនេះទេ' : 'No submission records match the selected year and criteria.' }}
          </span>
        </div>
      </div>

      <template #footer>
        <Button variant="outline" @click="showExportModal = false">
          {{ t.cancel }}
        </Button>
        <Button
          v-if="exportFormat === 'excel'"
          variant="primary"
          icon="download"
          :disabled="exportFilteredResults.length === 0"
          @click="executeExportExcel"
          class="bg-emerald-600 hover:bg-emerald-700 text-white border-none shadow-sm cursor-pointer"
        >
          {{ t.downloadExcel }}
        </Button>
        <Button
          v-else
          variant="primary"
          icon="print"
          :disabled="exportFilteredResults.length === 0"
          @click="executeExportPDF"
          class="bg-red-600 hover:bg-red-700 text-white border-none shadow-sm cursor-pointer"
        >
          {{ t.downloadPdf }}
        </Button>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import Card from '../components/ui/Card.vue'
import StatCard from '../components/ui/StatCard.vue'
import Button from '../components/ui/Button.vue'
import IconButton from '../components/ui/IconButton.vue'
import SearchInput from '../components/ui/SearchInput.vue'
import ProgressBar from '../components/ui/ProgressBar.vue'
import Pagination from '../components/ui/Pagination.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Modal from '../components/ui/Modal.vue'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import CustomDropdown from '../components/CustomDropdown.vue'
import Skeleton from '../components/ui/Skeleton.vue'
import { useLang } from '../utils/useLang'
import { useToast } from '../composables/useToast'
import { usePermissions } from '../composables/usePermissions'
import * as XLSX from 'xlsx'

const router = useRouter()
const { lang } = useLang()
const { success: toastSuccess, error: toastError } = useToast()
const { can, fetchUser } = usePermissions()

import { fastCache } from '../stores/fastCache'

const pageSize = 10
const currentPage = ref(1)

const results = ref(fastCache.get('results') || [])
const sessions = ref(fastCache.get('results_sessions') || [])
const tests = ref(fastCache.get('results_tests') || [])
const academicYears = ref(fastCache.get('results_academic_years') || [])
const initialLoading = ref(!results.value.length)

const selectedYear = ref('')
const selectedSession = ref('')
const selectedTest = ref('')
const searchQuery = ref('')

const showExportModal = ref(false)
const exportFormat = ref('excel')
const exportYear = ref('')
const exportSession = ref('')
const exportTest = ref('')

const showDeleteDialog = ref(false)
const submissionToDelete = ref(null)
const deleting = ref(false)

const yearOptions = computed(() => {
  const list = [
    { label: lang.value === 'kh' ? 'ឆ្នាំសិក្សាទាំងអស់' : 'All Academic Years', value: '' }
  ]
  const seen = new Set()

  academicYears.value.forEach(y => {
    const val = (typeof y === 'string' ? y : (y.year || y.name || '')).trim()
    if (val && !seen.has(val)) {
      seen.add(val)
      list.push({ label: val, value: val })
    }
  })

  results.value.forEach(r => {
    const val = (r.academicYear || r.years || '').trim()
    if (val && !seen.has(val)) {
      seen.add(val)
      list.push({ label: val, value: val })
    }
  })

  return list
})

const sessionOptions = computed(() => {
  return sessions.value.map(s => ({
    label: s.SessionName,
    value: s.SessionId,
    subLabel: s.StartTime && s.EndTime ? `${s.StartTime.substring(0, 5)} - ${s.EndTime.substring(0, 5)}` : ''
  }))
})

const t = computed(() => {
  if (lang.value === 'kh') {
    return {
      title: 'លទ្ធផល និង របាយការណ៍ប្រឡង',
      desc: 'វិភាគលទ្ធផលប្រឡងរបស់សិស្ស តាមដានអត្រាពិន្ទុ និងពិនិត្យចម្លើយលម្អិត',
      selectYear: 'ឆ្នាំសិក្សាទាំងអស់',
      selectSession: 'វេនប្រឡងទាំងអស់',
      selectTest: 'ការប្រឡងទាំងអស់',
      searchPlaceholder: 'ស្វែងរកសិស្ស ឬ ការប្រឡង...',
      reset: 'កំណត់ឡើងវិញ',
      totalSubmissions: 'ចំនួនបានប្រឡង',
      avgAccuracy: 'អត្រាត្រឹមត្រូវមធ្យម',
      uniqueStudents: 'សិស្សបានប្រឡង',
      student: 'សិស្ស',
      test: 'ការប្រឡង',
      score: 'ពិន្ទុ',
      accuracy: 'អត្រាត្រឹមត្រូវ',
      date: 'កាលបរិច្ឆេទ',
      actions: 'សកម្មភាព',
      noResults: 'មិនទាន់មានលទ្ធផលប្រឡងទេ',
      noResultsDesc: 'មិនមានទិន្នន័យប្រឡងត្រូវគ្នានឹងការស្វែងរករបស់អ្នកទេ។',
      deleteResultTitle: 'លុបលទ្ធផលប្រឡង?',
      delete: 'លុប',
      cancel: 'បោះបង់',
      exportTitle: 'នាំចេញរបាយការណ៍លទ្ធផលប្រឡង',
      exportDesc: 'ជ្រើសរើសឆ្នាំសិក្សា ទម្រង់ឯកសារ និងលក្ខខណ្ឌដែលចង់នាំចេញ',
      downloadExcel: 'ទាញយក Excel (.xlsx)',
      downloadPdf: 'បោះពុម្ព / ទាញយក PDF',
      exportReady: 'ទិន្នន័យដែលនឹងត្រូវនាំចេញ'
    }
  }
  return {
    title: 'Results & Analytics',
    desc: 'Analyze student examination performances, accuracy rates, and detailed answers',
    selectYear: 'All Academic Years',
    selectSession: 'All Exam Shifts',
    selectTest: 'All Exams',
    searchPlaceholder: 'Search student or exam...',
    reset: 'Reset Filters',
    totalSubmissions: 'Total Submissions',
    avgAccuracy: 'Average Accuracy',
    uniqueStudents: 'Unique Students',
    student: 'Student',
    test: 'Exam Title',
    score: 'Score',
    accuracy: 'Accuracy',
    date: 'Date Completed',
    actions: 'Actions',
    noResults: 'No results found',
    noResultsDesc: 'No submissions match your filter settings.',
    deleteResultTitle: 'Delete Exam Result?',
    delete: 'Delete',
    cancel: 'Cancel',
    exportTitle: 'Export Examination Results Report',
    exportDesc: 'Select academic year, format, and criteria for export',
    downloadExcel: 'Download Excel (.xlsx)',
    downloadPdf: 'Print / Download PDF',
    exportReady: 'Records Ready for Export'
  }
})

const isWeekdayName = (d) => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'].includes(String(d || '').trim().toLowerCase())
const isEnglishDay = isWeekdayName

const matchesYear = (recordYear, filterYear) => {
  if (!filterYear) return true
  if (!recordYear) return false
  const r = String(recordYear).trim().toLowerCase()
  const f = String(filterYear).trim().toLowerCase()
  if (r === f) return true
  if (f.includes(r) && r.length >= 4) return true
  if (r.includes(f) && f.length >= 4) return true
  return false
}

const filteredResults = computed(() => {
  return results.value.filter(r => {
    const q = searchQuery.value.toLowerCase().trim()
    const matchesSearch = !q ||
      (r.studentName && r.studentName.toLowerCase().includes(q)) ||
      (r.studentCode && r.studentCode.toLowerCase().includes(q)) ||
      (r.testName && r.testName.toLowerCase().includes(q))

    const matchesYearFilter = matchesYear(r.academicYear || r.years, selectedYear.value)
    const matchesSession = !selectedSession.value || String(r.sessionId) === String(selectedSession.value)
    const matchesTest = !selectedTest.value || String(r.testId) === String(selectedTest.value)

    return matchesSearch && matchesYearFilter && matchesSession && matchesTest
  })
})

const exportFilteredResults = computed(() => {
  return results.value.filter(r => {
    const matchesYearFilter = matchesYear(r.academicYear || r.years, exportYear.value)
    const matchesSession = !exportSession.value || String(r.sessionId) === String(exportSession.value)
    const matchesTest = !exportTest.value || String(r.testId) === String(exportTest.value)
    return matchesYearFilter && matchesSession && matchesTest
  })
})

const paginatedResults = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredResults.value.slice(start, start + pageSize)
})

const avgAccuracy = computed(() => {
  if (!filteredResults.value.length) return 0
  const sum = filteredResults.value.reduce((acc, r) => acc + (r.accuracy || 0), 0)
  return Math.round(sum / filteredResults.value.length)
})

const uniqueStudents = computed(() => {
  const ids = new Set(filteredResults.value.map(r => r.studentId || r.studentName).filter(Boolean))
  return ids.size
})

const deleteConfirmMessage = computed(() => {
  if (!submissionToDelete.value) return ''
  return `Are you sure you want to delete the exam submission for "${submissionToDelete.value.studentName}" on "${submissionToDelete.value.testName}"? This action cannot be undone.`
})

const resetFilters = () => {
  selectedYear.value = ''
  selectedSession.value = ''
  selectedTest.value = ''
  searchQuery.value = ''
  currentPage.value = 1
}

const formatDate = (iso) => {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const openExportModal = (format = 'excel') => {
  exportFormat.value = format
  exportYear.value = selectedYear.value
  exportSession.value = selectedSession.value
  exportTest.value = selectedTest.value
  showExportModal.value = true
}

const exportExcel = () => {
  openExportModal('excel')
}

const exportPDF = () => {
  openExportModal('pdf')
}

const executeExportExcel = () => {
  if (!exportFilteredResults.value.length) {
    toastError(lang.value === 'kh' ? 'គ្មានទិន្នន័យសម្រាប់នាំចេញទេ' : 'No data available to export')
    return
  }

  const exportData = exportFilteredResults.value.map((r, index) => {
    const isPassed = (r.passScore != null && r.passScore > 0) ? (r.score >= r.passScore) : (r.accuracy >= 50)
    const cleanDay = (!isEnglishDay(r.examDay || r.days) ? (r.examDay || r.days) : '') || '-'
    return {
      'ល.រ (No.)': index + 1,
      'ឈ្មោះសិស្ស (Student Name)': r.studentName || '-',
      'អត្តលេខ (Student ID)': r.studentCode || '-',
      'ឆ្នាំសិក្សា (Academic Year)': r.academicYear || r.years || '-',
      'កាលវិភាគថ្ងៃ (Exam Day)': cleanDay,
      'ការប្រឡង (Exam)': r.testName || '-',
      'ពិន្ទុ (Score)': `${r.score} / ${r.totalMarks}`,
      'លទ្ធផល (Result)': isPassed ? 'ជាប់ (Pass)' : 'ធ្លាក់ (Fail)',
      'កាលបរិច្ឆេទ (Date)': formatDate(r.completedAt || r.examDate),
      'ឈ្មោះវេនប្រឡង (Shift Name)': r.sessionName || '-'
    }
  })

  const ws = XLSX.utils.json_to_sheet(exportData)
  ws['!cols'] = [
    { wch: 8 },
    { wch: 26 },
    { wch: 16 },
    { wch: 16 },
    { wch: 18 },
    { wch: 32 },
    { wch: 16 },
    { wch: 16 },
    { wch: 22 },
    { wch: 26 }
  ]
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Exam Results')
  const dateStr = new Date().toISOString().split('T')[0]
  const yearTag = exportYear.value ? `${exportYear.value.replace(/[^a-zA-Z0-9_-]/g, '_')}_` : ''
  XLSX.writeFile(wb, `Exam_Results_${yearTag}${dateStr}.xlsx`)
  showExportModal.value = false
  toastSuccess(lang.value === 'kh' ? 'បាននាំចេញទិន្នន័យជា Excel ដោយជោគជ័យ' : 'Results exported to Excel successfully')
}

const executeExportPDF = () => {
  if (!exportFilteredResults.value.length) {
    toastError(lang.value === 'kh' ? 'គ្មានទិន្នន័យសម្រាប់នាំចេញទេ' : 'No data available to export')
    return
  }

  const rows = exportFilteredResults.value.map((r, idx) => {
    const isPassed = (r.passScore != null && r.passScore > 0) ? (r.score >= r.passScore) : (r.accuracy >= 50)
    const cleanDay = (!isEnglishDay(r.examDay || r.days) ? (r.examDay || r.days) : '') || '-'
    return `
      <tr>
        <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">${idx + 1}</td>
        <td style="padding: 6px 8px; border: 1px solid #cbd5e1; font-weight: bold;">
          ${r.studentName || '-'}
          <div style="font-size: 11px; font-family: monospace; color: #2563eb;">${r.studentCode || ''}</div>
        </td>
        <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1; font-family: monospace; font-weight: bold; color: #1e3a8a;">
          ${r.academicYear || r.years || '-'}
        </td>
        <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1; font-size: 11px; color: #0369a1;">
          ${cleanDay}
        </td>
        <td style="padding: 6px 8px; border: 1px solid #cbd5e1;">${r.testName || '-'}</td>
        <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1; font-weight: bold;">
          ${r.score} / ${r.totalMarks}
        </td>
        <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">
          <span style="display: inline-block; padding: 2px 6px; border-radius: 9999px; font-size: 10px; font-weight: bold; background-color: ${isPassed ? '#dcfce7' : '#fee2e2'}; color: ${isPassed ? '#166534' : '#991b1b'};">
            ${isPassed ? 'ជាប់' : 'ធ្លាក់'}
          </span>
        </td>
        <td style="padding: 6px 8px; border: 1px solid #cbd5e1; font-size: 11px;">${formatDate(r.completedAt || r.examDate)}</td>
        <td style="padding: 6px 8px; border: 1px solid #cbd5e1;">${r.sessionName || '-'}</td>
      </tr>
    `
  }).join('')

  const printWindow = window.open('', '_blank')
  if (!printWindow) {
    toastError(lang.value === 'kh' ? 'សូមអនុញ្ញាតបើក Pop-up ដើម្បីបោះពុម្ព PDF' : 'Please allow popups to export PDF')
    return
  }

  const title = lang.value === 'kh' ? 'របាយការណ៍លទ្ធផលប្រឡង' : 'Examination Results Report'
  const dateStr = new Date().toLocaleDateString(lang.value === 'kh' ? 'km-KH' : 'en-US', {
    year: 'numeric', month: 'long', day: 'numeric'
  })
  const yearDisplay = exportYear.value || (lang.value === 'kh' ? 'គ្រប់ឆ្នាំទាំងអស់ (All Academic Years)' : 'All Academic Years')

  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>${title} - ${yearDisplay}</title>
        <style>
          @page { size: landscape; margin: 10mm; }
          body { font-family: 'Kantumruy Pro', 'Inter', system-ui, sans-serif; margin: 0; color: #0f172a; font-size: 11px; }
          .header { text-align: center; margin-bottom: 16px; border-bottom: 2px solid #0284c7; padding-bottom: 8px; }
          .header h1 { margin: 0 0 4px; font-size: 18px; color: #0f172a; }
          .header .year-badge { display: inline-block; font-size: 12px; font-weight: bold; color: #1e40af; background: #eff6ff; padding: 2px 10px; border-radius: 9999px; margin-top: 4px; border: 1px solid #bfdbfe; }
          .header p { margin: 6px 0 0; font-size: 11px; color: #64748b; }
          table { width: 100%; border-collapse: collapse; margin-top: 8px; }
          th { background-color: #f8fafc; padding: 8px; border: 1px solid #94a3b8; font-weight: bold; text-align: left; font-size: 10px; text-transform: uppercase; color: #475569; }
          @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
          }
        </style>
      </head>
      <body>
        <div class="header">
          <h1>${title}</h1>
          <div class="year-badge">${lang.value === 'kh' ? 'ឆ្នាំសិក្សា' : 'Academic Year'}: ${yearDisplay}</div>
          <p>${lang.value === 'kh' ? 'កាលបរិច្ឆេទបង្កើត' : 'Generated on'}: ${dateStr} | ${lang.value === 'kh' ? 'ចំនួនសិស្សប្រឡង' : 'Total Submissions'}: ${exportFilteredResults.value.length} ${lang.value === 'kh' ? 'នាក់' : 'candidates'}</p>
        </div>
        <table>
          <thead>
            <tr>
              <th style="text-align: center; width: 35px;">#</th>
              <th>${lang.value === 'kh' ? 'ឈ្មោះសិស្ស (Student Name)' : 'Student Name'}</th>
              <th style="text-align: center;">${lang.value === 'kh' ? 'ឆ្នាំសិក្សា (Year)' : 'Academic Year'}</th>
              <th style="text-align: center;">${lang.value === 'kh' ? 'កាលវិភាគថ្ងៃ (Day)' : 'Exam Day'}</th>
              <th>${lang.value === 'kh' ? 'ការប្រឡង (Exam)' : 'Exam'}</th>
              <th style="text-align: center;">${lang.value === 'kh' ? 'ពិន្ទុ (Score)' : 'Score'}</th>
              <th style="text-align: center;">${lang.value === 'kh' ? 'លទ្ធផល' : 'Result'}</th>
              <th>${lang.value === 'kh' ? 'កាលបរិច្ឆេទ (Date)' : 'Date'}</th>
              <th>${lang.value === 'kh' ? 'ឈ្មោះវេនប្រឡង (Shift)' : 'Shift'}</th>
            </tr>
          </thead>
          <tbody>
            ${rows}
          </tbody>
        </table>
        <script>
          window.onload = function() {
            setTimeout(function() {
              window.print();
            }, 300);
          }
        <\/script>
      </body>
    </html>
  `)
  printWindow.document.close()
  showExportModal.value = false
}

const viewResult = (id) => {
  router.push({ name: 'AdminResultDetail', params: { submissionId: id } })
}

const confirmDeleteResult = (r) => {
  submissionToDelete.value = r
  showDeleteDialog.value = true
}

import { useRealtimePoll, broadcastSync } from '../composables/useRealtimePoll'

const performDelete = async () => {
  if (!submissionToDelete.value) return
  deleting.value = true
  try {
    await axios.delete(`/api/admin/results/${submissionToDelete.value.id}`)
    toastSuccess('Submission deleted successfully.')
    showDeleteDialog.value = false
    broadcastSync('results_updated')
    await loadData()
  } catch (e) {
    toastError('Failed to delete submission.')
  } finally {
    deleting.value = false
  }
}

const loadData = async (isBackground = false) => {
  try {
    const [res] = await Promise.all([
      axios.get('/api/admin/results'),
      fetchUser()
    ])
    results.value = res.data.results || []
    sessions.value = res.data.sessions || []
    tests.value = res.data.tests || []
    academicYears.value = res.data.academicYears || []

    fastCache.set('results', results.value)
    fastCache.set('results_sessions', sessions.value)
    fastCache.set('results_tests', tests.value)
    fastCache.set('results_academic_years', academicYears.value)
  } catch (e) {
    if (!isBackground) {
      console.error('Failed to load results', e)
    }
  } finally {
    initialLoading.value = false
  }
}

useRealtimePoll(loadData, { interval: 3500, listenEvents: ['results_updated'] })
</script>
