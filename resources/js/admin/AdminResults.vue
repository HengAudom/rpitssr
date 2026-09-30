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

        <!-- Report View Mode Toggle (Student Consolidated vs Detailed) -->
        <div class="space-y-1.5">
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
            {{ lang === 'kh' ? 'ទម្រង់របាយការណ៍នាំចេញ' : 'Report Layout' }}
          </label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            <button
              type="button"
              @click="exportViewMode = 'student'"
              :class="[
                'p-3 rounded-2xl border text-left transition-all cursor-pointer flex items-start gap-2.5',
                exportViewMode === 'student'
                  ? 'bg-blue-50/80 border-blue-300 ring-2 ring-blue-500/20 shadow-2xs'
                  : 'bg-white border-slate-200 hover:border-slate-300 text-slate-600'
              ]"
            >
              <div :class="[
                'w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5',
                exportViewMode === 'student' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-500'
              ]">
                <span class="material-symbols-outlined text-base">group</span>
              </div>
              <div class="min-w-0">
                <div :class="['text-xs font-bold', exportViewMode === 'student' ? 'text-blue-950' : 'text-slate-800']">
                  {{ lang === 'kh' ? 'សរុបតាមបេក្ខជន (១ជួរក្នុង១មុខវិជ្ជា)' : 'Grouped by Candidate (1 row per subject)' }}
                </div>
                <div class="text-[11px] text-slate-500 mt-0.5 leading-snug">
                  {{ lang === 'kh' ? 'បំបែកជួរដេកតាមមុខវិជ្ជានីមួយៗដែលបេក្ខជនបានប្រឡង (ឧ. ប្រឡង ២ មុខវិជ្ជា បង្ហាញ ២ ជួរដេក)' : 'Generates a row for each subject taken by the candidate (e.g. 2 subjects = 2 rows).' }}
                </div>
              </div>
            </button>

            <button
              type="button"
              @click="exportViewMode = 'detail'"
              :class="[
                'p-3 rounded-2xl border text-left transition-all cursor-pointer flex items-start gap-2.5',
                exportViewMode === 'detail'
                  ? 'bg-blue-50/80 border-blue-300 ring-2 ring-blue-500/20 shadow-2xs'
                  : 'bg-white border-slate-200 hover:border-slate-300 text-slate-600'
              ]"
            >
              <div :class="[
                'w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5',
                exportViewMode === 'detail' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-500'
              ]">
                <span class="material-symbols-outlined text-base">description</span>
              </div>
              <div class="min-w-0">
                <div :class="['text-xs font-bold', exportViewMode === 'detail' ? 'text-blue-950' : 'text-slate-800']">
                  {{ lang === 'kh' ? 'លម្អិតតាមវិញ្ញាសានីមួយៗ' : 'Detailed Submissions' }}
                </div>
                <div class="text-[11px] text-slate-500 mt-0.5 leading-snug">
                  {{ lang === 'kh' ? 'បង្ហាញមួយជួរសម្រាប់វិញ្ញាសានីមួយៗដែលបានប្រឡង' : 'Shows each exam submission row separately.' }}
                </div>
              </div>
            </button>
          </div>
        </div>

        <!-- Preview Count Banner -->
        <div
          v-if="exportFilteredResults.length > 0"
          class="p-3.5 rounded-2xl bg-emerald-50/70 border border-emerald-200/80 flex items-center justify-between"
        >
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-extrabold text-base shadow-sm shrink-0">
              {{ exportViewMode === 'student' ? exportGroupedStudents.length : exportFilteredResults.length }}
            </div>
            <div>
              <div class="text-xs font-bold text-emerald-950">
                {{ exportViewMode === 'student' ? (lang === 'kh' ? 'បេក្ខជនត្រៀមទាញយក' : 'Candidates Ready for Export') : t.exportReady }}
              </div>
              <div class="text-[11px] text-emerald-700 mt-0.5 flex flex-wrap items-center gap-x-2">
                <span>
                  {{ lang === 'kh' ? 'ឆ្នាំសិក្សា:' : 'Year:' }}
                  <span class="font-bold font-mono">{{ exportYear || (lang === 'kh' ? 'គ្រប់ឆ្នាំទាំងអស់' : 'All') }}</span>
                </span>
                <span>•</span>
                <span>
                  {{ lang === 'kh' ? 'វេន:' : 'Shift:' }}
                  <span class="font-bold">{{ exportSessionName }}</span>
                </span>
              </div>
            </div>
          </div>
          <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white text-emerald-800 font-bold text-xs border border-emerald-200 shadow-2xs">
            <span class="material-symbols-outlined text-xs text-emerald-600">check_circle</span>
            <span v-if="exportViewMode === 'student'">
              {{ exportGroupedStudents.length }} {{ lang === 'kh' ? 'បេក្ខជន' : 'candidates' }} ({{ exportFilteredResults.length }} {{ lang === 'kh' ? 'វិញ្ញាសា' : 'tests' }})
            </span>
            <span v-else>
              {{ exportFilteredResults.length }} {{ lang === 'kh' ? 'កំណត់ត្រា' : 'records' }}
            </span>
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
const exportViewMode = ref('student')
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
  const sessObj = sessions.value.find(s => String(s.SessionId) === String(exportSession.value))
  const sessName = sessObj ? String(sessObj.SessionName).trim() : ''

  return results.value.filter(r => {
    const matchesYearFilter = matchesYear(r.academicYear || r.years, exportYear.value)
    const matchesSession = !exportSession.value || 
      String(r.sessionId) === String(exportSession.value) || 
      (sessName && String(r.sessionName).trim() === sessName)
    const matchesTest = !exportTest.value || String(r.testId) === String(exportTest.value)
    return matchesYearFilter && matchesSession && matchesTest
  })
})

const exportSessionName = computed(() => {
  if (!exportSession.value) return lang.value === 'kh' ? 'គ្រប់វេនទាំងអស់' : 'All Exam Shifts'
  const s = sessions.value.find(sess => String(sess.SessionId) === String(exportSession.value))
  return s ? s.SessionName : (lang.value === 'kh' ? 'គ្រប់វេនទាំងអស់' : 'All Exam Shifts')
})

const exportDistinctTests = computed(() => {
  const map = new Map()
  exportFilteredResults.value.forEach(r => {
    const testId = r.testId ? String(r.testId) : ''
    const testName = r.testName || (testId ? `Test ${testId}` : 'Exam')
    const key = testId || testName
    if (!map.has(key)) {
      map.set(key, { id: testId, name: testName })
    }
  })
  return Array.from(map.values())
})

const exportGroupedStudents = computed(() => {
  const map = new Map()

  exportFilteredResults.value.forEach(r => {
    const key = r.studentId ? `id_${r.studentId}` : (r.studentCode ? `code_${r.studentCode}` : `name_${r.studentName}`)

    if (!map.has(key)) {
      map.set(key, {
        studentId: r.studentId,
        studentCode: r.studentCode || '-',
        studentName: r.studentName || '-',
        academicYear: r.academicYear || r.years || '-',
        sessionName: r.sessionName || '-',
        sessionId: r.sessionId,
        examDay: (!isEnglishDay(r.examDay || r.days) ? (r.examDay || r.days) : '') || '-',
        examDate: r.completedAt || r.examDate || r.startedAt,
        submissions: []
      })
    }

    const student = map.get(key)
    const isPassed = (r.passScore != null && r.passScore > 0) ? (r.score >= r.passScore) : (r.accuracy >= 50)

    const subData = {
      testId: r.testId,
      testName: r.testName || 'វិញ្ញាសា',
      score: Number(r.score) || 0,
      totalMarks: Number(r.totalMarks) || 0,
      totalCorrect: Number(r.totalCorrect) || 0,
      accuracy: Number(r.accuracy) || 0,
      passScore: r.passScore,
      isPassed,
      completedAt: r.completedAt
    }

    const existingIdx = student.submissions.findIndex(s =>
      (r.testId && String(s.testId) === String(r.testId)) ||
      (r.testName && s.testName === r.testName)
    )

    if (existingIdx >= 0) {
      if ((Number(r.score) || 0) >= student.submissions[existingIdx].score) {
        student.submissions[existingIdx] = subData
      }
    } else {
      student.submissions.push(subData)
    }

    if (r.completedAt && (!student.examDate || new Date(r.completedAt) > new Date(student.examDate))) {
      student.examDate = r.completedAt
    }
  })

  return Array.from(map.values()).map(s => {
    const totalScore = s.submissions.reduce((acc, sub) => acc + sub.score, 0)
    const totalMaxMarks = s.submissions.reduce((acc, sub) => acc + sub.totalMarks, 0)
    const totalCorrect = s.submissions.reduce((acc, sub) => acc + sub.totalCorrect, 0)
    const allPassed = s.submissions.length > 0 && s.submissions.every(sub => sub.isPassed)
    const avgAccuracy = totalMaxMarks > 0
      ? Math.round((totalScore / totalMaxMarks) * 100)
      : (s.submissions.length ? Math.round(s.submissions.reduce((acc, sub) => acc + sub.accuracy, 0) / s.submissions.length) : 0)

    const examsSummary = s.submissions.map(sub => `${sub.testName}: ${sub.score}/${sub.totalMarks}`).join(' | ')

    return {
      ...s,
      totalScore,
      totalMaxMarks,
      totalCorrect,
      allPassed,
      avgAccuracy,
      examsSummary,
      examCount: s.submissions.length
    }
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
  exportViewMode.value = 'student' // Default to consolidated candidate layout

  if (selectedYear.value) {
    exportYear.value = selectedYear.value
  } else if (academicYears.value && academicYears.value.length) {
    const firstY = academicYears.value[0]
    exportYear.value = typeof firstY === 'string' ? firstY : (firstY.year || firstY.name || '')
  } else if (results.value.length) {
    const foundY = results.value.find(r => r.academicYear || r.years)
    exportYear.value = foundY ? (foundY.academicYear || foundY.years) : ''
  } else {
    exportYear.value = ''
  }

  exportSession.value = selectedSession.value || ''
  exportTest.value = selectedTest.value || ''
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

  let exportData = []
  let colWidths = []

  if (exportViewMode.value === 'student') {
    const grouped = exportGroupedStudents.value
    exportData = []

    grouped.forEach((s, studentIdx) => {
      if (!s.submissions || !s.submissions.length) {
        exportData.push({
          'ល.រ (No.)': studentIdx + 1,
          'ឈ្មោះសិស្ស (Student Name)': s.studentName,
          'អត្តលេខ (Student ID)': s.studentCode,
          'ឆ្នាំសិក្សា (Academic Year)': s.academicYear,
          'វេនប្រឡង (Exam Shift)': s.sessionName,
          'កាលវិភាគថ្ងៃ (Exam Day)': s.examDay,
          'មុខវិជ្ជា/វិញ្ញាសាដែលបានប្រឡង': '-',
          'ពិន្ទុ (Score)': '-',
          'អត្រាត្រឹមត្រូវ (Accuracy)': '-',
          'លទ្ធផល (Result)': '-',
          'ពិន្ទុសរុបគ្រប់មុខ (Total Score)': '-',
          'លទ្ធផលរួម (Overall Result)': '-',
          'កាលបរិច្ឆេទ (Date)': formatDate(s.examDate)
        })
      } else {
        s.submissions.forEach((sub) => {
          exportData.push({
            'ល.រ (No.)': studentIdx + 1,
            'ឈ្មោះសិស្ស (Student Name)': s.studentName,
            'អត្តលេខ (Student ID)': s.studentCode,
            'ឆ្នាំសិក្សា (Academic Year)': s.academicYear,
            'វេនប្រឡង (Exam Shift)': s.sessionName,
            'កាលវិភាគថ្ងៃ (Exam Day)': s.examDay,
            'មុខវិជ្ជា/វិញ្ញាសាដែលបានប្រឡង': sub.testName || '-',
            'ពិន្ទុ (Score)': `${sub.score} / ${sub.totalMarks}`,
            'អត្រាត្រឹមត្រូវ (Accuracy)': `${sub.accuracy}%`,
            'លទ្ធផល (Result)': sub.isPassed ? 'ជាប់ (Pass)' : 'ធ្លាក់ (Fail)',
            'ពិន្ទុសរុបគ្រប់មុខ (Total Score)': `${s.totalScore} / ${s.totalMaxMarks}`,
            'លទ្ធផលរួម (Overall Result)': s.allPassed ? 'ជាប់ (Pass)' : 'ធ្លាក់ (Fail)',
            'កាលបរិច្ឆេទ (Date)': formatDate(sub.completedAt || s.examDate)
          })
        })
      }
    })

    colWidths = [
      { wch: 8 },  // No.
      { wch: 24 }, // Name
      { wch: 16 }, // ID
      { wch: 16 }, // Academic Year
      { wch: 24 }, // Shift
      { wch: 16 }, // Day
      { wch: 34 }, // មុខវិជ្ជា/វិញ្ញាសាដែលបានប្រឡង
      { wch: 16 }, // Score
      { wch: 16 }, // Accuracy
      { wch: 16 }, // Result
      { wch: 22 }, // Total Score
      { wch: 16 }, // Overall Result
      { wch: 22 }  // Date
    ]
  } else {
    // Detail by submission mode
    exportData = exportFilteredResults.value.map((r, index) => {
      const isPassed = (r.passScore != null && r.passScore > 0) ? (r.score >= r.passScore) : (r.accuracy >= 50)
      const cleanDay = (!isEnglishDay(r.examDay || r.days) ? (r.examDay || r.days) : '') || '-'
      return {
        'ល.រ (No.)': index + 1,
        'ឈ្មោះសិស្ស (Student Name)': r.studentName || '-',
        'អត្តលេខ (Student ID)': r.studentCode || '-',
        'ឆ្នាំសិក្សា (Academic Year)': r.academicYear || r.years || '-',
        'វេនប្រឡង (Exam Shift)': r.sessionName || '-',
        'កាលវិភាគថ្ងៃ (Exam Day)': cleanDay,
        'មុខវិជ្ជា/វិញ្ញាសាដែលបានប្រឡង': r.testName || '-',
        'ពិន្ទុ (Score)': `${r.score} / ${r.totalMarks}`,
        'អត្រាត្រឹមត្រូវ (Accuracy)': `${r.accuracy || 0}%`,
        'លទ្ធផល (Result)': isPassed ? 'ជាប់ (Pass)' : 'ធ្លាក់ (Fail)',
        'កាលបរិច្ឆេទ (Date)': formatDate(r.completedAt || r.examDate)
      }
    })

    colWidths = [
      { wch: 8 },
      { wch: 24 },
      { wch: 16 },
      { wch: 16 },
      { wch: 24 },
      { wch: 16 },
      { wch: 34 },
      { wch: 16 },
      { wch: 16 },
      { wch: 16 },
      { wch: 22 }
    ]
  }

  const ws = XLSX.utils.json_to_sheet(exportData)
  ws['!cols'] = colWidths
  const wb = XLSX.utils.book_new()
  const sheetTitle = exportViewMode.value === 'student' ? 'Student Exam Summary' : 'Exam Submissions'
  XLSX.utils.book_append_sheet(wb, ws, sheetTitle)

  const dateStr = new Date().toISOString().split('T')[0]
  const yearTag = exportYear.value ? `${exportYear.value.replace(/[^a-zA-Z0-9_-]/g, '_')}_` : ''
  const shiftObj = sessions.value.find(s => String(s.SessionId) === String(exportSession.value))
  const shiftTag = shiftObj ? `${(shiftObj.SessionName || 'Shift').replace(/[^a-zA-Z0-9\u1780-\u17FF_-]/g, '_')}_` : ''
  const modeTag = exportViewMode.value === 'student' ? 'Summary_' : 'Detail_'

  XLSX.writeFile(wb, `Exam_Results_${modeTag}${yearTag}${shiftTag}${dateStr}.xlsx`)
  showExportModal.value = false
  toastSuccess(lang.value === 'kh' ? 'បាននាំចេញទិន្នន័យជា Excel ដោយជោគជ័យ' : 'Results exported to Excel successfully')
}

const executeExportPDF = () => {
  if (!exportFilteredResults.value.length) {
    toastError(lang.value === 'kh' ? 'គ្មានទិន្នន័យសម្រាប់នាំចេញទេ' : 'No data available to export')
    return
  }

  const printWindow = window.open('', '_blank')
  if (!printWindow) {
    toastError(lang.value === 'kh' ? 'សូមអនុញ្ញាតបើក Pop-up ដើម្បីបោះពុម្ព PDF' : 'Please allow popups to export PDF')
    return
  }

  const isStudentMode = exportViewMode.value === 'student'
  const title = lang.value === 'kh' ? 'របាយការណ៍លទ្ធផលប្រឡង' : 'Examination Results Report'
  const dateStr = new Date().toLocaleDateString(lang.value === 'kh' ? 'km-KH' : 'en-US', {
    year: 'numeric', month: 'long', day: 'numeric'
  })
  const yearDisplay = exportYear.value || (lang.value === 'kh' ? 'គ្រប់ឆ្នាំទាំងអស់ (All Academic Years)' : 'All Academic Years')
  const shiftDisplay = exportSessionName.value

  let tableHeaderHtml = ''
  let tableRowsHtml = ''

  if (isStudentMode) {
    tableHeaderHtml = `
      <tr>
        <th style="text-align: center; width: 35px;">#</th>
        <th>${lang.value === 'kh' ? 'ឈ្មោះសិស្ស (Student Name)' : 'Student Name'}</th>
        <th style="text-align: center;">${lang.value === 'kh' ? 'ឆ្នាំសិក្សា (Year)' : 'Academic Year'}</th>
        <th>${lang.value === 'kh' ? 'វេនប្រឡង (Exam Shift)' : 'Exam Shift'}</th>
        <th>${lang.value === 'kh' ? 'មុខវិជ្ជា/វិញ្ញាសាដែលបានប្រឡង (Exam Subject)' : 'Exam Subject'}</th>
        <th style="text-align: center;">${lang.value === 'kh' ? 'ពិន្ទុ (Score)' : 'Score'}</th>
        <th style="text-align: center;">${lang.value === 'kh' ? 'លទ្ធផល' : 'Result'}</th>
        <th style="text-align: center;">${lang.value === 'kh' ? 'ពិន្ទុសរុបគ្រប់មុខ' : 'Total Score'}</th>
        <th style="text-align: center;">${lang.value === 'kh' ? 'លទ្ធផលរួម' : 'Overall Result'}</th>
        <th>${lang.value === 'kh' ? 'កាលបរិច្ឆេទ (Date)' : 'Date'}</th>
      </tr>
    `

    const rowItems = []
    exportGroupedStudents.value.forEach((s, sIdx) => {
      const bg = sIdx % 2 === 1 ? 'background-color: #f8fafc;' : ''
      if (!s.submissions || !s.submissions.length) {
        rowItems.push(`
          <tr style="${bg}">
            <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">${sIdx + 1}</td>
            <td style="padding: 6px 8px; border: 1px solid #cbd5e1;">
              <div style="font-weight: bold; color: #0f172a; font-size: 12px;">${s.studentName}</div>
              <div style="font-size: 11px; font-family: monospace; color: #2563eb;">${s.studentCode}</div>
            </td>
            <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1; font-family: monospace; font-weight: bold; color: #1e3a8a;">
              ${s.academicYear}
            </td>
            <td style="padding: 6px 8px; border: 1px solid #cbd5e1; font-size: 11px;">
              <div style="font-weight: 600;">${s.sessionName}</div>
              ${s.examDay && s.examDay !== '-' ? `<div style="font-size: 10px; color: #0369a1;">${s.examDay}</div>` : ''}
            </td>
            <td style="padding: 6px 8px; border: 1px solid #cbd5e1;">-</td>
            <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">-</td>
            <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">-</td>
            <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">-</td>
            <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">-</td>
            <td style="padding: 6px 8px; border: 1px solid #cbd5e1; font-size: 11px;">${formatDate(s.examDate)}</td>
          </tr>
        `)
      } else {
        s.submissions.forEach((sub) => {
          rowItems.push(`
            <tr style="${bg}">
              <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">${sIdx + 1}</td>
              <td style="padding: 6px 8px; border: 1px solid #cbd5e1;">
                <div style="font-weight: bold; color: #0f172a; font-size: 12px;">${s.studentName}</div>
                <div style="font-size: 11px; font-family: monospace; color: #2563eb;">${s.studentCode}</div>
              </td>
              <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1; font-family: monospace; font-weight: bold; color: #1e3a8a;">
                ${s.academicYear}
              </td>
              <td style="padding: 6px 8px; border: 1px solid #cbd5e1; font-size: 11px;">
                <div style="font-weight: 600;">${s.sessionName}</div>
                ${s.examDay && s.examDay !== '-' ? `<div style="font-size: 10px; color: #0369a1;">${s.examDay}</div>` : ''}
              </td>
              <td style="padding: 6px 8px; border: 1px solid #cbd5e1; font-weight: 600; color: #1e293b;">
                ${sub.testName}
              </td>
              <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1; font-weight: bold; color: ${sub.isPassed ? '#166534' : '#991b1b'};">
                ${sub.score} / ${sub.totalMarks}
                <div style="font-size: 10px; font-weight: normal; color: #64748b;">${sub.accuracy}%</div>
              </td>
              <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">
                <span style="display: inline-block; padding: 2px 6px; border-radius: 9999px; font-size: 10px; font-weight: bold; background-color: ${sub.isPassed ? '#dcfce7' : '#fee2e2'}; color: ${sub.isPassed ? '#166534' : '#991b1b'};">
                  ${sub.isPassed ? (lang.value === 'kh' ? 'ជាប់' : 'Pass') : (lang.value === 'kh' ? 'ធ្លាក់' : 'Fail')}
                </span>
              </td>
              <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1; font-weight: bold; font-size: 11px;">
                ${s.totalScore} / ${s.totalMaxMarks}
                <div style="font-size: 10px; font-weight: normal; color: #64748b;">${s.avgAccuracy}% (${s.examCount} ${lang.value === 'kh' ? 'វិញ្ញាសា' : 'tests'})</div>
              </td>
              <td style="text-align: center; padding: 6px 8px; border: 1px solid #cbd5e1;">
                <span style="display: inline-block; padding: 2px 6px; border-radius: 9999px; font-size: 10px; font-weight: bold; background-color: ${s.allPassed ? '#dcfce7' : '#fee2e2'}; color: ${s.allPassed ? '#166534' : '#991b1b'};">
                  ${s.allPassed ? (lang.value === 'kh' ? 'ជាប់' : 'Pass') : (lang.value === 'kh' ? 'ធ្លាក់' : 'Fail')}
                </span>
              </td>
              <td style="padding: 6px 8px; border: 1px solid #cbd5e1; font-size: 11px;">${formatDate(sub.completedAt || s.examDate)}</td>
            </tr>
          `)
        })
      }
    })
    tableRowsHtml = rowItems.join('')
  } else {
    tableHeaderHtml = `
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
    `

    tableRowsHtml = exportFilteredResults.value.map((r, idx) => {
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
  }

  const candidateSummaryText = isStudentMode
    ? `${lang.value === 'kh' ? 'ចំនួនបេក្ខជនសរុប' : 'Total Candidates'}: ${exportGroupedStudents.value.length} ${lang.value === 'kh' ? 'នាក់' : 'candidates'} (${exportFilteredResults.value.length} ${lang.value === 'kh' ? 'វិញ្ញាសា' : 'test submissions'})`
    : `${lang.value === 'kh' ? 'ចំនួនសិស្សប្រឡង' : 'Total Submissions'}: ${exportFilteredResults.value.length} ${lang.value === 'kh' ? 'នាក់' : 'records'}`

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
          .header .badges { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 6px; }
          .header .badge { display: inline-block; font-size: 11px; font-weight: bold; color: #1e40af; background: #eff6ff; padding: 2px 10px; border-radius: 9999px; border: 1px solid #bfdbfe; }
          .header .badge-shift { color: #065f46; background: #ecfdf5; border-color: #a7f3d0; }
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
          <div class="badges">
            <span class="badge">${lang.value === 'kh' ? 'ឆ្នាំសិក្សា' : 'Academic Year'}: ${yearDisplay}</span>
            <span class="badge badge-shift">${lang.value === 'kh' ? 'វេនប្រឡង' : 'Exam Shift'}: ${shiftDisplay}</span>
          </div>
          <p>${lang.value === 'kh' ? 'កាលបរិច្ឆេទបង្កើត' : 'Generated on'}: ${dateStr} | ${candidateSummaryText}</p>
        </div>
        <table>
          <thead>
            ${tableHeaderHtml}
          </thead>
          <tbody>
            ${tableRowsHtml}
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
