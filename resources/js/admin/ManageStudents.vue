<template>
  <div v-if="!can('Students', 'view')" class="min-h-[60vh] flex flex-col items-center justify-center p-6 text-center">
    <Card padding="lg" class="max-w-md w-full shadow-soft-md border-slate-200">
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 mb-4 border border-amber-100">
        <span class="material-symbols-outlined text-4xl">lock</span>
      </div>
      <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs font-bold border border-amber-200 mb-2">
        <span class="material-symbols-outlined text-xs">admin_panel_settings</span>
        {{ lang === 'kh' ? 'សិទ្ធិត្រូវបានកំណត់' : 'Access Restricted' }}
      </span>
      <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight mt-1">
        {{ lang === 'kh' ? 'មិនមានសិទ្ធិចូលមើលបញ្ជីបេក្ខជនទេ' : 'Permission Required' }}
      </h1>
      <p class="text-sm text-slate-500 mt-2 leading-relaxed">
        {{ lang === 'kh'
          ? 'គណនីរបស់អ្នកមិនមានសិទ្ធិមើលបញ្ជីបេក្ខជន (Students) ឡើយ។ សូមទាក់ទង Super Admin។'
          : 'You do not have permission to view the candidates directory. Please contact a Super Administrator.'
        }}
      </p>
    </Card>
  </div>

  <div v-else class="space-y-6">
    <!-- Header & Action Bar -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
          {{ t.title }}
        </h1>
        <p class="text-sm text-slate-500 mt-1">
          {{ t.desc }}
        </p>
      </div>

      <div class="flex items-center gap-2.5 flex-wrap">
        <Button
          v-if="can('Students', 'export') || can('Students', 'create')"
          variant="outline"
          icon="table_view"
          size="sm"
          class="bg-white border-blue-200 text-blue-700 hover:bg-blue-50 font-bold"
          @click="openExcelHubModal('export')"
        >
          {{ lang === 'kh' ? 'ឯកសារ Excel' : 'Excel Files' }}
        </Button>
        <Button
          v-if="can('Students', 'create')"
          variant="primary"
          icon="person_add"
          size="sm"
          @click="openAddModal"
        >
          {{ t.addStudent }}
        </Button>
      </div>
    </div>

    <!-- Filter Toolbar Card -->
    <Card padding="sm" class="shadow-soft-sm">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <!-- Filter Controls -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 flex-1 max-w-4xl">
          <CustomDropdown
            v-model="filterSession"
            :options="filterSessionOptions"
            labelKey="label"
            valueKey="value"
            :placeholder="t.allSessions"
          />

          <CustomDropdown
            v-model="filterYear"
            :options="filterYearOptions"
            labelKey="label"
            valueKey="value"
            :placeholder="t.allYears"
          />

          <CustomDropdown
            v-model="filterExamStatus"
            :options="examStatusOptions"
            labelKey="label"
            valueKey="value"
            :placeholder="t.examStatus"
          />
        </div>

        <!-- Search & Reset -->
        <div class="flex items-center gap-2">
          <IconButton
            v-if="filterSession || filterYear || filterExamStatus || searchQuery"
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

    <!-- ── Students Table / Cards (Dedicated Students View) ───────────── -->
    <Card padding="none" class="shadow-soft-sm overflow-hidden w-full">
      <template #header>
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center gap-2.5">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 shrink-0">
              <span class="material-symbols-outlined text-lg">group</span>
            </div>
            <div>
              <h3 class="text-sm sm:text-base font-bold text-slate-900 leading-normal">{{ t.studentsList }}</h3>
              <p class="text-xs text-slate-500 font-medium">{{ filteredStudentsList.length }} {{ t.enrolledCount }}</p>
            </div>
          </div>
        </div>
      </template>

      <!-- Desktop Table with all student columns -->
      <div class="hidden md:block overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
          <thead class="bg-slate-50/70">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 w-12">#</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.student }}</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.gender }}</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.examShift }}</th>
              <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.examStatus }}</th>
              <th v-if="can('Students', 'edit') || can('Students', 'delete')" class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-for="(student, index) in paginatedStudents" :key="student.id" class="hover:bg-slate-50/70 transition-colors">
              <td class="px-4 py-3 text-xs font-bold text-slate-400">
                {{ (currentStudentPage - 1) * pageSize + index + 1 }}
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center font-bold text-xs shrink-0 border border-blue-100">
                    {{ (student.name || 'S').charAt(0).toUpperCase() }}
                  </div>
                  <div class="min-w-0">
                    <div class="font-bold text-slate-900 truncate">{{ student.name }}</div>
                    <div class="mt-0.5 space-y-0.5">
                      <div>
                        <span class="font-bold font-mono text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded text-[11px] border border-blue-100 inline-block">
                          {{ student.studentCode || ('SR' + (new Date().getFullYear()) + String(student.id).padStart(5, '0')) }}
                        </span>
                      </div>
                      <div v-if="student.phone" class="text-xs text-slate-400">
                        {{ student.phone }}
                      </div>
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700">
                  {{ student.gender === 'Female' ? (lang === 'kh' ? 'ស្រី' : 'Female') : student.gender === 'Other' ? (lang === 'kh' ? 'ផ្សេងៗ' : 'Other') : (lang === 'kh' ? 'ប្រុស' : 'Male') }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div v-if="student.sessionName && student.sessionName !== 'Unassigned Shift'">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100">
                    {{ student.sessionName }}
                  </span>
                  <div v-if="student.examDay || student.academicYear" class="flex flex-wrap items-center gap-1.5 mt-1">
                    <span v-if="student.examDay" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">
                      <span class="material-symbols-outlined text-[12px]">calendar_today</span>
                      {{ student.examDay }}
                    </span>
                    <span v-if="student.academicYear" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-amber-50 text-amber-700 border border-amber-100">
                      <span class="material-symbols-outlined text-[12px]">school</span>
                      {{ student.academicYear }}
                    </span>
                  </div>
                  <div v-else-if="student.examDate" class="text-[11px] font-medium text-slate-500 mt-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[13px] text-slate-400">calendar_today</span>
                    <span>{{ student.examDate }} ({{ formatTime(student.startTime) }} - {{ formatTime(student.endTime) }})</span>
                  </div>
                </div>
                <div v-else class="text-xs text-slate-400 italic">
                  {{ lang === 'kh' ? 'មិនទាន់កំណត់វេនប្រឡង' : 'No shift assigned' }}
                  <div v-if="student.examDay || student.academicYear" class="flex flex-wrap items-center gap-1.5 mt-1 not-italic">
                    <span v-if="student.examDay" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">
                      <span class="material-symbols-outlined text-[12px]">calendar_today</span>
                      {{ student.examDay }}
                    </span>
                    <span v-if="student.academicYear" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium bg-amber-50 text-amber-700 border border-amber-100">
                      <span class="material-symbols-outlined text-[12px]">school</span>
                      {{ student.academicYear }}
                    </span>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <StatusBadge
                  :status="getStudentExamStatusType(student)"
                  :label="getStudentExamStatusLabel(student)"
                />
                <div
                  v-if="getStudentExamNamesDisplay(student)"
                  class="text-[11px] font-medium text-slate-500 truncate max-w-[200px] mt-0.5"
                  :title="getStudentExamNamesDisplay(student)"
                >
                  {{ getStudentExamNamesDisplay(student) }}
                </div>
              </td>
              <td v-if="can('Students', 'edit') || can('Students', 'delete')" class="px-4 py-3 text-right">
                <div class="flex items-center justify-end gap-1">
                  <IconButton
                    v-if="can('Students', 'edit')"
                    icon="edit"
                    variant="ghost"
                    size="sm"
                    title="Edit Student"
                    class="text-slate-500 hover:text-blue-600 hover:bg-blue-50"
                    @click="editStudent(student)"
                  />
                  <IconButton
                    v-if="can('Students', 'delete')"
                    icon="delete"
                    variant="ghost"
                    size="sm"
                    title="Delete Student"
                    class="text-red-500 hover:text-red-700 hover:bg-red-50"
                    @click="confirmDeleteStudent(student)"
                  />
                </div>
              </td>
            </tr>
            <tr v-if="initialLoading && filteredStudentsList.length === 0">
              <td colspan="6" class="py-12 text-center">
                <div class="inline-flex items-center gap-2 text-slate-400 text-xs font-semibold">
                  <span class="h-4 w-4 rounded-full border-2 border-blue-600 border-t-transparent animate-spin"></span>
                  <span>{{ lang === 'kh' ? 'កំពុងផ្ទុកទិន្នន័យ...' : 'Loading data...' }}</span>
                </div>
              </td>
            </tr>
            <tr v-else-if="filteredStudentsList.length === 0">
              <td colspan="6">
                <EmptyState
                  icon="person_search"
                  :title="t.noStudentsFound"
                  :description="t.noStudentsDesc"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Card List -->
      <div class="md:hidden divide-y divide-slate-100">
        <div v-for="student in paginatedStudents" :key="student.id" class="p-4 space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center font-bold text-xs shrink-0 border border-blue-100">
                {{ (student.name || 'S').charAt(0).toUpperCase() }}
              </div>
              <div>
                <h4 class="font-bold text-slate-900 text-sm">{{ student.name }}</h4>
                <div class="mt-1 space-y-0.5">
                  <div>
                    <span class="text-xs font-bold font-mono text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-100 inline-block">
                      {{ student.studentCode || ('SR' + (new Date().getFullYear()) + String(student.id).padStart(5, '0')) }}
                    </span>
                  </div>
                  <div v-if="student.phone" class="text-xs text-slate-400 font-medium">
                    {{ student.phone }}
                  </div>
                </div>
              </div>
            </div>
            <div class="text-right shrink-0">
              <StatusBadge
                :status="getStudentExamStatusType(student)"
                :label="getStudentExamStatusLabel(student)"
              />
              <div
                v-if="getStudentExamNamesDisplay(student)"
                class="text-[11px] font-medium text-slate-500 truncate max-w-[150px] mt-0.5"
                :title="getStudentExamNamesDisplay(student)"
              >
                {{ getStudentExamNamesDisplay(student) }}
              </div>
            </div>
          </div>

          <div class="flex flex-wrap gap-2 text-xs text-slate-600">
            <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700">
              {{ student.gender === 'Female' ? (lang === 'kh' ? 'ស្រី' : 'Female') : (lang === 'kh' ? 'ប្រុស' : 'Male') }}
            </span>
            <span v-if="student.sessionName" class="px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 font-semibold">
              {{ student.sessionName }}
            </span>
            <span v-if="student.examDay" class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 font-medium inline-flex items-center gap-1">
              <span class="material-symbols-outlined text-[12px]">calendar_today</span>
              {{ student.examDay }}
            </span>
            <span v-if="student.academicYear" class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 font-medium inline-flex items-center gap-1">
              <span class="material-symbols-outlined text-[12px]">school</span>
              {{ student.academicYear }}
            </span>
          </div>

          <div v-if="can('Students', 'edit') || can('Students', 'delete')" class="flex items-center justify-end gap-2 pt-2 border-t border-slate-50">
            <Button v-if="can('Students', 'edit')" variant="secondary" size="xs" icon="edit" @click="editStudent(student)">
              {{ t.edit }}
            </Button>
            <Button v-if="can('Students', 'delete')" variant="danger" size="xs" icon="delete" @click="confirmDeleteStudent(student)">
              {{ t.delete }}
            </Button>
          </div>
        </div>

        <div v-if="initialLoading && filteredStudentsList.length === 0" class="py-12 text-center">
          <div class="inline-flex items-center gap-2 text-slate-400 text-xs font-semibold">
            <span class="h-4 w-4 rounded-full border-2 border-blue-600 border-t-transparent animate-spin"></span>
            <span>{{ lang === 'kh' ? 'កំពុងផ្ទុកទិន្នន័យ...' : 'Loading data...' }}</span>
          </div>
        </div>

        <EmptyState
          v-else-if="filteredStudentsList.length === 0"
          icon="person_search"
          :title="t.noStudentsFound"
          :description="t.noStudentsDesc"
        />
      </div>

      <!-- Student Pagination -->
      <Pagination
        v-if="filteredStudentsList.length > pageSize"
        v-model:currentPage="currentStudentPage"
        :pageSize="pageSize"
        :totalItems="filteredStudentsList.length"
      />
    </Card>

    <!-- ── Add Student Modal ─────────────────────────────────────────── -->
    <Modal
      v-model="addingStudent"
      :title="t.addUserTitle"
      max-width="lg"
      :overflow-visible="true"
    >
      <div class="space-y-4">
        <!-- Student ID (Auto-generated & Non-editable on Add) -->
        <div class="grid grid-cols-1 gap-4">
          <Input
            v-model="addForm.studentCode"
            :label="lang === 'kh' ? 'អត្តលេខសិស្ស (Student ID)' : 'Student ID'"
            icon="badge"
            disabled
          />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Input v-model="addForm.firstName" :label="t.firstName" required :placeholder="lang === 'kh' ? 'ឧទាហរណ៍៖ សុខា' : 'First Name'" />
          <Input v-model="addForm.lastName" :label="t.lastName" required :placeholder="lang === 'kh' ? 'ឧទាហរណ៍៖ ចាន់' : 'Last Name'" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Input v-model="addForm.phone" :label="t.phone" icon="call" required placeholder="012 345 678" />
          <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">{{ t.gender }}</label>
            <CustomDropdown v-model="addForm.gender" :options="genderOptions" labelKey="label" valueKey="value" />
          </div>
        </div>

        <!-- Exam Shift / Session Selection -->
        <div class="space-y-1.5">
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
            {{ t.examShift }} <span class="text-red-500">*</span>
          </label>
          <CustomDropdown
            v-model="addForm.sessionId"
            :options="sessionOptions"
            labelKey="label"
            valueKey="value"
            :placeholder="t.selectExamShift"
          />
        </div>

        <!-- Exam Days and Academic Years -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
              {{ t.examDay }}
            </label>
            <CustomDropdown
              v-model="addForm.examDay"
              :options="examDayOptions"
              labelKey="label"
              valueKey="value"
              :placeholder="t.selectExamDay"
            />
          </div>
          <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
              {{ t.academicYear }}
            </label>
            <CustomDropdown
              v-model="addForm.academicYear"
              :options="academicYearOptions"
              labelKey="label"
              valueKey="value"
              :placeholder="t.selectAcademicYear"
            />
          </div>
        </div>
      </div>

      <template #footer>
        <Button variant="outline" @click="addingStudent = false">{{ t.cancel }}</Button>
        <Button variant="primary" :loading="savingAdd" @click="saveNewUser">{{ t.saveUser }}</Button>
      </template>
    </Modal>

    <!-- ── Edit Student Modal ────────────────────────────────────────── -->
    <Modal
      v-model="editingStudentModal"
      :title="t.editUserTitle"
      max-width="lg"
      :overflow-visible="true"
    >
      <div class="space-y-4">
        <!-- Student ID (Read-only / Non-editable on Edit) -->
        <div class="grid grid-cols-1 gap-4">
          <Input
            v-model="editForm.studentCode"
            :label="lang === 'kh' ? 'អត្តលេខសិស្ស (Student ID)' : 'Student ID'"
            icon="badge"
            disabled
          />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Input v-model="editForm.firstName" :label="t.firstName" required />
          <Input v-model="editForm.lastName" :label="t.lastName" required />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Input v-model="editForm.phone" :label="t.phone" icon="call" required />
          <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">{{ t.gender }}</label>
            <CustomDropdown v-model="editForm.gender" :options="genderOptions" labelKey="label" valueKey="value" />
          </div>
        </div>

        <!-- Exam Shift / Session Selection -->
        <div class="space-y-1.5">
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
            {{ t.examShift }}
          </label>
          <CustomDropdown
            v-model="editForm.sessionId"
            :options="sessionOptions"
            labelKey="label"
            valueKey="value"
            :placeholder="t.selectExamShift"
          />
        </div>

        <!-- Exam Days and Academic Years -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
              {{ t.examDay }}
            </label>
            <CustomDropdown
              v-model="editForm.examDay"
              :options="examDayOptions"
              labelKey="label"
              valueKey="value"
              :placeholder="t.selectExamDay"
            />
          </div>
          <div class="space-y-1.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
              {{ t.academicYear }}
            </label>
            <CustomDropdown
              v-model="editForm.academicYear"
              :options="academicYearOptions"
              labelKey="label"
              valueKey="value"
              :placeholder="t.selectAcademicYear"
            />
          </div>
        </div>
      </div>

      <template #footer>
        <Button variant="outline" @click="editingStudentModal = false">{{ t.cancel }}</Button>
        <Button variant="primary" :loading="savingEdit" @click="saveStudent">{{ t.saveChanges }}</Button>
      </template>
    </Modal>

    <!-- ── Unified Candidates Excel Hub Modal (Export & Import) ──────── -->
    <Modal
      v-model="showExcelModal"
      :title="excelAction === 'export' ? (lang === 'kh' ? 'នាំចេញរបាយការណ៍បញ្ជីបេក្ខជន (Excel)' : 'Export Candidates Directory (Excel)') : (lang === 'kh' ? 'នាំចូលបញ្ជីបេក្ខជនជាឯកសារ Excel' : 'Import Candidates from Excel')"
      :subtitle="excelAction === 'export' ? (lang === 'kh' ? 'ជ្រើសរើសតម្រងឆ្នាំសិក្សា វេនប្រឡង ឬស្ថានភាពសម្រាប់ទាញយក' : 'Filter by academic year, shift, or status before exporting') : (lang === 'kh' ? 'ទាញយកគម្រូ បំពេញទិន្នន័យ និងបញ្ចូលឯកសារ Excel (.xlsx)' : 'Download template, fill data, and upload Excel (.xlsx)')"
      max-width="3xl"
      :overflow-visible="excelAction === 'export'"
    >
      <div class="space-y-4">
        <!-- Operation Selector (Export vs Import) -->
        <div class="space-y-1.5" v-if="can('Students', 'export') && can('Students', 'create')">
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
            {{ lang === 'kh' ? 'ជ្រើសរើសប្រតិបត្តិការ' : 'Select Operation' }}
          </label>
          <div class="grid grid-cols-2 gap-3 p-1 bg-slate-100/90 rounded-2xl border border-slate-200/60">
            <button
              type="button"
              @click="excelAction = 'export'"
              :class="[
                'flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl font-bold text-sm transition-all cursor-pointer',
                excelAction === 'export'
                  ? 'bg-white text-blue-700 shadow-sm border border-blue-200 ring-2 ring-blue-500/20'
                  : 'text-slate-600 hover:text-slate-900'
              ]"
            >
              <span class="material-symbols-outlined text-base text-blue-600">download</span>
              <span>{{ lang === 'kh' ? 'នាំចេញ Excel' : 'Export to Excel' }}</span>
            </button>
            <button
              type="button"
              @click="excelAction = 'import'"
              :class="[
                'flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl font-bold text-sm transition-all cursor-pointer',
                excelAction === 'import'
                  ? 'bg-white text-emerald-700 shadow-sm border border-emerald-200 ring-2 ring-emerald-500/20'
                  : 'text-slate-600 hover:text-slate-900'
              ]"
            >
              <span class="material-symbols-outlined text-base text-emerald-600">upload_file</span>
              <span>{{ lang === 'kh' ? 'នាំចូល Excel' : 'Import from Excel' }}</span>
            </button>
          </div>
        </div>

        <!-- ═══ EXPORT SECTION ═══ -->
        <div v-if="excelAction === 'export'" class="space-y-4">
          <!-- Academic Year Selector (Prominent Card) -->
          <div class="space-y-1.5 bg-gradient-to-r from-blue-50/80 to-indigo-50/60 p-4 rounded-2xl border border-blue-100">
            <div class="flex items-center justify-between mb-1">
              <label class="text-xs font-bold uppercase tracking-wider text-blue-950 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base text-blue-600">calendar_month</span>
                <span>{{ lang === 'kh' ? 'ជ្រើសរើសឆ្នាំសិក្សា (Academic Year)' : 'Select Academic Year' }}</span>
              </label>
              <span v-if="exportYear" class="text-[11px] font-mono font-bold text-blue-700 bg-white px-2 py-0.5 rounded-md border border-blue-200 shadow-2xs">
                {{ exportYear }}
              </span>
            </div>
            <CustomDropdown
              v-model="exportYear"
              :options="filterYearOptions"
              labelKey="label"
              valueKey="value"
              :placeholder="t.allYears"
            />
            <p class="text-[11px] text-blue-600/80 mt-1">
              {{ lang === 'kh' ? 'ជ្រើសរើសឆ្នាំសិក្សាជាក់លាក់មួយ ឬជ្រើស "ឆ្នាំទាំងអស់" ដើម្បីទាញយកទិន្នន័យគ្រប់ឆ្នាំ' : 'Choose a specific academic year or select "All Academic Years" to export all years.' }}
            </p>
          </div>

          <!-- Shift & Exam Status Selectors -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                {{ t.examShift }}
              </label>
              <CustomDropdown
                v-model="exportSession"
                :options="filterSessionOptions"
                labelKey="label"
                valueKey="value"
                :placeholder="t.allSessions"
              />
            </div>
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                {{ t.examStatus }}
              </label>
              <CustomDropdown
                v-model="exportExamStatus"
                :options="examStatusOptions"
                labelKey="label"
                valueKey="value"
                :placeholder="t.examStatus"
              />
            </div>
          </div>

          <!-- Quick Filter Actions -->
          <div class="flex items-center justify-between text-xs pt-0.5">
            <button
              type="button"
              class="text-blue-600 hover:text-blue-800 font-semibold inline-flex items-center gap-1 cursor-pointer transition-colors"
              @click="syncExportWithPageFilters"
            >
              <span class="material-symbols-outlined text-sm">sync</span>
              <span>{{ lang === 'kh' ? 'ប្រើតាមតម្រងលើតារាងបច្ចុប្បន្ន' : 'Match current table filters' }}</span>
            </button>
            <button
              type="button"
              class="text-slate-500 hover:text-slate-800 font-medium inline-flex items-center gap-1 cursor-pointer transition-colors"
              @click="resetExportFilters"
            >
              <span class="material-symbols-outlined text-sm">restart_alt</span>
              <span>{{ lang === 'kh' ? 'ជ្រើសទាំងអស់ (ដកតម្រង)' : 'Clear filters (all)' }}</span>
            </button>
          </div>

          <!-- Live Count / Summary Alert -->
          <div class="flex items-center justify-between p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
            <div class="flex items-center gap-2">
              <span class="material-symbols-outlined text-lg text-emerald-600">checklist</span>
              <span class="text-xs font-bold text-slate-700">{{ lang === 'kh' ? 'ចំនួនបេក្ខជនត្រូវនាំចេញ' : 'Candidates ready for export' }}:</span>
            </div>
            <span class="text-sm font-extrabold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-lg border border-emerald-200">
              {{ exportFilteredStudents.length }} {{ lang === 'kh' ? 'នាក់' : 'candidates' }}
            </span>
          </div>
        </div>

        <!-- ═══ IMPORT SECTION ═══ -->
        <div v-else class="space-y-4">
          <!-- Top banner with download template button -->
          <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-start gap-3">
              <span class="material-symbols-outlined text-2xl text-emerald-600 mt-0.5">description</span>
              <div>
                <h4 class="font-bold text-sm text-emerald-950">{{ lang === 'kh' ? 'គម្រូឯកសារ Excel សម្រាប់បញ្ចូល' : 'Candidate Excel Import Template' }}</h4>
                <p class="text-xs text-emerald-700 mt-0.5">{{ lang === 'kh' ? 'ទាញយកគម្រូដែលមានទម្រង់ត្រឹមត្រូវដើម្បីបញ្ចូលទិន្នន័យបេក្ខជន' : 'Download sample template with correct columns' }}</p>
              </div>
            </div>
            <Button
              variant="outline"
              size="sm"
              icon="download"
              type="button"
              @click="downloadSampleTemplate"
              class="bg-white border-emerald-300 text-emerald-800 hover:bg-emerald-100 shrink-0 font-bold"
            >
              {{ lang === 'kh' ? 'ទាញយកគម្រូ Excel' : 'Download Template' }}
            </Button>
          </div>

          <!-- File Upload Area -->
          <div
            class="border-2 border-dashed border-slate-300 rounded-2xl p-6 text-center hover:border-emerald-500 transition-colors bg-slate-50/50 cursor-pointer"
            @click="$refs.excelFileInput.click()"
          >
            <input
              ref="excelFileInput"
              type="file"
              accept=".xlsx, .xls, .csv"
              class="hidden"
              @change="handleExcelFileUpload"
            />
            <div class="flex flex-col items-center justify-center gap-2">
              <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                <span class="material-symbols-outlined text-2xl">cloud_upload</span>
              </div>
              <div>
                <p class="text-sm font-bold text-slate-800">
                  {{ importedFileName || (lang === 'kh' ? 'ចុចទីនេះដើម្បីជ្រើសរើសឯកសារ Excel (.xlsx, .xls)' : 'Click to select Excel file (.xlsx, .xls)') }}
                </p>
                <p class="text-xs text-slate-500 mt-1">
                  {{ lang === 'kh' ? 'គាំទ្រទ្រង់ទ្រាយ .xlsx, .xls, .csv' : 'Supports .xlsx, .xls, .csv formats' }}
                </p>
              </div>
            </div>
          </div>

          <!-- Preview parsed data -->
          <div v-if="parsedStudents.length > 0" class="space-y-2">
            <div class="flex items-center justify-between text-xs font-bold text-slate-700">
              <span class="inline-flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base text-emerald-600">table_rows</span>
                <span>{{ lang === 'kh' ? 'ទិន្នន័យបានពិនិត្យ' : 'Preview Data' }} ({{ parsedStudents.length }} {{ lang === 'kh' ? 'នាក់' : 'candidates' }})</span>
              </span>
              <button
                type="button"
                @click="clearImport"
                class="text-red-600 hover:text-red-700 hover:underline font-semibold inline-flex items-center gap-1 cursor-pointer transition-colors"
              >
                <span class="material-symbols-outlined text-sm">delete_sweep</span>
                <span>{{ lang === 'kh' ? 'សម្អាត' : 'Clear' }}</span>
              </button>
            </div>

            <!-- Scrollable Table Container (Vertical scroll up to 280px + Horizontal scroll) -->
            <div class="relative border border-slate-200/90 rounded-2xl overflow-hidden bg-white shadow-2xs">
              <div class="max-h-64 sm:max-h-72 overflow-y-auto overflow-x-auto divide-y divide-slate-100">
                <table class="min-w-[680px] w-full text-xs text-left">
                  <thead class="bg-slate-100/95 backdrop-blur-xs sticky top-0 z-10 font-bold text-slate-700 border-b border-slate-200 shadow-2xs">
                    <tr>
                      <th class="px-3.5 py-2.5 text-center w-12">#</th>
                      <th class="px-3.5 py-2.5">Student ID</th>
                      <th class="px-3.5 py-2.5">{{ lang === 'kh' ? 'នាមខ្លួន' : 'First Name' }}</th>
                      <th class="px-3.5 py-2.5">{{ lang === 'kh' ? 'គោត្តនាម' : 'Last Name' }}</th>
                      <th class="px-3.5 py-2.5 text-center">{{ lang === 'kh' ? 'ភេទ' : 'Gender' }}</th>
                      <th class="px-3.5 py-2.5">{{ lang === 'kh' ? 'ទូរស័ព្ទ' : 'Phone' }}</th>
                      <th class="px-3.5 py-2.5">{{ lang === 'kh' ? 'វេនប្រឡង' : 'Exam Shift' }}</th>
                      <th class="px-3.5 py-2.5">{{ lang === 'kh' ? 'ថ្ងៃប្រឡង' : 'Exam Day' }}</th>
                      <th class="px-3.5 py-2.5">{{ lang === 'kh' ? 'ឆ្នាំសិក្សា' : 'Academic Year' }}</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 bg-white">
                    <tr
                      v-for="(s, idx) in parsedStudents"
                      :key="idx"
                      class="hover:bg-slate-50/80 transition-colors"
                    >
                      <td class="px-3.5 py-2 text-slate-400 font-mono text-center">{{ idx + 1 }}</td>
                      <td class="px-3.5 py-2 font-mono font-bold text-blue-600">{{ s.studentCode || '-' }}</td>
                      <td class="px-3.5 py-2 font-medium text-slate-900">{{ s.firstName }}</td>
                      <td class="px-3.5 py-2 font-medium text-slate-900">{{ s.lastName }}</td>
                      <td class="px-3.5 py-2 text-center">
                        <span
                          v-if="s.gender"
                          :class="[
                            'px-2 py-0.5 rounded-md text-[10px] font-bold',
                            s.gender === 'Female' || s.gender === 'ស្រី'
                              ? 'bg-rose-50 text-rose-700 border border-rose-200/60'
                              : 'bg-blue-50 text-blue-700 border border-blue-200/60'
                          ]"
                        >
                          {{ s.gender }}
                        </span>
                        <span v-else class="text-slate-400">-</span>
                      </td>
                      <td class="px-3.5 py-2 font-mono text-slate-600">{{ s.phone || '-' }}</td>
                      <td class="px-3.5 py-2 text-slate-600">{{ s.sessionName || '-' }}</td>
                      <td class="px-3.5 py-2 text-slate-600">{{ s.examDay || '-' }}</td>
                      <td class="px-3.5 py-2 text-slate-600 font-mono">{{ s.academicYear || '-' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Footer count & summary -->
            <div class="flex items-center justify-between text-[11px] text-slate-500 px-1 pt-0.5">
              <span>{{ lang === 'kh' ? 'បញ្ជីទិន្នន័យបេក្ខជនទាំងអស់ដែលបាន Upload' : 'Scroll to view all uploaded candidates' }}</span>
              <span class="font-bold text-slate-700 font-mono">{{ parsedStudents.length }} {{ lang === 'kh' ? 'នាក់សរុប' : 'candidates total' }}</span>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <Button variant="outline" @click="showExcelModal = false">{{ t.cancel }}</Button>
        <Button
          v-if="excelAction === 'export'"
          variant="primary"
          icon="download"
          class="bg-emerald-600 hover:bg-emerald-700 text-white"
          :disabled="exportFilteredStudents.length === 0"
          @click="confirmExportExcel"
        >
          {{ lang === 'kh' ? 'ទាញយក Excel (.xlsx)' : 'Download Excel (.xlsx)' }}
        </Button>
        <Button
          v-else
          variant="primary"
          :loading="isImporting"
          :disabled="parsedStudents.length === 0"
          @click="submitImport"
          icon="upload"
          class="bg-emerald-600 hover:bg-emerald-700 text-white"
        >
          {{ lang === 'kh' ? `នាំចូល ${parsedStudents.length} នាក់` : `Import ${parsedStudents.length} Candidates` }}
        </Button>
      </template>
    </Modal>

    <!-- ── Delete Confirmation Dialog ───────────────────────────────── -->
    <ConfirmDialog
      v-model="showDeleteDialog"
      :title="t.deleteConfirmTitle"
      :message="deleteConfirmMessage"
      :confirm-text="t.delete"
      :cancel-text="t.cancel"
      confirm-variant="danger"
      icon="delete"
      :loading="deleting"
      @confirm="performDelete"
    />

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import IconButton from '../components/ui/IconButton.vue'
import Input from '../components/ui/Input.vue'
import CustomDropdown from '../components/CustomDropdown.vue'
import SearchInput from '../components/ui/SearchInput.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import Modal from '../components/ui/Modal.vue'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import Pagination from '../components/ui/Pagination.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import { useLang } from '../utils/useLang'
import { useToast } from '../composables/useToast'
import { usePermissions } from '../composables/usePermissions'
import { useRealtimePoll, broadcastSync } from '../composables/useRealtimePoll'
import { fastCache } from '../stores/fastCache'
import * as XLSX from 'xlsx'

const { lang } = useLang()
const { can } = usePermissions()
const { success: toastSuccess, error: toastError } = useToast()

const studentsList = ref([])
const adminsList = ref([])
const sessionsList = ref([])
const examsList = ref([])

const examDaysList = ref(fastCache.get('exam_days') || [
  { id: 'day1', name: 'Day 1' },
  { id: 'day2', name: 'Day 2' }
])

const academicYearsList = ref(fastCache.get('academic_years') || [
  { id: '1', year: '2025-2026', name: '2025-2026', isDefault: false },
  { id: '2', year: '2026-2027', name: '2026-2027', isDefault: true },
  { id: '3', year: '2027-2028', name: '2027-2028', isDefault: false }
])

const filterSession = ref('')
const filterYear = ref('')
const filterExamStatus = ref('')
const searchQuery = ref('')

const matchesAcademicYear = (recordYear, filterVal) => {
  if (!filterVal) return true
  if (!recordYear) return false
  const r = String(recordYear).trim().toLowerCase()
  const f = String(filterVal).trim().toLowerCase()
  return r === f || r.includes(f) || f.includes(r)
}

const filterSessionOptions = computed(() => [
  { label: t.value.allSessions, value: '' },
  ...sessionsList.value.map(s => ({
    label: s.SessionName,
    value: s.SessionName
  }))
])

const filterYearOptions = computed(() => {
  const yearsSet = new Set()
  academicYearsList.value.forEach(y => {
    const val = (y.year || y.name || '').trim()
    if (val) yearsSet.add(val)
  })
  studentsList.value.forEach(s => {
    const val = (s.academicYear || s.years || '').trim()
    if (val) yearsSet.add(val)
  })
  const sorted = Array.from(yearsSet).sort().reverse()
  return [
    { label: t.value.allYears, value: '' },
    ...sorted.map(y => ({ label: y, value: y }))
  ]
})

// Unified Candidates Excel Hub (Export & Import) States & Logic
const showExcelModal = ref(false)
const showExportModal = showExcelModal
const showImportModal = showExcelModal
const excelAction = ref('export') // 'export' | 'import'

const openExcelHubModal = (action = 'export') => {
  if (action === 'import' && can('Students', 'create')) {
    excelAction.value = 'import'
  } else if (can('Students', 'export')) {
    excelAction.value = 'export'
    syncExportWithPageFilters()
  } else {
    excelAction.value = 'import'
  }
  showExcelModal.value = true
}

const openExportModal = () => openExcelHubModal('export')
const openImportModal = () => {
  clearImport()
  openExcelHubModal('import')
}

const exportYear = ref('')
const exportSession = ref('')
const exportExamStatus = ref('')

const syncExportWithPageFilters = () => {
  exportYear.value = filterYear.value
  exportSession.value = filterSession.value
  exportExamStatus.value = filterExamStatus.value
}

const resetExportFilters = () => {
  exportYear.value = ''
  exportSession.value = ''
  exportExamStatus.value = ''
}

const exportFilteredStudents = computed(() => {
  return studentsList.value.filter(s => {
    const matchesSession = !exportSession.value || s.sessionName === exportSession.value
    const matchesYear = matchesAcademicYear(s.academicYear || s.years, exportYear.value)
    let matchesStatus = true
    if (exportExamStatus.value === 'taken') matchesStatus = s.hasTakenExam
    if (exportExamStatus.value === 'not_taken') matchesStatus = !s.hasTakenExam
    return matchesSession && matchesYear && matchesStatus
  })
})

const currentStudentPage = ref(1)
const pageSize = 15

const initialLoading = ref(!studentsList.value.length)
const addingStudent = ref(false)
const savingAdd = ref(false)
const editingStudentModal = ref(false)
const editingStudentId = ref(null)
const savingEdit = ref(false)
const showDeleteDialog = ref(false)
const userToDelete = ref(null)
const deleting = ref(false)

// Excel Import State
const importedFileName = ref('')
const parsedStudents = ref([])
const isImporting = ref(false)
const excelFileInput = ref(null)

const clearImport = () => {
  importedFileName.value = ''
  parsedStudents.value = []
  if (excelFileInput.value) {
    excelFileInput.value.value = ''
  }
}

const downloadSampleTemplate = () => {
  const currentYear = new Date().getFullYear()
  const sampleHeaders = ['Student ID', 'First Name', 'Last Name', 'Gender', 'Phone', 'Exam Shift', 'Exam Day', 'Academic Year']
  const sampleRows = [
    sampleHeaders,
    [`SR${currentYear}39642`, 'សុខា', 'ចាន់', 'ប្រុស', '012345678', sessionsList.value[0]?.SessionName || 'វេនទី១: ព្រឹក', examDaysList.value[0]?.name || 'Day 1', academicYearsList.value.find(y => y.isDefault)?.year || academicYearsList.value.find(y => y.isDefault)?.name || '2026-2027'],
    [`SR${currentYear}95646`, 'ដារ៉ា', 'សុខ', 'ស្រី', '098765432', sessionsList.value[0]?.SessionName || 'វេនទី១: ព្រឹក', examDaysList.value[0]?.name || 'Day 1', academicYearsList.value.find(y => y.isDefault)?.year || academicYearsList.value.find(y => y.isDefault)?.name || '2026-2027'],
    [`SR${currentYear}88736`, 'វណ្ណា', 'សេង', 'ប្រុស', '088123456', '', '', '']
  ]
  const ws = XLSX.utils.aoa_to_sheet(sampleRows)
  ws['!cols'] = [
    { wch: 18 },
    { wch: 18 },
    { wch: 18 },
    { wch: 12 },
    { wch: 16 },
    { wch: 25 },
    { wch: 20 },
    { wch: 18 }
  ]
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Candidates Template')
  XLSX.writeFile(wb, 'Candidate_Import_Template.xlsx')
  toastSuccess(lang.value === 'kh' ? 'បានទាញយកគម្រូ Excel ដោយជោគជ័យ' : 'Template downloaded successfully')
}

const confirmExportExcel = () => {
  if (!can('Students', 'export')) {
    toastError(lang.value === 'kh' ? 'អ្នកមិនមានសិទ្ធិនាំចេញទិន្នន័យបេក្ខជនទេ' : 'You do not have permission to export candidates.')
    return
  }
  const dataToExport = exportFilteredStudents.value
  if (!dataToExport.length) {
    toastError(lang.value === 'kh' ? 'គ្មានទិន្នន័យបេក្ខជនស្របតាមការ Filter សម្រាប់នាំចេញទេ' : 'No candidates matching the filter to export.')
    return
  }

  const headers = [
    lang.value === 'kh' ? 'ល.រ' : 'No',
    lang.value === 'kh' ? 'អត្តលេខសិស្ស' : 'Student ID',
    lang.value === 'kh' ? 'គោត្តនាម' : 'Last Name',
    lang.value === 'kh' ? 'នាមខ្លួន' : 'First Name',
    lang.value === 'kh' ? 'ភេទ' : 'Gender',
    lang.value === 'kh' ? 'លេខទូរស័ព្ទ' : 'Phone',
    lang.value === 'kh' ? 'វេនប្រឡង' : 'Exam Shift',
    lang.value === 'kh' ? 'ថ្ងៃប្រឡង' : 'Exam Day',
    lang.value === 'kh' ? 'ឆ្នាំសិក្សា' : 'Academic Year',
    lang.value === 'kh' ? 'ស្ថានភាពប្រឡង' : 'Exam Status'
  ]

  const rows = dataToExport.map((s, idx) => [
    idx + 1,
    s.studentCode || ('SR' + (new Date().getFullYear()) + String(s.id).padStart(5, '0')),
    s.lastName || '',
    s.firstName || '',
    s.gender === 'Female' ? (lang.value === 'kh' ? 'ស្រី' : 'Female') : (lang.value === 'kh' ? 'ប្រុស' : 'Male'),
    s.phone || '',
    s.sessionName || (lang.value === 'kh' ? 'មិនទាន់ចាត់វេន' : 'Unassigned'),
    s.examDay || '',
    s.academicYear || s.years || '',
    getStudentExamStatusLabel(s)
  ])

  const ws = XLSX.utils.aoa_to_sheet([headers, ...rows])
  ws['!cols'] = [
    { wch: 6 },
    { wch: 18 },
    { wch: 16 },
    { wch: 16 },
    { wch: 10 },
    { wch: 15 },
    { wch: 22 },
    { wch: 18 },
    { wch: 16 },
    { wch: 18 }
  ]
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Candidates')
  const dateStr = new Date().toISOString().slice(0, 10)
  const yearSuffix = exportYear.value ? `_${exportYear.value.replace(/[^a-zA-Z0-9_-]/g, '_')}` : ''
  XLSX.writeFile(wb, `Candidates_List${yearSuffix}_${dateStr}.xlsx`)
  showExportModal.value = false
  toastSuccess(lang.value === 'kh' ? 'បាននាំចេញបញ្ជីបេក្ខជនជា Excel ដោយជោគជ័យ!' : 'Exported candidates to Excel successfully!')
}

const exportStudentsToExcel = openExportModal

const handleExcelFileUpload = (e) => {
  const file = e.target.files?.[0]
  if (!file) return

  importedFileName.value = file.name
  const reader = new FileReader()

  reader.onload = (evt) => {
    try {
      const data = evt.target.result
      const workbook = XLSX.read(data, { type: 'binary' })
      const firstSheetName = workbook.SheetNames[0]
      const worksheet = workbook.Sheets[firstSheetName]
      const rows = XLSX.utils.sheet_to_json(worksheet, { header: 1 })

      if (!rows || rows.length < 2) {
        toastError(lang.value === 'kh' ? 'ឯកសារ Excel គ្មានទិន្នន័យទេ' : 'Excel file has no data')
        return
      }

      const header = rows[0].map(h => String(h || '').trim().toLowerCase())
      
      const idIdx = header.findIndex(h => h.includes('id') || h.includes('code') || h.includes('អត្តលេខ'))
      const firstIdx = header.findIndex(h => h.includes('first') || h.includes('នាមខ្លួន') || h.includes('ឈ្មោះ') || h === 'fname')
      const lastIdx = header.findIndex(h => h.includes('last') || h.includes('គោត្តនាម') || h === 'lname')
      const genderIdx = header.findIndex(h => h.includes('gender') || h.includes('ភេទ') || h.includes('sex'))
      const phoneIdx = header.findIndex(h => h.includes('phone') || h.includes('ទូរស័ព្ទ') || h.includes('tel'))
      const shiftIdx = header.findIndex(h => h.includes('shift') || h.includes('session') || h.includes('វេន'))
      const dayIdx = header.findIndex(h => h.includes('day') || h.includes('ថ្ងៃ'))
      const yearIdx = header.findIndex(h => h.includes('year') || h.includes('ឆ្នាំ'))

      const candidates = []
      for (let i = 1; i < rows.length; i++) {
        const row = rows[i]
        if (!row || !row.length) continue

        let studentCode = idIdx !== -1 ? String(row[idIdx] || '').trim() : ''
        let firstName = firstIdx !== -1 ? String(row[firstIdx] || '').trim() : ''
        let lastName = lastIdx !== -1 ? String(row[lastIdx] || '').trim() : ''
        let gender = genderIdx !== -1 ? String(row[genderIdx] || '').trim() : 'Male'
        let phone = phoneIdx !== -1 ? String(row[phoneIdx] || '').trim() : ''
        let sessionName = shiftIdx !== -1 ? String(row[shiftIdx] || '').trim() : ''
        let examDay = dayIdx !== -1 ? String(row[dayIdx] || '').trim() : ''
        let academicYear = yearIdx !== -1 ? String(row[yearIdx] || '').trim() : ''

        if (firstIdx === -1 && row[1]) firstName = String(row[1]).trim()
        if (lastIdx === -1 && row[2]) lastName = String(row[2]).trim()
        if (!studentCode && row[0]) studentCode = String(row[0]).trim()

        if (!firstName && !lastName) continue

        candidates.push({
          studentCode,
          firstName,
          lastName,
          gender: gender || 'Male',
          phone,
          sessionName,
          examDay,
          academicYear
        })
      }

      if (candidates.length === 0) {
        toastError(lang.value === 'kh' ? 'រកមិនឃើញទិន្នន័យបេក្ខជនក្នុងឯកសារទេ' : 'No candidate rows found in file')
        return
      }

      parsedStudents.value = candidates
      toastSuccess(lang.value === 'kh' ? `បានពិនិត្យឃើញ ${candidates.length} នាក់` : `Parsed ${candidates.length} candidates`)
    } catch (err) {
      console.error('Excel parse error', err)
      toastError(lang.value === 'kh' ? 'មានបញ្ហាក្នុងការអានឯកសារ Excel' : 'Failed to parse Excel file')
    }
  }

  reader.readAsBinaryString(file)
}

const submitImport = async () => {
  if (parsedStudents.value.length === 0) return
  isImporting.value = true
  try {
    const res = await axios.post('/api/admin/students/import', {
      students: parsedStudents.value
    })
    toastSuccess(lang.value === 'kh' ? `បាននាំចូលបេក្ខជន ${res.data.imported || parsedStudents.value.length} នាក់ដោយជោគជ័យ!` : `Imported ${res.data.imported || parsedStudents.value.length} candidates successfully!`)
    showImportModal.value = false
    clearImport()
    broadcastSync('students_updated')
    await loadData()
  } catch (err) {
    toastError(err.response?.data?.message || (lang.value === 'kh' ? 'មានបញ្ហាក្នុងការនាំចូល' : 'Failed to import candidates'))
  } finally {
    isImporting.value = false
  }
}

const generateRandomCode = (year = null) => {
  const currentYear = year || new Date().getFullYear()
  const randNum = Math.floor(10000 + Math.random() * 90000)
  return `SR${currentYear}${randNum}`
}

const randomizeAddStudentCode = () => {
  addForm.studentCode = generateRandomCode()
}

const addForm = reactive({
  role: 'Student',
  studentCode: '',
  firstName: '',
  lastName: '',
  phone: '',
  gender: 'Male',
  sessionId: '',
  examDay: '',
  academicYear: ''
})

const editForm = reactive({
  role: 'Student',
  studentCode: '',
  firstName: '',
  lastName: '',
  phone: '',
  gender: 'Male',
  sessionId: '',
  examDay: '',
  academicYear: ''
})

const genderOptions = computed(() => [
  { label: lang.value === 'kh' ? 'ប្រុស' : 'Male', value: 'Male' },
  { label: lang.value === 'kh' ? 'ស្រី' : 'Female', value: 'Female' },
  { label: lang.value === 'kh' ? 'ផ្សេងៗ' : 'Other', value: 'Other' }
])

const examDayOptions = computed(() => {
  const list = [
    { label: lang.value === 'kh' ? '-- មិនកំណត់ថ្ងៃប្រឡង --' : '-- No Exam Day --', value: '' },
    ...examDaysList.value.map(d => ({
      label: d.name,
      value: d.name
    }))
  ]
  const currentVal = editingStudentModal.value ? editForm.examDay : addForm.examDay
  if (currentVal && !list.some(opt => opt.value === currentVal)) {
    list.push({ label: currentVal, value: currentVal })
  }
  return list
})

const academicYearOptions = computed(() => {
  const list = [
    { label: lang.value === 'kh' ? '-- មិនកំណត់ឆ្នាំសិក្សា --' : '-- No Academic Year --', value: '' },
    ...academicYearsList.value.map(y => {
      const val = y.year || y.name || ''
      return {
        label: val + (y.isDefault ? (lang.value === 'kh' ? ' (បច្ចុប្បន្ន)' : ' (Current)') : ''),
        value: val
      }
    })
  ]
  const currentVal = editingStudentModal.value ? editForm.academicYear : addForm.academicYear
  if (currentVal && !list.some(opt => opt.value === currentVal)) {
    list.push({ label: currentVal, value: currentVal })
  }
  return list
})

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

const sessionOptions = computed(() => {
  return [
    { label: lang.value === 'kh' ? '-- មិនទាន់កំណត់វេន --' : '-- Unassigned Shift --', value: '', subLabel: null },
    ...sessionsList.value.map(s => {
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
  ]
})

const examStatusOptions = computed(() => [
  { label: lang.value === 'kh' ? 'ស្ថានភាពប្រឡងទាំងអស់' : 'All Exam Statuses', value: '' },
  { label: lang.value === 'kh' ? 'បានប្រឡងរួច' : 'Taken Exam', value: 'taken' },
  { label: lang.value === 'kh' ? 'មិនទាន់ប្រឡង' : 'Not Taken', value: 'not_taken' }
])

const t = computed(() => {
  if (lang.value === 'kh') {
    return {
      title: 'គ្រប់គ្រងបេក្ខជន & សិស្ស',
      desc: 'បញ្ជីឈ្មោះបេក្ខជន វេនប្រឡងអាហារូបករណ៍ និងការតាមដានការប្រឡង',
      addStudent: 'បន្ថែមបេក្ខជន',
      allSessions: 'វេនប្រឡងទាំងអស់',
      allYears: 'ឆ្នាំទាំងអស់',
      allExams: 'វិញ្ញាសាទាំងអស់',
      examStatus: 'ស្ថានភាពប្រឡង',
      reset: 'កំណត់ឡើងវិញ',
      searchPlaceholder: 'ស្វែងរកតាមឈ្មោះ, Student ID, ឬលេខទូរស័ព្ទ...',
      studentsList: 'បញ្ជីបេក្ខជនប្រឡង',
      enrolledCount: 'បេក្ខជនបានចុះឈ្មោះ',
      student: 'បេក្ខជន',
      gender: 'ភេទ',
      examShift: 'វេន & កាលវិភាគប្រឡង',
      actions: 'សកម្មភាព',
      takenExam: 'បានប្រឡង',
      notTakenExam: 'មិនទាន់ប្រឡង',
      edit: 'កែប្រែ',
      delete: 'លុប',
      noStudentsFound: 'រកមិនឃើញទិន្នន័យបេក្ខជនទេ',
      noStudentsDesc: 'សូមព្យាយាមផ្លាស់ប្តូរពាក្យស្វែងរក ឬតម្រង។',
      addUserTitle: 'បន្ថែមបេក្ខជនថ្មី',
      editUserTitle: 'កែប្រែព័ត៌មានបេក្ខជន',
      firstName: 'នាមខ្លួន',
      lastName: 'គោត្តនាម',
      phone: 'លេខទូរស័ព្ទ',
      selectExamShift: 'ជ្រើសរើសវេនប្រឡង',
      examDay: 'កាលវិភាគថ្ងៃប្រឡង (Exam Day)',
      academicYear: 'ឆ្នាំសិក្សា (Academic Year)',
      selectExamDay: '-- ជ្រើសរើសថ្ងៃប្រឡង --',
      selectAcademicYear: '-- ជ្រើសរើសឆ្នាំសិក្សា --',
      cancel: 'បោះបង់',
      saveUser: 'រក្សាទុកបេក្ខជន',
      saveChanges: 'រក្សាទុកការកែប្រែ',
      deleteConfirmTitle: 'លុបបេក្ខជន?',
    }
  }
  return {
    title: 'Candidates & Students Directory',
    desc: 'Manage enrolled scholarship candidates, exam shifts, and examination records',
    addStudent: 'Add Candidate',
    allSessions: 'All Exam Shifts',
    allYears: 'All Academic Years',
    allExams: 'All Exams',
    examStatus: 'Exam Status',
    reset: 'Reset Filters',
    searchPlaceholder: 'Search by name, Student ID, or phone...',
    studentsList: 'Candidates Directory',
    enrolledCount: 'registered candidates',
    student: 'Candidate',
    gender: 'Gender',
    examShift: 'Exam Shift & Schedule',
    actions: 'Actions',
    takenExam: 'Taken',
    notTakenExam: 'Not Taken',
    edit: 'Edit',
    delete: 'Delete',
    noStudentsFound: 'No candidates found',
    noStudentsDesc: 'Try adjusting your search terms or filter selection.',
    addUserTitle: 'Add New Candidate',
    editUserTitle: 'Edit Candidate Profile',
    firstName: 'First Name',
    lastName: 'Last Name',
    phone: 'Phone Number',
    selectExamShift: 'Select Exam Shift',
    examDay: 'Exam Day',
    academicYear: 'Academic Year',
    selectExamDay: '-- Select Exam Day --',
    selectAcademicYear: '-- Select Academic Year --',
    cancel: 'Cancel',
    saveUser: 'Save Candidate',
    saveChanges: 'Save Changes',
    deleteConfirmTitle: 'Delete Candidate?',
  }
})

const filteredStudentsList = computed(() => {
  return studentsList.value.filter(s => {
    const q = searchQuery.value.toLowerCase().trim()
    const matchesSearch = !q ||
      (s.name && s.name.toLowerCase().includes(q)) ||
      (s.username && s.username.toLowerCase().includes(q)) ||
      (s.studentCode && s.studentCode.toLowerCase().includes(q)) ||
      (s.phone && s.phone.toLowerCase().includes(q))

    const matchesSession = !filterSession.value || s.sessionName === filterSession.value

    const matchesYear = matchesAcademicYear(s.academicYear || s.years, filterYear.value)

    let matchesStatus = true
    if (filterExamStatus.value === 'taken') matchesStatus = s.hasTakenExam
    if (filterExamStatus.value === 'not_taken') matchesStatus = !s.hasTakenExam

    return matchesSearch && matchesSession && matchesYear && matchesStatus
  })
})

const paginatedStudents = computed(() => {
  const start = (currentStudentPage.value - 1) * pageSize
  return filteredStudentsList.value.slice(start, start + pageSize)
})

const getStudentExamStatusLabel = (student) => {
  return student.hasTakenExam ? `${t.value.takenExam} (${student.examCount})` : t.value.notTakenExam
}

const getStudentExamStatusType = (student) => {
  return student.hasTakenExam ? 'taken' : 'pending'
}

const getStudentExamNamesDisplay = (student) => {
  if (!student.takenExamNames || !student.takenExamNames.length) return ''
  return student.takenExamNames.join(', ')
}

const deleteConfirmMessage = computed(() => {
  if (!userToDelete.value) return ''
  return lang.value === 'kh'
    ? `តើអ្នកពិតជាចង់លុបទិន្នន័យបេក្ខជន "${userToDelete.value.name}" (${userToDelete.value.studentCode || userToDelete.value.username}) មែនទេ? សកម្មភាពនេះមិនអាចត្រឡប់វិញបានទេ។`
    : `Are you sure you want to delete candidate "${userToDelete.value.name}" (${userToDelete.value.studentCode || userToDelete.value.username})? This action cannot be undone.`
})

const resetFilters = () => {
  filterSession.value = ''
  filterYear.value = ''
  filterExamStatus.value = ''
  searchQuery.value = ''
  currentStudentPage.value = 1
}

const openAddModal = () => {
  addForm.role = 'Student'
  addForm.firstName = ''
  addForm.lastName = ''
  addForm.phone = ''
  addForm.gender = 'Male'
  addForm.studentCode = generateRandomCode()
  if (sessionsList.value.length) {
    addForm.sessionId = sessionsList.value[0].SessionId
  } else {
    addForm.sessionId = ''
  }
  const defYear = academicYearsList.value.find(y => y.isDefault)?.year || academicYearsList.value.find(y => y.isDefault)?.name || academicYearsList.value[0]?.year || academicYearsList.value[0]?.name || ''
  addForm.academicYear = defYear
  addForm.examDay = examDaysList.value[0]?.name || ''
  addingStudent.value = true
}

const saveNewUser = async () => {
  if (!addForm.firstName.trim() || !addForm.lastName.trim() || !addForm.phone.trim()) {
    toastError(lang.value === 'kh' ? 'សូមបំពេញព័ត៌មានចាំបាច់ (នាមខ្លួន គោត្តនាម លេខទូរស័ព្ទ)' : 'Please fill all required fields (First Name, Last Name, Phone).')
    return
  }

  savingAdd.value = true
  try {
    const res = await axios.post('/api/admin/students', {
      role: 'Student',
      studentCode: addForm.studentCode,
      firstName: addForm.firstName.trim(),
      lastName: addForm.lastName.trim(),
      phone: addForm.phone.trim(),
      gender: addForm.gender,
      sessionId: addForm.sessionId || null,
      examDay: addForm.examDay || null,
      academicYear: addForm.academicYear || null
    })

    toastSuccess(res.data.message || 'Candidate added successfully!')
    addingStudent.value = false
    broadcastSync('students_updated')
    await loadData()
  } catch (err) {
    toastError(err.response?.data?.message || 'Failed to add candidate.')
  } finally {
    savingAdd.value = false
  }
}

const editStudent = (student) => {
  editingStudentId.value = student.id
  editForm.role = 'Student'
  editForm.studentCode = student.studentCode || ''
  editForm.firstName = student.firstName || student.first_name || student.name?.split(' ')[0] || ''
  editForm.lastName = student.lastName || student.last_name || student.name?.split(' ').slice(1).join(' ') || ''
  editForm.phone = student.phone || ''
  editForm.gender = student.gender || 'Male'
  editForm.sessionId = student.sessionId || ''
  editForm.examDay = student.examDay || ''
  editForm.academicYear = student.academicYear || ''
  editingStudentModal.value = true
}

const saveStudent = async () => {
  if (!editForm.firstName.trim() || !editForm.lastName.trim() || !editForm.phone.trim()) {
    toastError(lang.value === 'kh' ? 'សូមបំពេញព័ត៌មានចាំបាច់' : 'Please fill all required fields.')
    return
  }

  savingEdit.value = true
  try {
    const payload = {
      role: 'Student',
      firstName: editForm.firstName.trim(),
      lastName: editForm.lastName.trim(),
      studentCode: editForm.studentCode,
      phone: editForm.phone.trim(),
      gender: editForm.gender,
      sessionId: editForm.sessionId || null,
      examDay: editForm.examDay || null,
      academicYear: editForm.academicYear || null
    }

    const res = await axios.put(`/api/admin/students/${editingStudentId.value}`, payload)

    toastSuccess(res.data.message || 'Candidate updated successfully!')
    editingStudentModal.value = false
    broadcastSync('students_updated')
    await loadData()
  } catch (err) {
    toastError(err.response?.data?.message || 'Failed to update candidate.')
  } finally {
    savingEdit.value = false
  }
}

const confirmDeleteStudent = (student) => {
  userToDelete.value = student
  showDeleteDialog.value = true
}

const performDelete = async () => {
  if (!userToDelete.value) return
  deleting.value = true
  try {
    await axios.delete(`/api/admin/students/${userToDelete.value.id}`, {
      data: { role: 'Student' }
    })
    toastSuccess('Candidate deleted successfully.')
    showDeleteDialog.value = false
    broadcastSync('students_updated')
    await loadData()
  } catch (err) {
    toastError(err.response?.data?.message || 'Failed to delete candidate.')
  } finally {
    deleting.value = false
  }
}

const loadData = async (isBackground = false) => {
  try {
    const [studentsRes, sessionsRes] = await Promise.all([
      axios.get('/api/admin/students'),
      axios.get('/api/admin/exam-sessions')
    ])

    studentsList.value = studentsRes.data.students || []
    adminsList.value = studentsRes.data.admins || []
    sessionsList.value = sessionsRes.data.sessions || studentsRes.data.sessions || []
    if (studentsRes.data.exams) {
      examsList.value = studentsRes.data.exams || []
    }
    if (studentsRes.data.examDays && Array.isArray(studentsRes.data.examDays) && studentsRes.data.examDays.length) {
      examDaysList.value = studentsRes.data.examDays
      fastCache.set('exam_days', examDaysList.value)
    }
    if (studentsRes.data.academicYears && Array.isArray(studentsRes.data.academicYears) && studentsRes.data.academicYears.length) {
      academicYearsList.value = studentsRes.data.academicYears.map(y => ({
        ...y,
        year: y.year || y.name || '',
        name: y.name || y.year || ''
      }))
      fastCache.set('academic_years', academicYearsList.value)
    }
  } catch (err) {
    if (!isBackground) {
      console.error('Failed to load candidate management data', err)
    }
  } finally {
    initialLoading.value = false
  }
}

useRealtimePoll(loadData, { interval: 4000, listenEvents: ['students_updated', 'session_updated', 'session_deleted', 'schedule_days_years_updated'] })

onMounted(() => {
  loadData()
})
</script>
