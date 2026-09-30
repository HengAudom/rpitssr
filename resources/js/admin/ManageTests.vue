<template>
  <div :class="[
    'space-y-4',
    isBuilderMode ? 'pb-8 lg:pb-4' : ''
  ]">
    <!-- Header & Mode Switcher -->
    <div v-if="!isBuilderMode" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between shrink-0">
      <div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2.5">
          <span class="material-symbols-outlined text-blue-600 text-3xl">quiz</span>
          {{ t.examLibraryTitle }}
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">
          {{ t.librarySubtitle }}
        </p>
      </div>

      <div class="flex items-center gap-2.5 flex-wrap">
        <Button
          v-if="can('Exams', 'create')"
          variant="primary"
          icon="add_circle"
          size="sm"
          @click="openCreateBuilder"
        >
          {{ t.createExam }}
        </Button>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- VIEW 1: EXAM LIBRARY (TEST BANK)                               -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <div v-if="!isBuilderMode">
      <!-- Loading Skeleton -->
      <div v-if="initialLoading" class="space-y-6">
        <Skeleton height="110px" customClass="rounded-2xl" />
        <Skeleton height="400px" customClass="rounded-2xl" />
      </div>

      <div v-else class="space-y-6">
        <!-- Filter Toolbar -->
      <Card padding="sm" class="shadow-soft-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="w-full sm:w-72">
            <CustomDropdown
              v-model="filterSessionId"
              :options="sessionFilterOptions"
              labelKey="label"
              valueKey="value"
              :placeholder="t.allShifts"
            />
          </div>

          <div class="flex items-center gap-2">
            <IconButton
              v-if="filterSessionId || searchQuery || statusFilter !== 'all'"
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

        <!-- Status Filter Tabs -->
        <div class="pt-3 mt-3 border-t border-slate-100">
          <Tabs
            v-model="statusFilter"
            :tabs="statusTabs"
            :pill="true"
          />
        </div>
      </Card>

      <!-- Exam Table Card -->
      <Card padding="none" class="shadow-soft-sm overflow-hidden">
        <!-- Desktop Table View -->
        <div class="hidden md:block overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50/70">
              <tr>
                <th class="px-4 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.examName }}</th>
                <th class="px-4 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.examShift }}</th>
                <th class="px-4 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.duration }}</th>
                <th class="px-4 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.questionsMarks }}</th>
                <th class="px-4 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.status }}</th>
                <th class="px-4 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500">{{ t.actions }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-for="test in paginatedTests" :key="test.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="px-4 py-3.5 font-bold text-slate-900 leading-snug">
                  <div>{{ test.name }}</div>
                  <div v-if="test.scheduledAt" class="text-xs text-slate-400 font-normal mt-0.5">
                    {{ formatDateTime(test.scheduledAt) }}
                  </div>
                </td>
                <td class="px-4 py-3.5 whitespace-nowrap">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span
                      v-if="test.sessionName"
                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100"
                    >
                      <span class="material-symbols-outlined text-xs">schedule</span>
                      {{ test.sessionName }}
                    </span>
                    <span
                      v-else
                      class="inline-flex items-center px-2 py-0.5 rounded-md text-xs text-slate-400 bg-slate-50 border border-slate-200/80 font-medium"
                    >
                      {{ lang === 'kh' ? 'គ្រប់វេនទាំងអស់' : 'All Shifts (General)' }}
                    </span>
                    <span
                      v-if="test.examDay"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200/60"
                      :title="test.examDay"
                    >
                      <span class="material-symbols-outlined text-xs text-amber-600">event</span>
                      {{ test.examDay }}
                    </span>
                    <span
                      v-if="test.academicYear"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-purple-50 text-purple-800 border border-purple-200/60"
                      :title="test.academicYear"
                    >
                      <span class="material-symbols-outlined text-xs text-purple-600">school</span>
                      {{ test.academicYear }}
                    </span>
                  </div>
                </td>
                <td class="px-4 py-3.5 text-slate-600 font-medium whitespace-nowrap">
                  {{ test.durationMinutes }} {{ lang === 'kh' ? 'នាទី' : 'mins' }}
                </td>
                <td class="px-4 py-3.5 text-slate-600 whitespace-nowrap">
                  <span class="font-bold text-slate-800">{{ (test.questionCount != null ? test.questionCount : test.totalQuestions) ?? 0 }}</span> {{ lang === 'kh' ? 'សំណួរ' : 'qs' }}
                  <span class="text-slate-300 mx-1">·</span>
                  <span class="font-bold text-blue-600">{{ test.totalMarks }}</span> {{ lang === 'kh' ? 'ពិន្ទុ' : 'pts' }}
                </td>
                <td class="px-4 py-3.5">
                  <StatusBadge
                    :status="test.status.toLowerCase()"
                    :label="test.status"
                  />
                </td>
                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1">
                    <!-- Export Excel (.xlsx) -->
                    <IconButton
                      v-if="can('Exams', 'export')"
                      icon="table_view"
                      variant="ghost"
                      size="sm"
                      title="Export Excel (.xlsx)"
                      class="text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50"
                      :loading="exportingExcelId === test.id"
                      @click="exportTestToExcel(test)"
                    />
                    <!-- Export PDF -->
                    <IconButton
                      v-if="can('Exams', 'export')"
                      icon="picture_as_pdf"
                      variant="ghost"
                      size="sm"
                      title="Export PDF"
                      class="text-rose-600 hover:text-rose-800 hover:bg-rose-50"
                      :loading="exportingPdfId === test.id"
                      @click="exportTestToPdf(test)"
                    />

                    <!-- Edit -->
                    <IconButton
                      v-if="can('Exams', 'edit')"
                      icon="edit"
                      variant="ghost"
                      size="sm"
                      :title="t.edit"
                      @click="editTest(test)"
                    />
                    <!-- Delete -->
                    <IconButton
                      v-if="can('Exams', 'delete')"
                      icon="delete"
                      variant="ghost"
                      size="sm"
                      class="text-red-600 hover:bg-red-50"
                      :title="t.delete"
                      @click="confirmDeleteTest(test)"
                    />
                  </div>
                </td>
              </tr>

              <tr v-if="filteredTests.length === 0">
                <td colspan="6">
                  <EmptyState
                    icon="quiz"
                    :title="t.noExamsFound"
                    :description="t.noExamsDesc"
                    :action-label="can('Exams', 'create') ? t.createExam : ''"
                    @action="openCreateBuilder"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Card View -->
        <div class="block md:hidden divide-y divide-slate-100">
          <div
            v-for="test in paginatedTests"
            :key="test.id"
            class="p-4 space-y-3 hover:bg-slate-50/60 transition-colors"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <h4 class="font-bold text-slate-900 text-sm leading-snug break-words">
                  {{ test.name }}
                </h4>
                <div v-if="test.scheduledAt" class="text-[11px] text-slate-400 mt-0.5">
                  {{ formatDateTime(test.scheduledAt) }}
                </div>
              </div>
              <StatusBadge
                :status="test.status.toLowerCase()"
                :label="test.status"
                class="shrink-0"
              />
            </div>

            <div class="flex flex-col gap-1.5 text-xs bg-slate-50 p-2.5 rounded-xl border border-slate-100">
              <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">{{ t.examShift }}</span>
                <span v-if="test.sessionName" class="text-indigo-700 font-semibold flex items-center gap-1">
                  <span class="material-symbols-outlined text-xs">schedule</span>
                  {{ test.sessionName }}
                </span>
                <span v-else class="text-slate-400 font-medium">{{ lang === 'kh' ? 'គ្រប់វេនទាំងអស់' : 'All Shifts' }}</span>
              </div>
              <div v-if="test.examDay || test.academicYear" class="flex items-center gap-1.5 flex-wrap pt-1 border-t border-slate-200/60">
                <span
                  v-if="test.examDay"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-amber-50 text-amber-800 border border-amber-200/60"
                >
                  <span class="material-symbols-outlined text-[12px] text-amber-600">event</span>
                  {{ test.examDay }}
                </span>
                <span
                  v-if="test.academicYear"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-purple-50 text-purple-800 border border-purple-200/60"
                >
                  <span class="material-symbols-outlined text-[12px] text-purple-600">school</span>
                  {{ test.academicYear }}
                </span>
              </div>
            </div>

            <div class="flex items-center justify-between text-xs text-slate-600 px-0.5">
              <div class="flex items-center gap-1">
                <span class="material-symbols-outlined text-sm text-slate-400">timer</span>
                <span>{{ test.durationMinutes }} {{ lang === 'kh' ? 'នាទី' : 'mins' }}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="font-bold text-slate-800">{{ (test.questionCount != null ? test.questionCount : test.totalQuestions) ?? 0 }}</span> {{ lang === 'kh' ? 'សំណួរ' : 'qs' }}
                <span class="text-slate-300">·</span>
                <span class="font-bold text-blue-600">{{ test.totalMarks }}</span> {{ lang === 'kh' ? 'ពិន្ទុ' : 'pts' }}
              </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 pt-2 border-t border-slate-100">
              <Button
                v-if="can('Exams', 'export')"
                variant="outline"
                size="xs"
                icon="table_view"
                class="w-full justify-center text-[11px] text-emerald-700 border-emerald-200"
                :loading="exportingExcelId === test.id"
                @click="exportTestToExcel(test)"
              >
                Excel
              </Button>
              <Button
                v-if="can('Exams', 'export')"
                variant="outline"
                size="xs"
                icon="picture_as_pdf"
                class="w-full justify-center text-[11px] text-rose-700 border-rose-200"
                :loading="exportingPdfId === test.id"
                @click="exportTestToPdf(test)"
              >
                PDF
              </Button>

              <Button
                v-if="can('Exams', 'edit')"
                variant="secondary"
                size="xs"
                icon="edit"
                class="w-full justify-center text-[11px]"
                @click="editTest(test)"
              >
                {{ t.edit }}
              </Button>
              <Button
                v-if="can('Exams', 'delete')"
                variant="danger"
                size="xs"
                icon="delete"
                class="w-full justify-center text-[11px]"
                @click="confirmDeleteTest(test)"
              >
                {{ t.delete }}
              </Button>
            </div>
          </div>

          <div v-if="filteredTests.length === 0" class="p-6">
            <EmptyState
              icon="quiz"
              :title="t.noExamsFound"
              :description="t.noExamsDesc"
              :action-label="can('Exams', 'create') ? t.createExam : ''"
              @action="openCreateBuilder"
            />
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="filteredTests.length > pageSize" class="p-3 border-t border-slate-100">
          <Pagination
            v-model="currentPage"
            :total-items="filteredTests.length"
            :page-size="pageSize"
          />
        </div>
      </Card>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- VIEW 2: 3-ZONE EXAM BUILDER                                     -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <div v-else class="space-y-4">
      <!-- Builder Top Actions Bar -->
      <div class="flex flex-wrap items-center justify-between gap-3 bg-white rounded-2xl border border-slate-200/90 shadow-soft-sm p-4">
        <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
          <Button
            variant="outline"
            size="sm"
            icon="arrow_back"
            class="shrink-0 whitespace-nowrap"
            @click="returnToLibrary"
          >
            <span class="hidden sm:inline">{{ t.backToLibrary }}</span>
          </Button>

          <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

          <div class="min-w-0">
            <h2 class="text-base sm:text-lg font-bold text-slate-900 leading-tight truncate">
              {{ editingTestId ? t.editExamTitle : t.createExamTitle }}
            </h2>
            <p class="text-xs text-slate-500 truncate hidden sm:block mt-0.5">{{ t.builderSubtitle }}</p>
          </div>
        </div>

        <div class="flex items-center gap-2 justify-end shrink-0">
          <Button
            variant="outline"
            size="sm"
            icon="edit_note"
            :loading="saving"
            class="whitespace-nowrap"
            @click="handleSaveTest('Draft')"
          >
            {{ t.saveDraft }}
          </Button>
          <Button
            variant="primary"
            size="sm"
            :icon="editingTestId ? 'save' : 'publish'"
            :loading="saving"
            class="whitespace-nowrap"
            @click="handleSaveTest('Published')"
          >
            {{ editingTestId ? t.updateAndPublish : t.publishExam }}
          </Button>
        </div>
      </div>

      <!-- Mobile Tab Switcher for Builder (3 equal segmented tabs) -->
      <div class="lg:hidden">
        <div class="grid grid-cols-3 gap-1 p-1 bg-slate-100/90 rounded-2xl border border-slate-200/60 text-xs font-semibold select-none">
          <button
            v-for="tab in builderTabs"
            :key="tab.value"
            type="button"
            :class="[
              'py-2 px-1.5 rounded-xl transition-all flex items-center justify-center gap-1 text-center truncate cursor-pointer font-bold',
              activeBuilderTab === tab.value
                ? 'bg-white text-blue-600 shadow-soft-xs'
                : 'text-slate-600 hover:text-slate-900'
            ]"
            @click="activeBuilderTab = tab.value"
          >
            <span v-if="tab.icon" class="material-symbols-outlined text-base shrink-0">{{ tab.icon }}</span>
            <span class="truncate text-[11px] sm:text-xs">{{ tab.label }}</span>
          </button>
        </div>
      </div>

      <!-- 3-Zone Layout Grid (Height fixed to viewport on desktop, natural on mobile) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 h-auto lg:h-[calc(100vh-210px)] lg:min-h-[580px]">

        <!-- Zone 1: Question Navigator (3 cols on desktop) -->
        <Card
          :class="[
            'lg:col-span-3 shadow-soft-sm flex flex-col h-full min-h-0 overflow-hidden',
            activeBuilderTab === 'questions' ? 'flex' : 'hidden lg:flex'
          ]"
          padding="none"
        >
          <!-- Zone 1 Header -->
          <div class="px-3 py-2.5 border-b border-slate-100 flex items-center justify-between gap-1 bg-slate-50/50 shrink-0">
            <span class="text-xs font-bold text-slate-700 whitespace-nowrap shrink-0">
              {{ lang === 'kh' ? `បញ្ជីសំណួរ (${questions.length})` : `Questions (${questions.length})` }}
            </span>
            <button
              type="button"
              class="text-xs font-bold text-blue-600 hover:text-blue-700 flex items-center gap-0.5 cursor-pointer select-none whitespace-nowrap shrink-0 ml-auto"
              @click="addQuestionAndOpen"
            >
              <span class="material-symbols-outlined text-sm shrink-0">add</span>
              <span class="whitespace-nowrap">{{ t.addQuestion }}</span>
            </button>
          </div>

          <!-- Question Items (Scrollable) -->
          <div class="flex-1 min-h-0 overflow-y-auto p-2 space-y-1.5">
            <button
              v-for="(q, qIdx) in questions"
              :key="qIdx"
              type="button"
              :class="[
                'w-full flex items-center justify-between p-2.5 rounded-xl text-left transition-all cursor-pointer select-none border',
                activeQuestionIdx === qIdx
                  ? 'bg-blue-50/90 border-blue-400 text-blue-900 font-bold shadow-soft-xs'
                  : 'bg-white border-slate-200/80 hover:border-slate-300 hover:bg-slate-50 text-slate-700'
              ]"
              @click="selectQuestion(qIdx)"
            >
              <div class="flex items-center gap-2.5 min-w-0 flex-1 pr-1">
                <span
                  :class="[
                    'h-6 w-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0',
                    q.isExample
                      ? 'bg-purple-100 text-purple-700 ring-1 ring-purple-300'
                      : (activeQuestionIdx === qIdx ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-700')
                  ]"
                >
                  {{ getQuestionNum(qIdx) }}
                </span>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-1">
                    <span v-if="q.isExample" class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-purple-100 text-purple-800 text-[9px] font-bold shrink-0">
                      {{ lang === 'kh' ? 'គំរូ ០' : 'Ex 0' }}
                    </span>
                    <span v-else-if="q.passage" class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 text-[9px] font-bold shrink-0">
                      Reading
                    </span>
                    <p class="text-xs truncate leading-tight flex-1" v-html="renderMath(q.text || (lang === 'kh' ? `បញ្ចូលខ្លឹមសារសំណួរ...` : `Enter question text here...`))"></p>
                  </div>
                </div>
              </div>

              <span class="text-[11px] text-slate-400 font-mono shrink-0 ml-1">
                {{ q.points || 1 }}p
              </span>
            </button>

            <div v-if="questions.length === 0" class="p-6 text-center text-xs text-slate-400">
              {{ t.noQuestionsYet }}
            </div>
          </div>

          <!-- Add Question & Bulk Import in Zone 1 Footer -->
          <div class="p-3 border-t border-slate-100 bg-slate-50/50 space-y-2 shrink-0">
            <!-- Bulk Question Importer Button -->
            <Button
              variant="outline"
              full-width
              size="sm"
              icon="playlist_add"
              class="border-slate-200 text-slate-700 hover:bg-slate-100 hover:text-blue-700 justify-center"
              @click="openBulkImportModal"
            >
              {{ lang === 'kh' ? 'នាំចូលសំណួរជាដុំ (Word / Excel)' : 'Import Excel / Word' }}
            </Button>
          </div>
        </Card>

        <!-- Zone 2: Main Question Editor (5 cols on desktop) -->
        <Card
          :class="[
            'lg:col-span-5 shadow-soft-sm flex flex-col h-full min-h-0 overflow-hidden',
            activeBuilderTab === 'editor' ? 'flex' : 'hidden lg:flex'
          ]"
          padding="normal"
        >
          <template v-if="currentEditingQuestion">
            <!-- Zone 2 2-Row Clean Header (Guarantees zero overflow at all screen widths) -->
            <div class="space-y-2 pb-3 mb-3 border-b border-slate-100 shrink-0">
              <!-- Row 1: Question Badge + Example Checkbox + Delete Icon -->
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0 flex-wrap">
                  <span :class="[
                    'px-2.5 py-1 rounded-lg font-bold text-xs flex items-center justify-center shadow-soft-xs whitespace-nowrap shrink-0',
                    currentEditingQuestion.isExample ? 'bg-purple-100 text-purple-700 border border-purple-200' : 'bg-blue-50 text-blue-700 border border-blue-200'
                  ]">
                    {{ t.question }} {{ getQuestionNum(activeQuestionIdx) }}
                  </span>

                  <label class="flex items-center gap-1.5 cursor-pointer text-xs font-semibold text-slate-700 hover:text-purple-700 select-none whitespace-nowrap">
                    <input
                      type="checkbox"
                      v-model="currentEditingQuestion.isExample"
                      class="h-4 w-4 rounded text-purple-600 focus:ring-purple-500 border-slate-300 cursor-pointer"
                    />
                    <span class="text-xs">{{ t.exampleQuestion }}</span>
                  </label>
                </div>

                <IconButton
                  icon="delete"
                  variant="danger"
                  size="sm"
                  :title="t.deleteQuestion"
                  class="shrink-0"
                  @click="removeQuestion(activeQuestionIdx)"
                />
              </div>

              <!-- Row 2: Points Input + Reading Passage Toggle Button -->
              <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-50">
                <div class="flex items-center gap-1.5 text-xs text-slate-600 whitespace-nowrap shrink-0">
                  <span class="font-semibold text-xs">{{ t.points }}:</span>
                  <input
                    v-model.number="currentEditingQuestion.points"
                    type="number"
                    min="1"
                    class="w-12 px-2 py-0.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold text-slate-800 text-center focus:bg-white focus:border-blue-500 outline-none"
                    @input="updateTotalMarks"
                  />
                </div>

                <button
                  type="button"
                  :class="[
                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold border transition-all cursor-pointer select-none whitespace-nowrap shrink-0',
                    currentEditingQuestion.passage
                      ? 'bg-blue-100 text-blue-800 border-blue-300 shadow-soft-xs'
                      : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100 hover:text-slate-900'
                  ]"
                  :title="t.readingPassage"
                  @click="togglePassageForCurrent"
                >
                  <span class="material-symbols-outlined text-sm">menu_book</span>
                  <span>{{ t.readingPassage }}</span>
                </button>
              </div>
            </div>

            <!-- Question Content (Scrollable) -->
            <div class="flex-1 min-h-0 overflow-y-auto space-y-3.5 pr-1">

              <!-- Reading Passage Card (Only shown when question has a passage) -->
              <div v-if="currentEditingQuestion.passage" class="p-3 rounded-xl border border-blue-200 bg-blue-50/60 space-y-2 animate-fade-in">
                <div class="flex items-center justify-between gap-3">
                  <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 min-w-0">
                    <span class="material-symbols-outlined text-base text-blue-600 shrink-0">menu_book</span>
                    <span class="truncate">{{ lang === 'kh' ? 'អត្ថបទអាន (Reading Passage)' : 'Reading Passage' }}</span>
                    <span class="px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 text-[10px] font-extrabold shrink-0">Active</span>
                  </div>
                  <div class="flex items-center gap-3 shrink-0">
                    <button
                      v-if="activeQuestionIdx < questions.length - 1"
                      type="button"
                      class="text-[11px] font-bold text-blue-600 hover:text-blue-700 hover:underline cursor-pointer whitespace-nowrap inline-flex items-center gap-1"
                      @click="applyPassageToNextQuestions"
                    >
                      <span class="material-symbols-outlined text-xs">playlist_add</span>
                      <span>{{ lang === 'kh' ? 'អនុវត្តទៅសំណួរបន្ទាប់' : 'Apply to Following' }}</span>
                    </button>
                    <button
                      type="button"
                      class="text-[11px] font-bold text-rose-600 hover:text-rose-700 hover:underline cursor-pointer inline-flex items-center gap-0.5 whitespace-nowrap"
                      @click="togglePassageForCurrent"
                    >
                      <span class="material-symbols-outlined text-xs">close</span>
                      <span>{{ lang === 'kh' ? 'លុបអត្ថបទអាន' : 'Remove Passage' }}</span>
                    </button>
                  </div>
                </div>

                <div class="space-y-1">
                  <textarea
                    v-model="currentEditingQuestion.passage"
                    rows="3"
                    class="w-full px-3 py-2 text-xs font-medium rounded-xl border border-blue-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all text-slate-800 placeholder:text-slate-400 resize-y leading-relaxed"
                    :placeholder="lang === 'kh' ? 'បញ្ចូលអត្ថបទរឿង ឬខ្លឹមសារសម្រាប់សិស្សអានមុនឆ្លើយសំណួរនេះ...' : 'Enter story passage or text for student to read before answering...'"
                  ></textarea>
                </div>
              </div>

              <!-- Question Text Editor & KaTeX Preview Toggle -->
              <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                  <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                    {{ t.questionText }} <span class="text-red-500">*</span>
                  </label>
                  <button
                    type="button"
                    class="text-[11px] font-semibold text-blue-600 hover:text-blue-700 flex items-center gap-1 cursor-pointer select-none"
                    @click="isEditingQuestionText = !isEditingQuestionText"
                  >
                    <span class="material-symbols-outlined text-sm">{{ isEditingQuestionText ? 'visibility' : 'edit' }}</span>
                    <span>{{ isEditingQuestionText ? (lang === 'kh' ? 'មើលរូបមន្ត' : 'Preview Math') : (lang === 'kh' ? 'កែសម្រួល' : 'Edit') }}</span>
                  </button>
                </div>

                <!-- Direct In-Place Rendered Math Box (when previewing) -->
                <div
                  v-if="!isEditingQuestionText"
                  class="w-full min-h-[72px] px-3.5 py-2.5 text-xs font-medium rounded-xl border border-slate-200 bg-white hover:border-slate-300 transition-all text-slate-900 cursor-pointer leading-relaxed flex items-center"
                  @click="isEditingQuestionText = true"
                >
                  <div v-if="currentEditingQuestion.text" v-html="renderMath(currentEditingQuestion.text)" class="w-full"></div>
                  <span v-else class="text-slate-400 italic">{{ t.enterQuestionContent }}</span>
                </div>

                <!-- Textarea (when editing) -->
                <textarea
                  v-else
                  v-model="currentEditingQuestion.text"
                  rows="3"
                  class="w-full px-3.5 py-2.5 text-xs font-medium rounded-xl border border-blue-400 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all text-slate-900 placeholder:text-slate-400 resize-none leading-relaxed"
                  :placeholder="t.enterQuestionContent"
                  @blur="isEditingQuestionText = false"
                ></textarea>
              </div>

              <!-- Multiple Choice Options -->
              <div class="space-y-2 pt-1">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold uppercase tracking-wider text-slate-600">
                    {{ t.answerOptions }} ({{ currentEditingQuestion.answers.length }}) <span class="text-red-500">*</span>
                  </span>
                  <span class="text-[10px] text-slate-400 italic">
                    {{ t.markCorrectAnswerNotice }}
                  </span>
                </div>

                <div class="space-y-2">
                  <div
                    v-for="(answer, aIdx) in currentEditingQuestion.answers"
                    :key="aIdx"
                    :class="[
                      'flex items-center gap-2.5 p-2 rounded-xl border transition-all',
                      currentEditingQuestion.correctIndex === aIdx
                        ? 'border-emerald-400 bg-emerald-50/70 shadow-soft-xs'
                        : 'border-slate-200 bg-white hover:border-slate-300'
                    ]"
                  >
                    <!-- Selector Button for Correct Answer -->
                    <button
                      type="button"
                      :class="[
                        'h-7 w-7 rounded-lg flex items-center justify-center shrink-0 font-bold text-xs transition-all cursor-pointer select-none',
                        currentEditingQuestion.correctIndex === aIdx
                          ? 'bg-emerald-600 text-white shadow-soft-xs'
                          : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                      ]"
                      :title="lang === 'kh' ? 'កំណត់ជាចម្លើយត្រឹមត្រូវ' : 'Mark as correct answer'"
                      @click="setCorrectAnswer(aIdx)"
                    >
                      <span v-if="currentEditingQuestion.correctIndex === aIdx" class="material-symbols-outlined text-sm">check</span>
                      <span v-else>{{ String.fromCharCode(65 + aIdx) }}</span>
                    </button>

                    <!-- Option Text with Math Rendering and Inline Edit -->
                    <div class="flex-1 min-h-[28px] flex items-center">
                      <input
                        v-if="editingOptionIdx === aIdx"
                        v-model="answer.text"
                        type="text"
                        :placeholder="`${t.option} ${String.fromCharCode(65 + aIdx)}`"
                        class="w-full bg-transparent text-xs font-medium text-slate-800 outline-none placeholder:text-slate-400"
                        @blur="editingOptionIdx = null"
                        @keyup.enter="editingOptionIdx = null"
                        autofocus
                      />
                      <div
                        v-else
                        class="w-full cursor-text py-0.5 text-xs font-medium text-slate-800"
                        @click="editingOptionIdx = aIdx"
                      >
                        <span v-if="answer.text" v-html="renderMath(answer.text)"></span>
                        <span v-else class="text-slate-400">{{ t.option }} {{ String.fromCharCode(65 + aIdx) }}</span>
                      </div>
                    </div>

                    <!-- Trash Icon on Right -->
                    <button
                      v-if="currentEditingQuestion.answers.length > 2"
                      type="button"
                      class="text-slate-400 hover:text-rose-600 p-1 rounded-lg hover:bg-rose-50 transition-colors cursor-pointer"
                      :title="lang === 'kh' ? 'លុបជម្រើសនេះ' : 'Delete choice'"
                      @click="removeAnswerOption(aIdx)"
                    >
                      <span class="material-symbols-outlined text-base">delete</span>
                    </button>
                  </div>
                </div>

                <Button
                  v-if="currentEditingQuestion.answers.length < 6"
                  variant="ghost"
                  size="xs"
                  icon="add"
                  @click="addAnswerOption"
                >
                  {{ t.addChoice }}
                </Button>
              </div>
            </div>

            <!-- Question Editor Navigation Footer -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-1.5 sm:gap-2 shrink-0">
              <div class="flex items-center gap-1 sm:gap-1.5 shrink-0">
                <Button
                  variant="outline"
                  size="xs"
                  icon="arrow_back"
                  :disabled="activeQuestionIdx <= 0"
                  class="px-2 sm:px-3 text-xs"
                  @click="activeQuestionIdx--"
                >
                  <span class="hidden xs:inline sm:inline">{{ t.previous }}</span>
                </Button>
                <Button
                  variant="outline"
                  size="xs"
                  icon="arrow_forward"
                  :disabled="activeQuestionIdx >= questions.length - 1"
                  class="px-2 sm:px-3 text-xs"
                  @click="activeQuestionIdx++"
                >
                  <span class="hidden xs:inline sm:inline">{{ t.next }}</span>
                </Button>
              </div>

              <div class="flex items-center gap-1 sm:gap-1.5 shrink-0 ml-auto">
                <Button
                  variant="ghost"
                  size="xs"
                  icon="format_list_numbered"
                  class="lg:hidden px-2 text-xs"
                  :title="t.questionList"
                  @click="activeBuilderTab = 'questions'"
                >
                  <span class="hidden sm:inline">{{ t.questionList }}</span>
                </Button>
                <Button
                  variant="primary"
                  size="xs"
                  icon="add"
                  class="whitespace-nowrap px-2.5 sm:px-3 text-xs shrink-0"
                  @click="addQuestionAndOpen"
                >
                  {{ t.addQuestion }}
                </Button>
              </div>
            </div>
          </template>

          <EmptyState
            v-else
            icon="quiz"
            :title="t.noQuestionSelected"
            :description="t.clickToAddOrSelect"
            :action-label="t.addQuestion"
            @action="addQuestionAndOpen"
          />
        </Card>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- Zone 3: Exam Settings & Summary (4 cols on desktop)            -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <Card
          :class="[
            'lg:col-span-4 shadow-soft-sm flex flex-col h-full min-h-0 overflow-hidden',
            activeBuilderTab === 'settings' ? 'flex' : 'hidden lg:flex'
          ]"
          padding="normal"
        >
          <template #header>
            <div class="flex items-center gap-2 pb-1">
              <span class="material-symbols-outlined text-blue-600 text-lg">tune</span>
              <h3 class="font-bold text-slate-900 text-sm">{{ t.examSettings }}</h3>
            </div>
          </template>

          <div class="flex-1 min-h-0 overflow-y-auto space-y-3 pr-1">
            <!-- Exam Name -->
            <Input
              v-model="testName"
              :label="t.examName"
              required
              :placeholder="lang === 'kh' ? 'ឧ. វិញ្ញាសាអាហារូបករណ៍ ២០២៦' : 'e.g. Scholarship Exam 2026'"
            />

            <!-- Exam Schedule & Shift (tblsession) -->
            <div class="space-y-1">
              <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                {{ t.examShift }}
              </label>
              <CustomDropdown
                v-model="selectedSessionId"
                :options="sessionBuilderOptions"
                labelKey="label"
                valueKey="value"
                :placeholder="t.allShifts"
              />
            </div>

            <!-- Exam Days & Academic Years -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div class="space-y-1">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                  {{ t.examDays }}
                </label>
                <CustomDropdown
                  v-model="selectedExamDay"
                  :options="examDayBuilderOptions"
                  labelKey="label"
                  valueKey="value"
                  :placeholder="t.allDays"
                />
              </div>

              <div class="space-y-1">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                  {{ t.academicYears }}
                </label>
                <CustomDropdown
                  v-model="selectedAcademicYear"
                  :options="academicYearBuilderOptions"
                  labelKey="label"
                  valueKey="value"
                  :placeholder="t.allYears"
                />
              </div>
            </div>

            <!-- Start Date & End Date -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <Input
                v-model="scheduledAt"
                type="datetime-local"
                :label="t.scheduledDate"
              />
              <Input
                v-model="finishedAt"
                type="datetime-local"
                :label="t.finishedDate"
              />
            </div>

            <!-- Duration & Total Marks -->
            <div class="grid grid-cols-2 gap-2.5">
              <Input
                v-model.number="durationMinutes"
                type="number"
                min="1"
                :label="t.durationMin"
              />
              <div class="space-y-1">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                  {{ t.totalMarks }}
                </label>
                <div class="px-3 py-2 rounded-xl bg-slate-100 border border-slate-200 text-xs font-extrabold text-blue-700">
                  {{ calculatedTotalMarks }} pts
                </div>
              </div>
            </div>

            <!-- Pass Score & Randomize Questions -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <Input
                v-model.number="passScore"
                type="number"
                min="0"
                :label="lang === 'kh' ? 'ពិន្ទុជាប់ (Pass Score)' : 'Pass Score'"
                placeholder="50"
              />
              <div class="space-y-1">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                  {{ lang === 'kh' ? 'សំណួរចៃដន្យ' : 'Randomize Questions' }}
                </label>
                <div class="flex items-center justify-between px-3 py-2 rounded-xl bg-slate-50 border border-slate-200">
                  <span class="text-xs font-medium text-slate-700">{{ randomizeQuestions ? (lang === 'kh' ? 'បើក (On)' : 'Enabled') : (lang === 'kh' ? 'បិទ (Off)' : 'Disabled') }}</span>
                  <label class="relative inline-flex items-center cursor-pointer shrink-0">
                    <input type="checkbox" v-model="randomizeQuestions" class="sr-only peer">
                    <div class="w-8 h-4 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-blue-600"></div>
                  </label>
                </div>
              </div>
            </div>

            <!-- Summary Box -->
            <div class="p-2.5 rounded-2xl bg-blue-50/70 border border-blue-100 space-y-1 text-xs">
              <div class="flex items-center justify-between">
                <span class="text-slate-600">{{ t.totalQuestions }}:</span>
                <strong class="text-slate-900 font-bold text-xs">{{ questions.length }}</strong>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-600">{{ t.totalScore }}:</span>
                <strong class="text-blue-700 font-bold text-xs">{{ calculatedTotalMarks }} pts</strong>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-600">{{ lang === 'kh' ? 'ពិន្ទុជាប់' : 'Pass Score' }}:</span>
                <strong class="text-emerald-700 font-bold text-xs">{{ passScore }} pts</strong>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-600">{{ lang === 'kh' ? 'ចៃដន្យសំណួរ' : 'Randomize Questions' }}:</span>
                <strong class="font-bold text-xs" :class="randomizeQuestions ? 'text-blue-700' : 'text-slate-500'">
                  {{ randomizeQuestions ? (lang === 'kh' ? 'បើក' : 'Yes') : (lang === 'kh' ? 'បិទ' : 'No') }}
                </strong>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-slate-600">{{ t.duration }}:</span>
                <strong class="text-slate-900 font-bold text-xs">{{ durationMinutes }} min</strong>
              </div>
              <div v-if="selectedExamDay" class="flex items-center justify-between pt-1 border-t border-blue-100">
                <span class="text-slate-600">{{ t.examDays }}:</span>
                <strong class="text-amber-800 font-bold text-xs truncate max-w-[140px]">{{ selectedExamDay }}</strong>
              </div>
              <div v-if="selectedAcademicYear" class="flex items-center justify-between">
                <span class="text-slate-600">{{ t.academicYears }}:</span>
                <strong class="text-purple-800 font-bold text-xs">{{ selectedAcademicYear }}</strong>
              </div>
            </div>
          </div>

          <!-- Action Buttons Fixed at bottom of Zone 3 -->
          <div class="pt-2.5 mt-2 border-t border-slate-100 flex flex-col gap-2 shrink-0">
            <Button
              v-if="editingTestId ? can('Exams', 'edit') : can('Exams', 'create')"
              variant="primary"
              full-width
              size="md"
              icon="publish"
              :loading="saving"
              @click="handleSaveTest('Published')"
            >
              {{ editingTestId ? t.updateAndPublish : t.publishExam }}
            </Button>
            <Button
              v-if="editingTestId ? can('Exams', 'edit') : can('Exams', 'create')"
              variant="outline"
              full-width
              size="sm"
              icon="draft"
              :disabled="saving"
              @click="handleSaveTest('Draft')"
            >
              {{ t.saveDraft }}
            </Button>
          </div>
        </Card>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- Bulk Import Questions Modal (Word / Docs / JSON)                -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <Modal
      v-model="showBulkImportModal"
      :title="lang === 'kh' ? 'នាំចូលវិញ្ញាសាជាដុំ (Word / Text / Excel)' : 'Bulk Question Importer (Word / Text / Excel)'"
      max-width="2xl"
    >
      <div class="space-y-3.5">
        <!-- Mode Tabs: Upload Word File vs Import Excel -->
        <div class="flex items-center gap-1.5 p-1 bg-slate-100/90 rounded-xl border border-slate-200/60">
          <button
            type="button"
            class="flex-1 py-2 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
            :class="bulkImportTab === 'upload' ? 'bg-white text-blue-700 shadow-soft-xs' : 'text-slate-600 hover:text-slate-900'"
            @click="switchBulkImportTab('upload')"
          >
            <span class="material-symbols-outlined text-base">upload_file</span>
            <span>{{ lang === 'kh' ? 'ផ្ទុកឯកសារ Word / Docs' : 'Upload Word / Docs File' }}</span>
          </button>
          <button
            type="button"
            class="flex-1 py-2 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
            :class="bulkImportTab === 'excel' ? 'bg-white text-blue-700 shadow-soft-xs' : 'text-slate-600 hover:text-slate-900'"
            @click="switchBulkImportTab('excel')"
          >
            <span class="material-symbols-outlined text-base">table_view</span>
            <span>{{ lang === 'kh' ? 'នាំចូល Excel (.xlsx / .xls)' : 'Import Excel (.xlsx / .xls)' }}</span>
          </button>
        </div>

        <!-- 1. Word / Docs File Upload Dropzone (When tab is upload) -->
        <div v-if="bulkImportTab === 'upload'" class="space-y-1.5">
          <input
            ref="docFileInputRef"
            type="file"
            accept=".docx,.doc,.txt"
            class="hidden"
            @change="onDocFileSelected"
          />

          <div
            class="border-2 border-dashed rounded-2xl p-4 text-center transition-all cursor-pointer select-none"
            :class="[
              isDragging
                ? 'border-blue-500 bg-blue-50/80 scale-[0.99]'
                : uploadingDocFile
                ? 'border-slate-300 bg-slate-50'
                : uploadedDocName
                ? 'border-emerald-300 bg-emerald-50/50 hover:bg-emerald-50'
                : 'border-slate-200 bg-slate-50/60 hover:bg-slate-100/80 hover:border-blue-300'
            ]"
            @click="triggerDocFileInput"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDocFileDrop"
          >
            <!-- Loading State -->
            <div v-if="uploadingDocFile" class="py-2 flex flex-col items-center justify-center gap-2">
              <span class="h-6 w-6 rounded-full border-2 border-blue-600 border-t-transparent animate-spin"></span>
              <p class="text-xs font-bold text-blue-700">{{ lang === 'kh' ? 'កំពុងអានទិន្នន័យពីឯកសារ Word...' : 'Extracting text from Word file...' }}</p>
            </div>

            <!-- Uploaded File Info State -->
            <div v-else-if="uploadedDocName" class="flex items-center justify-between gap-3 px-2 py-1">
              <div class="flex items-center gap-3 min-w-0 text-left">
                <div class="h-10 w-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                  <span class="material-symbols-outlined text-2xl">description</span>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-bold text-emerald-950 truncate">{{ uploadedDocName }}</p>
                  <p class="text-[11px] text-emerald-700 font-semibold mt-0.5">
                    {{ parsedPreviewQuestions.length > 0 ? (lang === 'kh' ? `✓ រកឃើញ ${parsedPreviewQuestions.length} សំណួរដោយជោគជ័យ!` : `✓ Detected ${parsedPreviewQuestions.length} questions!`) : (lang === 'kh' ? 'ឯកសារត្រូវបានអាន' : 'File parsed') }}
                  </p>
                </div>
              </div>

              <div class="flex items-center gap-2 shrink-0" @click.stop>
                <button
                  type="button"
                  class="px-2.5 py-1 text-xs font-bold bg-white border border-emerald-300 text-emerald-800 rounded-lg hover:bg-emerald-100/80 cursor-pointer shadow-soft-xs"
                  @click="triggerDocFileInput"
                >
                  {{ lang === 'kh' ? 'ប្តូរឯកសារ' : 'Change File' }}
                </button>
                <button
                  type="button"
                  class="p-1 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-white cursor-pointer transition-colors"
                  title="Clear file"
                  @click="clearDocFile"
                >
                  <span class="material-symbols-outlined text-base">close</span>
                </button>
              </div>
            </div>

            <!-- Default Empty Upload State -->
            <div v-else class="py-2 flex flex-col items-center justify-center gap-1">
              <div class="h-10 w-10 rounded-2xl bg-blue-100/80 text-blue-700 flex items-center justify-center mb-0.5">
                <span class="material-symbols-outlined text-2xl">upload_file</span>
              </div>
              <p class="text-xs font-bold text-slate-800">
                {{ lang === 'kh' ? 'ចុចទីនេះដើម្បី Upload File Word / Docs ឬ អូសទម្លាក់' : 'Click to Upload Word / Docs file or Drag & Drop' }}
              </p>
              <p class="text-[11px] text-slate-500 font-medium">
                {{ lang === 'kh' ? 'គាំទ្រឯកសារ .docx, .doc, .txt (អានសំណួរ រូបមន្តគណិត និងចម្លើយស្វ័យប្រវត្តិ)' : 'Supports .docx, .doc, .txt files (Auto-extracts questions, formulas & choices)' }}
              </p>
            </div>
          </div>
        </div>

        <!-- 2. Excel File Upload Dropzone (When tab is excel) -->
        <div v-else class="space-y-3">
          <!-- Download Template Banner -->
          <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-start gap-2.5">
              <span class="material-symbols-outlined text-2xl text-emerald-600 mt-0.5">description</span>
              <div>
                <h4 class="font-bold text-xs text-emerald-950">{{ lang === 'kh' ? 'គម្រូសំណួរ Excel (.xlsx)' : 'Questions Excel Template (.xlsx)' }}</h4>
                <p class="text-[11px] text-emerald-700 mt-0.5">{{ lang === 'kh' ? 'ទាញយកគម្រូដែលមានជួរឈរ សំណួរ, ជម្រើស ក-ឃ, ចម្លើយត្រឹមត្រូវ និងពិន្ទុ' : 'Download template with Question, Choices A-D, Answer & Points' }}</p>
              </div>
            </div>
            <Button
              variant="outline"
              size="xs"
              icon="download"
              type="button"
              @click="downloadQuestionSampleExcel"
              class="bg-white border-emerald-300 text-emerald-800 hover:bg-emerald-100 shrink-0 font-bold"
            >
              {{ lang === 'kh' ? 'ទាញយកគម្រូ Excel' : 'Download Template' }}
            </Button>
          </div>

          <input
            ref="excelFileInputRef"
            type="file"
            accept=".xlsx, .xls, .csv"
            class="hidden"
            @change="onExcelFileSelected"
          />

          <div
            class="border-2 border-dashed rounded-2xl p-4 text-center transition-all cursor-pointer select-none"
            :class="[
              isDraggingExcel
                ? 'border-emerald-500 bg-emerald-50/80 scale-[0.99]'
                : uploadingExcelFile
                ? 'border-slate-300 bg-slate-50'
                : uploadedExcelName
                ? 'border-emerald-300 bg-emerald-50/50 hover:bg-emerald-50'
                : 'border-slate-200 bg-slate-50/60 hover:bg-slate-100/80 hover:border-emerald-300'
            ]"
            @click="triggerExcelFileInput"
            @dragover.prevent="isDraggingExcel = true"
            @dragleave.prevent="isDraggingExcel = false"
            @drop.prevent="onExcelFileDrop"
          >
            <!-- Loading State -->
            <div v-if="uploadingExcelFile" class="py-2 flex flex-col items-center justify-center gap-2">
              <span class="h-6 w-6 rounded-full border-2 border-emerald-600 border-t-transparent animate-spin"></span>
              <p class="text-xs font-bold text-emerald-700">{{ lang === 'kh' ? 'កំពុងអានឯកសារ Excel...' : 'Reading Excel file...' }}</p>
            </div>

            <!-- Uploaded File Info State -->
            <div v-else-if="uploadedExcelName" class="flex items-center justify-between gap-3 px-2 py-1">
              <div class="flex items-center gap-3 min-w-0 text-left">
                <div class="h-10 w-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                  <span class="material-symbols-outlined text-2xl">table_view</span>
                </div>
                <div class="min-w-0">
                  <p class="text-xs font-bold text-emerald-950 truncate">{{ uploadedExcelName }}</p>
                  <p class="text-[11px] text-emerald-700 font-semibold mt-0.5">
                    {{ parsedPreviewQuestions.length > 0 ? (lang === 'kh' ? `✓ រកឃើញ ${parsedPreviewQuestions.length} សំណួរដោយជោគជ័យ!` : `✓ Detected ${parsedPreviewQuestions.length} questions!`) : (lang === 'kh' ? 'ឯកសារត្រូវបានអាន' : 'File parsed') }}
                  </p>
                </div>
              </div>

              <div class="flex items-center gap-2 shrink-0" @click.stop>
                <button
                  type="button"
                  class="px-2.5 py-1 text-xs font-bold bg-white border border-emerald-300 text-emerald-800 rounded-lg hover:bg-emerald-100/80 cursor-pointer shadow-soft-xs"
                  @click="triggerExcelFileInput"
                >
                  {{ lang === 'kh' ? 'ប្តូរឯកសារ' : 'Change File' }}
                </button>
                <button
                  type="button"
                  class="p-1 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-white cursor-pointer transition-colors"
                  title="Clear file"
                  @click="clearExcelFile"
                >
                  <span class="material-symbols-outlined text-base">close</span>
                </button>
              </div>
            </div>

            <!-- Default Empty Excel State -->
            <div v-else class="py-2 flex flex-col items-center justify-center gap-1">
              <div class="h-10 w-10 rounded-2xl bg-emerald-100/80 text-emerald-700 flex items-center justify-center mb-0.5">
                <span class="material-symbols-outlined text-2xl">table_view</span>
              </div>
              <p class="text-xs font-bold text-slate-800">
                {{ lang === 'kh' ? 'ចុចទីនេះដើម្បី Upload File Excel (.xlsx, .xls) ឬ អូសទម្លាក់' : 'Click to Upload Excel (.xlsx, .xls) or Drag & Drop' }}
              </p>
              <p class="text-[11px] text-slate-500 font-medium">
                {{ lang === 'kh' ? 'គាំទ្រសំណួរ, ជម្រើស A/B/C/D, ចម្លើយត្រឹមត្រូវ និងពិន្ទុ' : 'Supports Question, Options A/B/C/D, Correct Answer & Points' }}
              </p>
            </div>
          </div>
        </div>

        <!-- Collapsible Format Guide Banner -->
        <div class="rounded-xl border border-blue-100 bg-blue-50/60 overflow-hidden text-xs text-blue-900">
          <button
            type="button"
            class="w-full p-2.5 flex items-center justify-between font-bold hover:bg-blue-100/50 transition-colors cursor-pointer"
            @click="showFormatGuide = !showFormatGuide"
          >
            <div class="flex items-center gap-1.5">
              <span class="material-symbols-outlined text-sm text-blue-600">info</span>
              <span>{{ lang === 'kh' ? 'របៀបសរសេរទម្រង់សំណួរ (គាំទ្រលេខខ្មែរ ១, ២, ដាក់ពិន្ទុ (៤ពិន្ទុ) និង ក, ខ)' : 'Question Format Guide (Supports Khmer numerals, Points (4 pts), & choices)' }}</span>
            </div>
            <span class="material-symbols-outlined text-base text-blue-600 transition-transform" :class="showFormatGuide ? 'rotate-180' : ''">
              expand_more
            </span>
          </button>

          <div v-if="showFormatGuide" class="p-3 pt-0 space-y-2 border-t border-blue-100/80 mt-1">
            <pre class="bg-white/90 p-2.5 rounded-lg font-mono text-[11px] text-slate-700 border border-blue-200/60 overflow-x-auto leading-relaxed">
១. (៤ពិន្ទុ) តើ HTML តំណាងឱ្យអ្វី?
ក. HyperText Markup Language *
ខ. High Tech Machine Language
គ. Hyper Tool Multi Language
ឃ. Home Text Markup Language

២. (4 pts) តើ 1 + 1 + 1 ស្មើប៉ុន្មាន?
ក. ២ ខ. ១ គ. ៣ * ឃ. ៤ </pre>
            <p class="text-[11px] text-blue-700">
              {{ lang === 'kh' ? '💡 គាំទ្រ៖ លេខខ្មែរ (១., ២.), ដាក់ពិន្ទុតាមសំណួរ ((៤ពិន្ទុ) ឬ (4 pts)), អក្សរខ្មែរ (ក., ខ., គ., ឃ.), និងសញ្ញាសម្គាល់ចម្លើយត្រឹមត្រូវ (*, (ត្រឹមត្រូវ), (correct))' : '💡 Supports: Khmer numerals (១., ២.), Points per question ((4 pts) or (៤ពិន្ទុ)), Khmer letters (ក., ខ.), and correct answer marks (*, (ត្រឹមត្រូវ), (correct))' }}
            </p>
          </div>
        </div>

        <!-- Textarea input -->
        <div class="space-y-1.5">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
              {{ lang === 'kh' ? 'ពិនិត្យ ឬបិទភ្ជាប់អត្ថបទសំណួរ៖' : 'Review or Paste Questions Content:' }}
            </label>
            <div class="flex items-center gap-2.5">
              <button
                type="button"
                class="text-xs text-blue-600 font-semibold hover:underline cursor-pointer"
                @click="insertSampleBulkText"
              >
                {{ lang === 'kh' ? 'បញ្ចូលឧទាហរណ៍គំរូ' : 'Insert Sample' }}
              </button>
              <span v-if="bulkInputText" class="text-slate-300">|</span>
              <button
                v-if="bulkInputText"
                type="button"
                class="text-xs text-slate-500 font-semibold hover:text-rose-600 hover:underline cursor-pointer"
                @click="clearBulkInput"
              >
                {{ lang === 'kh' ? 'សម្អាត' : 'Clear' }}
              </button>
            </div>
          </div>
          <textarea
            v-model="bulkInputText"
            :rows="bulkImportTab === 'upload' ? 4 : 7"
            class="w-full p-3 font-mono text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all text-slate-800 placeholder:text-slate-400 resize-none"
            :placeholder="lang === 'kh' ? 'អត្ថបទពី File Word នឹងបង្ហាញនៅទីនេះ ឬអាចចម្លងបិទភ្ជាប់ដោយផ្ទាល់...' : 'Extracted text from Word file will appear here, or you can paste directly...'"
            @input="runBulkParser"
          ></textarea>
        </div>

        <!-- Toggle: Auto-skip Example Questions (0.) -->
        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 text-xs">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-blue-600">help_center</span>
            <div>
              <span class="font-bold text-slate-800">{{ lang === 'kh' ? 'រំលងសំណួរគំរូ (សំណួរ ០ / Example)' : 'Auto-Skip Example Questions (0.)' }}</span>
              <p class="text-[11px] text-slate-500">{{ lang === 'kh' ? 'រំលងសំណួរទី ០ នៃផ្នែកនីមួយៗដែលជាលំហាត់គំរូមានស្រាប់ចម្លើយលើវិញ្ញាសា' : 'Automatically skip question 0 in sections where answers are already filled as an example' }}</p>
            </div>
          </div>
          <label class="relative inline-flex items-center cursor-pointer shrink-0">
            <input type="checkbox" v-model="skipExampleQuestions" @change="runBulkParser" class="sr-only peer">
            <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
          </label>
        </div>

        <!-- Live Parsed Preview Box -->
        <div v-if="parsedPreviewQuestions.length > 0" class="p-3 bg-emerald-50/80 rounded-xl border border-emerald-200 text-xs space-y-2">
          <div class="flex items-center justify-between font-bold text-emerald-900">
            <span class="flex items-center gap-1.5">
              <span class="material-symbols-outlined text-base text-emerald-600">check_circle</span>
              <span>{{ lang === 'kh' ? `បានរកឃើញ ${parsedPreviewQuestions.length} សំណួរត្រឹមត្រូវ!` : `Detected ${parsedPreviewQuestions.length} valid questions!` }}</span>
            </span>
            <div class="flex items-center gap-1.5">
              <span v-if="skippedExamplesCount > 0 && skipExampleQuestions" class="text-[11px] font-bold bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">
                {{ lang === 'kh' ? `បានរំលង ${skippedExamplesCount} សំណួរគំរូ (០)` : `Skipped ${skippedExamplesCount} examples (0)` }}
              </span>
              <span class="text-[11px] font-mono bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full">
                {{ parsedPreviewQuestions.reduce((acc, q) => acc + q.answers.length, 0) }} {{ lang === 'kh' ? 'ជម្រើសសរុប' : 'total options' }}
              </span>
            </div>
          </div>

          <!-- Scrollable Live Preview List of Parsed Questions with Math Rendering -->
          <div class="mt-2 max-h-52 overflow-y-auto space-y-2 pr-1 border-t border-emerald-200/60 pt-2">
            <div
              v-for="(pq, pqIdx) in parsedPreviewQuestions"
              :key="pqIdx"
              class="p-2.5 bg-white rounded-lg border border-emerald-100 shadow-soft-xs text-xs space-y-1.5"
            >
              <!-- Section Reading Passage Banner in Preview -->
              <div
                v-if="pq.passage && (pqIdx === 0 || parsedPreviewQuestions[pqIdx - 1]?.passage !== pq.passage)"
                class="p-2 rounded bg-blue-50 border border-blue-200/80 text-[11px] text-blue-950 mb-1 space-y-0.5"
              >
                <div class="flex items-center gap-1 font-bold text-blue-800">
                  <span class="material-symbols-outlined text-xs">menu_book</span>
                  <span>{{ lang === 'kh' ? 'អត្ថបទអាន (Reading Passage)' : 'Reading Passage' }}</span>
                </div>
                <p class="line-clamp-2 text-slate-700 italic font-normal leading-relaxed">{{ pq.passage }}</p>
              </div>

              <div class="flex items-start justify-between gap-2 font-medium text-slate-800">
                <div class="flex items-center gap-1.5 shrink-0">
                  <span class="font-bold text-emerald-800">{{ pqIdx + 1 }}. ({{ pq.points }}ពិន្ទុ)</span>
                  <span v-if="pq.passage" class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded bg-blue-100 text-blue-800 text-[9px] font-bold">
                    <span class="material-symbols-outlined text-[10px]">menu_book</span> Reading
                  </span>
                </div>
                <span class="flex-1" v-html="renderMath(pq.text)"></span>
              </div>
              <div class="grid grid-cols-2 gap-1.5 pt-1 pl-2">
                <div
                  v-for="(ans, aIdx) in pq.answers"
                  :key="aIdx"
                  :class="[
                    'px-2 py-1 rounded text-[11px] flex items-center gap-1.5',
                    ans.correct ? 'bg-emerald-100/90 text-emerald-950 font-bold border border-emerald-300' : 'bg-slate-50 text-slate-700 border border-slate-100'
                  ]"
                >
                  <span class="text-slate-400 font-mono shrink-0">{{ ['ក', 'ខ', 'គ', 'ឃ', 'ង', 'ច'][aIdx] || String.fromCharCode(65 + aIdx) }}.</span>
                  <span class="truncate" v-html="renderMath(ans.text)"></span>
                  <span v-if="ans.correct" class="material-symbols-outlined text-xs text-emerald-700 ml-auto shrink-0">check_circle</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else-if="bulkInputText.trim()" class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-800 flex items-center gap-2">
          <span class="material-symbols-outlined text-amber-600 text-base">warning</span>
          <span>{{ bulkImportTab === 'excel' ? (lang === 'kh' ? 'មិនទាន់រកឃើញសំណួរ Excel ត្រឹមត្រូវទេ។ សូមទាញយកគំរូខាងលើ។' : 'No valid Excel questions detected yet. Please check the sample format above.') : (lang === 'kh' ? 'មិនទាន់រកឃើញទម្រង់សំណួរត្រឹមត្រូវទេ។ សូមពិនិត្យមើលទម្រង់គំរូខាងលើ។' : 'No questions detected yet. Please check the sample format above.') }}</span>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-between w-full">
          <Button
            variant="outline"
            size="sm"
            @click="showBulkImportModal = false"
          >
            {{ t.cancel }}
          </Button>

          <Button
            variant="primary"
            size="sm"
            icon="playlist_add_check"
            :disabled="parsedPreviewQuestions.length === 0"
            @click="commitBulkQuestions"
          >
            {{ lang === 'kh' ? `នាំចូល ${parsedPreviewQuestions.length} សំណួរ` : `Import ${parsedPreviewQuestions.length} Questions` }}
          </Button>
        </div>
      </template>
    </Modal>

    <!-- ── Delete Confirm Dialog ───────────────────────────────────── -->
    <ConfirmDialog
      v-model="showDeleteDialog"
      :title="t.deleteExamTitle"
      :message="deleteConfirmMessage"
      :confirm-text="t.delete"
      :cancel-text="t.cancel"
      confirm-variant="danger"
      icon="delete"
      :loading="deleting"
      @confirm="performDeleteTest"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import * as XLSX from 'xlsx'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import IconButton from '../components/ui/IconButton.vue'
import Input from '../components/ui/Input.vue'
import Modal from '../components/ui/Modal.vue'
import SearchInput from '../components/ui/SearchInput.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import Tabs from '../components/ui/Tabs.vue'
import Pagination from '../components/ui/Pagination.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import CustomDropdown from '../components/CustomDropdown.vue'
import Skeleton from '../components/ui/Skeleton.vue'
import { useLang } from '../utils/useLang'
import { renderMath } from '../utils/mathRender'
import { useToast } from '../composables/useToast'
import { usePermissions } from '../composables/usePermissions'
import { logActivity } from '../utils/activityLog'
import { fastCache } from '../stores/fastCache'
import { useRealtimeSync, notifyRealtimeChange } from '../composables/useRealtimeSync'

const { lang } = useLang()
const { success: toastSuccess, error: toastError } = useToast()
const { can } = usePermissions()

const pageSize = 8
const currentPage = ref(1)

const isBuilderMode = ref(false)
const activeBuilderTab = ref('questions')
const searchQuery = ref('')

const filterSessionId = ref('')
const statusFilter = ref('all')

const cachedTests = fastCache.get('tests')
const tests = ref(Array.isArray(cachedTests) && cachedTests.length && cachedTests[0]?.name ? cachedTests : [])
const sessions = ref(fastCache.get('exam_sessions') || [])
const examDays = ref(fastCache.get('exam_days') || [])
const academicYears = ref(fastCache.get('academic_years') || [])
const initialLoading = ref(!tests.value.length)

const editingTestId = ref(null)
const testName = ref('')
const selectedSessionId = ref('')
const selectedExamDay = ref('')
const selectedAcademicYear = ref('')
const durationMinutes = ref(45)
const totalMarks = ref(0)
const passScore = ref(50)
const randomizeQuestions = ref(false)
const scheduledAt = ref('')
const finishedAt = ref('')
const questions = ref([])
const activeQuestionIdx = ref(0)
const isEditingQuestionText = ref(false)
const editingOptionIdx = ref(null)

watch(activeQuestionIdx, () => {
  isEditingQuestionText.value = false
  editingOptionIdx.value = null
})

const saving = ref(false)
const exportingWordId = ref(null)
const exportingTxtId = ref(null)
const exportingExcelId = ref(null)
const exportingPdfId = ref(null)

const showDeleteDialog = ref(false)
const testToDelete = ref(null)
const deleting = ref(false)

const t = computed(() => {
  if (lang.value === 'kh') {
    return {
      examLibraryTitle: 'វិញ្ញាសា & ការប្រឡង',
      librarySubtitle: 'គ្រប់គ្រងបញ្ជីការប្រឡង ចេញផ្សាយ និងនាំចេញឯកសារ',
      createExamTitle: 'បង្កើតការប្រឡងថ្មី',
      editExamTitle: 'កែប្រែការប្រឡង',
      builderSubtitle: 'កំណត់សំណួរ ជម្រើសចម្លើយ ចម្លើយត្រឹមត្រូវ និងវេនប្រឡង',
      createExam: 'បង្កើតវិញ្ញាសាថ្មី',
      backToLibrary: 'ត្រឡប់ទៅបញ្ជីវិញ្ញាសា',
      allShifts: 'គ្រប់វេនទាំងអស់',
      reset: 'កំណត់ឡើងវិញ',
      searchPlaceholder: 'ស្វែងរកការប្រឡង...',
      examName: 'ឈ្មោះការប្រឡង',
      examShift: 'កាលវិភាគ & វេនប្រឡង',
      examDays: 'កាលវិភាគថ្ងៃ (Exam Days)',
      academicYears: 'ឆ្នាំសិក្សា (Academic Years)',
      allDays: 'គ្រប់ថ្ងៃទាំងអស់',
      allYears: 'គ្រប់ឆ្នាំសិក្សា',
      duration: 'រយៈពេល',
      durationMin: 'រយៈពេល (នាទី)',
      questionsMarks: 'សំណួរ & ពិន្ទុ',
      status: 'ស្ថានភាព',
      actions: 'សកម្មភាព',
      noExamsFound: 'រកមិនឃើញការប្រឡងទេ',
      noExamsDesc: 'មិនទាន់មានការប្រឡងត្រូវគ្នានឹងការស្វែងរករបស់អ្នកទេ។',
      questionList: 'បញ្ជីសំណួរ',
      addQuestion: 'បន្ថែមសំណួរ',
      exampleQuestion: 'សំណួរគំរូ',
      readingPassage: 'អត្ថបទ',
      deleteQuestion: 'លុបសំណួរ',
      importJSON: 'នាំចូលសំណួរ (JSON)',
      noQuestionsYet: 'មិនទាន់មានសំណួរទេ។ សូមចុច "បន្ថែមសំណួរ"',
      question: 'សំណួរទី',
      of: 'នៃ',
      points: 'ពិន្ទុ',
      questionText: 'ខ្លឹមសារសំណួរ',
      enterQuestionContent: 'វាយបញ្ចូលខ្លឹមសារសំណួរនៅទីនេះ...',
      answerOptions: 'ជម្រើសចម្លើយ',
      markCorrectAnswerNotice: 'ចុចលើតួអក្សរដើម្បីកំណត់ជាចម្លើយត្រឹមត្រូវ',
      option: 'ជម្រើស',
      addChoice: 'បន្ថែមជម្រើស',
      noQuestionSelected: 'មិនមានសំណួរត្រូវបានជ្រើសរើស',
      clickToAddOrSelect: 'ជ្រើសរើសសំណួរពីបញ្ជីខាងឆ្វេង ឬចុចបន្ថែមសំណួរថ្មី។',
      examSettings: 'ការកំណត់ការប្រឡង',
      scheduledDate: 'កាលបរិច្ឆេទចាប់ផ្តើម',
      finishedDate: 'កាលបរិច្ឆេទបញ្ចប់',
      totalMarks: 'ពិន្ទុសរុប',
      totalQuestions: 'ចំនួនសំណួរ',
      totalScore: 'ពិន្ទុសរុបគណនា',
      publishExam: 'ចេញការប្រឡង (Publish)',
      updateAndPublish: 'រក្សាទុកការកែសម្រួល',
      saveDraft: 'រក្សាទុកជាព្រាង (Draft)',
      edit: 'កែប្រែ',
      deleteExamTitle: 'លុបការប្រឡងនេះ?',
      delete: 'លុប',
      cancel: 'បោះបង់',
      previous: 'មុន',
      next: 'បន្ទាប់'
    }
  }
  return {
    examLibraryTitle: 'Exams & Tests',
    librarySubtitle: 'Manage tests, question banks, schedule exams, and export documents',
    createExamTitle: 'Create Exam',
    editExamTitle: 'Edit Exam',
    builderSubtitle: 'Configure questions, choices, answer keys, and shifts',
    createExam: 'Create Exam',
    backToLibrary: 'Back to Exams',
    allShifts: 'All Shifts (General)',
    reset: 'Reset Filters',
    searchPlaceholder: 'Search exams...',
    examName: 'Exam Name',
    examShift: 'Exam Schedule & Shift',
    examDays: 'Exam Days',
    academicYears: 'Academic Years',
    allDays: 'All Exam Days',
    allYears: 'All Academic Years',
    duration: 'Duration',
    durationMin: 'Duration (Mins)',
    questionsMarks: 'Questions & Marks',
    status: 'Status',
    actions: 'Actions',
    noExamsFound: 'No exams found',
    noExamsDesc: 'No exams match your search criteria.',
    questionList: 'Questions List',
    addQuestion: 'Add Question',
    exampleQuestion: 'Example Question',
    readingPassage: 'Reading',
    deleteQuestion: 'Delete Question',
    importJSON: 'Import JSON',
    noQuestionsYet: 'No questions added yet. Click "Add Question".',
    question: 'Question',
    of: 'of',
    points: 'Points',
    questionText: 'Question Content',
    enterQuestionContent: 'Enter question text here...',
    answerOptions: 'Answer Choices',
    markCorrectAnswerNotice: 'Click letter badge to mark as correct answer',
    option: 'Option',
    addChoice: 'Add Choice',
    noQuestionSelected: 'No question selected',
    clickToAddOrSelect: 'Select a question on the left or click Add Question.',
    examSettings: 'Exam Settings',
    scheduledDate: 'Start Date & Time',
    finishedDate: 'End Date & Time',
    totalMarks: 'Total Marks',
    totalQuestions: 'Total Questions',
    totalScore: 'Calculated Score',
    publishExam: 'Publish Exam',
    updateAndPublish: 'Save Changes',
    saveDraft: 'Save as Draft',
    edit: 'Edit',
    deleteExamTitle: 'Delete Exam?',
    delete: 'Delete',
    cancel: 'Cancel',
    previous: 'Previous',
    next: 'Next'
  }
})

const statusTabs = computed(() => [
  { label: lang.value === 'kh' ? 'ទាំងអស់' : 'All', value: 'all' },
  { label: lang.value === 'kh' ? 'បានចេញផ្សាយ' : 'Published', value: 'published' },
  { label: lang.value === 'kh' ? 'ព្រាង' : 'Draft', value: 'draft' },
  { label: lang.value === 'kh' ? 'បានបិទ' : 'Closed', value: 'closed' }
])

const builderTabs = computed(() => [
  { label: lang.value === 'kh' ? `សំណួរ (${questions.value.length})` : `Questions (${questions.value.length})`, value: 'questions', icon: 'format_list_numbered' },
  { label: lang.value === 'kh' ? 'ខ្លឹមសារ' : 'Content', value: 'editor', icon: 'edit_note' },
  { label: lang.value === 'kh' ? 'ការកំណត់' : 'Settings', value: 'settings', icon: 'tune' }
])

const sessionFilterOptions = computed(() => [
  { label: t.value.allShifts, value: '' },
  ...sessions.value.map(s => ({
    label: s.SessionName || s.name || `Session #${s.SessionId || s.id}`,
    value: String(s.SessionId || s.id)
  }))
])

const sessionBuilderOptions = computed(() => [
  { label: lang.value === 'kh' ? 'គ្រប់វេនទាំងអស់' : 'All Shifts', value: '' },
  ...sessions.value.map(s => ({
    label: s.SessionName || s.name || `Session #${s.SessionId || s.id}`,
    value: s.SessionId || s.id
  }))
])

const examDayBuilderOptions = computed(() => {
  const opts = [
    { label: t.value.allDays, value: '' },
    ...examDays.value.map(d => ({
      label: d.name || `Day #${d.id}`,
      value: d.name || String(d.id)
    }))
  ]
  if (selectedExamDay.value && !opts.some(o => o.value === selectedExamDay.value)) {
    opts.push({ label: selectedExamDay.value, value: selectedExamDay.value })
  }
  return opts
})

const academicYearBuilderOptions = computed(() => {
  const opts = [
    { label: t.value.allYears, value: '' },
    ...academicYears.value.map(y => ({
      label: y.year || y.name || `Year #${y.id}`,
      value: y.year || y.name || String(y.id)
    }))
  ]
  if (selectedAcademicYear.value && !opts.some(o => o.value === selectedAcademicYear.value)) {
    opts.push({ label: selectedAcademicYear.value, value: selectedAcademicYear.value })
  }
  return opts
})

const getQuestionNum = (idx) => {
  const khmerDigits = ['០', '១', '២', '៣', '៤', '៥', '៦', '៧', '៨', '៩']
  const num = idx + 1
  if (lang.value === 'kh') {
    return String(num).split('').map(d => khmerDigits[parseInt(d)] || d).join('')
  }
  return num
}

const togglePassageForCurrent = () => {
  if (!currentEditingQuestion.value) return
  if (currentEditingQuestion.value.passage) {
    currentEditingQuestion.value.passage = ''
  } else {
    currentEditingQuestion.value.passage = ' '
  }
}

const applyPassageToNextQuestions = () => {
  if (!currentEditingQuestion.value?.passage) return
  const currentPassage = currentEditingQuestion.value.passage
  let appliedCount = 0
  for (let i = activeQuestionIdx.value + 1; i < questions.value.length; i++) {
    questions.value[i].passage = currentPassage
    appliedCount++
  }
  toastSuccess(lang.value === 'kh' ? `បានអនុវត្តអត្ថបទអានទៅ ${appliedCount} សំណួរបន្ទាប់` : `Applied passage to next ${appliedCount} questions`)
}

const filteredAndStatusTests = computed(() => {
  let list = tests.value
  if (filterSessionId.value) {
    list = list.filter(t => String(t.sessionId || t.SessionId) === String(filterSessionId.value))
  }
  if (statusFilter.value !== 'all') {
    list = list.filter(t => (t?.status || '').toLowerCase() === statusFilter.value)
  }
  return list
})

const filteredTests = computed(() => {
  let list = filteredAndStatusTests.value
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(t =>
      (t.name && t.name.toLowerCase().includes(q)) ||
      (t.sessionName && t.sessionName.toLowerCase().includes(q))
    )
  }
  return list
})

const paginatedTests = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredTests.value.slice(start, start + pageSize)
})

const currentEditingQuestion = computed(() => {
  return questions.value[activeQuestionIdx.value] || null
})

const calculatedTotalMarks = computed(() => {
  return questions.value.reduce((sum, q) => sum + (parseInt(q.points) || 1), 0)
})

const deleteConfirmMessage = computed(() => {
  if (!testToDelete.value) return ''
  return lang.value === 'kh'
    ? `តើអ្នកពិតជាចង់លុបការប្រឡង "${testToDelete.value.name}" មែនទេ? សំណួរ និងចម្លើយទាំងអស់នឹងត្រូវបានលុបជាអចិន្ត្រៃយ៍។`
    : `Are you sure you want to delete "${testToDelete.value.name}"? All associated questions and choices will be permanently deleted.`
})

const resetFilters = () => {
  filterSessionId.value = ''
  statusFilter.value = 'all'
  searchQuery.value = ''
}

const formatDateTime = (dtStr) => {
  if (!dtStr) return ''
  try {
    const d = new Date(String(dtStr).replace(' ', 'T'))
    if (isNaN(d.getTime())) return String(dtStr)
    return d.toLocaleString(lang.value === 'kh' ? 'km-KH' : 'en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      second: '2-digit',
      hour12: true
    })
  } catch (e) {
    return String(dtStr)
  }
}

const loadData = async (isBackground = false) => {
  try {
    const [testsRes, sesRes, daysYearsRes] = await Promise.all([
      axios.get('/api/admin/tests'),
      axios.get('/api/admin/exam-sessions'),
      axios.get('/api/admin/schedule-days-years').catch(() => ({ data: {} }))
    ])
    tests.value = testsRes.data.tests || []
    sessions.value = sesRes.data.sessions || []
    if (daysYearsRes?.data?.examDays) {
      examDays.value = daysYearsRes.data.examDays
      fastCache.set('exam_days', examDays.value)
    }
    if (daysYearsRes?.data?.academicYears) {
      academicYears.value = daysYearsRes.data.academicYears.map(y => ({
        ...y,
        year: y.year || y.name || '',
        name: y.name || y.year || ''
      }))
      fastCache.set('academic_years', academicYears.value)
    }

    fastCache.set('tests', tests.value)
    fastCache.set('exam_sessions', sessions.value)
  } catch (e) {
    if (!isBackground) {
      console.error('Failed to load tests or sessions:', e)
      if (!tests.value.length) {
        toastError(lang.value === 'kh' ? 'មិនអាចទាញយកទិន្នន័យបានទេ' : 'Failed to load data')
      }
    }
  } finally {
    initialLoading.value = false
  }
}

useRealtimeSync(() => {
  if (!isBuilderMode.value) {
    loadData(true)
  }
}, 30000)

const openCreateBuilder = async () => {
  editingTestId.value = null
  testName.value = ''
  await loadData(true)
  selectedSessionId.value = ''
  selectedExamDay.value = ''
  const defaultYear = academicYears.value.find(y => y.isDefault || y.status === 'Active')
  selectedAcademicYear.value = defaultYear ? defaultYear.year : ''
  durationMinutes.value = 45
  totalMarks.value = 1
  passScore.value = 50
  randomizeQuestions.value = false
  scheduledAt.value = ''
  finishedAt.value = ''
  questions.value = []
  activeQuestionIdx.value = 0
  addQuestionAndOpen()
  isBuilderMode.value = true
}

const returnToLibrary = () => {
  isBuilderMode.value = false
  loadData(true)
}

const editTest = async (test) => {
  try {
    await loadData(true)
    const res = await axios.get(`/api/admin/tests/${test.id}`)
    const tData = res.data.test
    editingTestId.value = tData.id
    testName.value = tData.name
    selectedSessionId.value = tData.sessionId || ''
    selectedExamDay.value = tData.examDay || ''
    selectedAcademicYear.value = tData.academicYear || ''
    durationMinutes.value = tData.durationMinutes
    totalMarks.value = tData.totalMarks
    passScore.value = tData.passScore ?? 50
    randomizeQuestions.value = Boolean(tData.randomizeQuestions)
    scheduledAt.value = tData.scheduledAt ? tData.scheduledAt.replace(' ', 'T').substring(0, 16) : ''
    finishedAt.value = tData.finishedAt ? tData.finishedAt.replace(' ', 'T').substring(0, 16) : ''

    questions.value = (tData.questions || []).map(q => {
      const correctIdx = q.answers.findIndex(a => a.correct)
      return {
        id: q.id,
        text: q.text,
        passage: q.passage || '',
        isExample: !!q.isExample,
        points: q.points || 1,
        correctIndex: correctIdx >= 0 ? correctIdx : 0,
        answers: q.answers.map(a => ({ id: a.id, text: a.text, correct: a.correct }))
      }
    })

    activeQuestionIdx.value = 0
    isBuilderMode.value = true
  } catch (e) {
    toastError(lang.value === 'kh' ? 'មិនអាចទាញយកសំណួរបានទេ' : 'Failed to load questions')
  }
}

const selectQuestion = (idx) => {
  activeQuestionIdx.value = idx
  activeBuilderTab.value = 'editor'
}

const addQuestionAndOpen = () => {
  questions.value.push({
    text: '',
    passage: '',
    isExample: false,
    points: 1,
    correctIndex: 0,
    answers: [
      { text: '', correct: true },
      { text: '', correct: false },
      { text: '', correct: false },
      { text: '', correct: false }
    ]
  })
  activeQuestionIdx.value = questions.value.length - 1
  updateTotalMarks()
}

const removeQuestion = (idx) => {
  questions.value.splice(idx, 1)
  if (activeQuestionIdx.value >= questions.value.length) {
    activeQuestionIdx.value = Math.max(0, questions.value.length - 1)
  }
  updateTotalMarks()
}

const setCorrectAnswer = (aIdx) => {
  if (!currentEditingQuestion.value) return
  currentEditingQuestion.value.correctIndex = aIdx
  currentEditingQuestion.value.answers.forEach((a, i) => {
    a.correct = (i === aIdx)
  })
}

const addAnswerOption = () => {
  if (!currentEditingQuestion.value) return
  currentEditingQuestion.value.answers.push({ text: '', correct: false })
}

const removeAnswerOption = (aIdx) => {
  if (!currentEditingQuestion.value || currentEditingQuestion.value.answers.length <= 2) return
  currentEditingQuestion.value.answers.splice(aIdx, 1)
  if (currentEditingQuestion.value.correctIndex >= currentEditingQuestion.value.answers.length) {
    setCorrectAnswer(0)
  }
}

const updateTotalMarks = () => {
  totalMarks.value = calculatedTotalMarks.value
}

const handleSaveTest = async (status = 'Published') => {
  if (!testName.value.trim()) {
    toastError(lang.value === 'kh' ? 'សូមបញ្ចូលឈ្មោះការប្រឡង' : 'Please enter exam name')
    return
  }
  if (questions.value.length === 0) {
    toastError(lang.value === 'kh' ? 'សូមបន្ថែមសំណួរយ៉ាងហោចណាស់ ១' : 'Please add at least 1 question')
    return
  }

  // Validate questions
  for (let i = 0; i < questions.value.length; i++) {
    const q = questions.value[i]
    if (!q.text.trim()) {
      toastError(lang.value === 'kh' ? `សំណួរទី ${i + 1} មិនទាន់មានខ្លឹមសារនៅឡើយទេ` : `Question ${i + 1} text is empty`)
      activeQuestionIdx.value = i
      activeBuilderTab.value = 'editor'
      return
    }
    const filledAnswers = q.answers.filter(a => a.text.trim())
    if (filledAnswers.length < 2) {
      toastError(lang.value === 'kh' ? `សំណួរទី ${i + 1} ត្រូវមានចម្លើយយ៉ាងហោចណាស់ ២` : `Question ${i + 1} must have at least 2 non-empty choices`)
      activeQuestionIdx.value = i
      activeBuilderTab.value = 'editor'
      return
    }
    if (q.correctIndex === null || q.correctIndex >= q.answers.length) {
      toastError(lang.value === 'kh' ? `សូមជ្រើសរើសចម្លើយត្រឹមត្រូវសម្រាប់សំណួរទី ${i + 1}` : `Please select a correct answer for question ${i + 1}`)
      activeQuestionIdx.value = i
      activeBuilderTab.value = 'editor'
      return
    }
  }

  saving.value = true
  try {
    const payload = {
      name: testName.value,
      sessionId: selectedSessionId.value || null,
      examDay: selectedExamDay.value || null,
      academicYear: selectedAcademicYear.value || null,
      durationMinutes: durationMinutes.value,
      totalMarks: calculatedTotalMarks.value || 1,
      passScore: passScore.value != null ? Number(passScore.value) : 50,
      randomizeQuestions: Boolean(randomizeQuestions.value),
      scheduledAt: scheduledAt.value ? scheduledAt.value.replace('T', ' ') + ':00' : null,
      finishedAt: finishedAt.value ? finishedAt.value.replace('T', ' ') + ':00' : null,
      status: status,
      questions: questions.value.map(q => ({
        id: q.id || null,
        text: q.text,
        passage: q.passage || null,
        isExample: !!q.isExample,
        points: parseInt(q.points) || 1,
        answers: q.answers.map((a, i) => ({
          id: a.id || null,
          text: a.text,
          correct: i === q.correctIndex
        }))
      }))
    }

    if (editingTestId.value) {
      await axios.put(`/api/admin/tests/${editingTestId.value}`, payload)
      toastSuccess(lang.value === 'kh' ? 'បានកែប្រែការប្រឡងជោគជ័យ' : 'Exam updated successfully')
      logActivity('UPDATE_EXAM', `Updated exam: ${testName.value}`)
    } else {
      await axios.post('/api/admin/tests', payload)
      toastSuccess(lang.value === 'kh' ? 'បានបង្កើតការប្រឡងជោគជ័យ' : 'Exam created successfully')
      logActivity('CREATE_EXAM', `Created exam: ${testName.value}`)
    }

    isBuilderMode.value = false
    notifyRealtimeChange('tests_updated')
    await loadData(true)
  } catch (e) {
    toastError(e.response?.data?.message || (lang.value === 'kh' ? 'មានបញ្ហាក្នុងការរក្សាទុក' : 'Failed to save exam'))
  } finally {
    saving.value = false
  }
}

const confirmDeleteTest = (test) => {
  testToDelete.value = test
  showDeleteDialog.value = true
}

const performDeleteTest = async () => {
  if (!testToDelete.value) return
  deleting.value = true
  try {
    await axios.delete(`/api/admin/tests/${testToDelete.value.id}`)
    toastSuccess(lang.value === 'kh' ? 'បានលុបការប្រឡងជោគជ័យ' : 'Exam deleted successfully')
    logActivity('DELETE_EXAM', `Deleted exam: ${testToDelete.value.name}`)
    showDeleteDialog.value = false
    testToDelete.value = null
    notifyRealtimeChange('tests_updated')
    await loadData(true)
  } catch (e) {
    toastError(lang.value === 'kh' ? 'មិនអាចលុបការប្រឡងបានទេ' : 'Failed to delete exam')
  } finally {
    deleting.value = false
  }
}

const exportTestToWord = async (test) => {
  exportingWordId.value = test.id
  try {
    const res = await axios.get(`/api/admin/tests/${test.id}/export-word`, { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `Exam_${test.name.replace(/\s+/g, '_')}.docx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    toastSuccess(lang.value === 'kh' ? 'ទាញយកឯកសារ Word (.docx) ជោគជ័យ' : 'Word document downloaded')
  } catch (e) {
    toastError(lang.value === 'kh' ? 'មិនអាចទាញយក Word បានទេ' : 'Failed to export Word')
  } finally {
    exportingWordId.value = null
  }
}

const exportTestToTxt = async (test) => {
  exportingTxtId.value = test.id
  try {
    const res = await axios.get(`/api/admin/tests/${test.id}/export-txt`, { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `Exam_${test.name.replace(/\s+/g, '_')}.txt`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    toastSuccess(lang.value === 'kh' ? 'ទាញយកឯកសារ Text (.txt) ជោគជ័យ' : 'Text file downloaded')
  } catch (e) {
    toastError(lang.value === 'kh' ? 'មិនអាចទាញយក Text (.txt) បានទេ' : 'Failed to export Text file')
  } finally {
    exportingTxtId.value = null
  }
}

const exportTestToExcel = async (test) => {
  exportingExcelId.value = test.id
  try {
    let tData = test
    if (!tData.questions || tData.questions.length === 0) {
      const res = await axios.get(`/api/admin/tests/${test.id}`)
      tData = res.data.test || test
      test.questions = tData.questions
    }

    const testQuestions = tData.questions || []
    if (testQuestions.length === 0) {
      toastError(lang.value === 'kh' ? 'វិញ្ញាសានេះមិនទាន់មានសំណួរនៅឡើយទេ' : 'This exam has no questions to export yet.')
      return
    }

    const rows = [
      ['កាលវិភាគ & វិញ្ញាសាប្រឡង (Exam Details)'],
      ['ឈ្មោះការប្រឡង (Exam Name):', tData.name || ''],
      ['វេនប្រឡង (Shift):', tData.sessionName || tData.session_name || 'ទូទៅ (General)'],
      ['រយៈពេល (Duration):', `${tData.durationMinutes || 0} នាទី (mins)`],
      ['ពិន្ទុសរុប (Total Marks):', tData.totalMarks || 0],
      ['ពិន្ទុជាប់ (Pass Score):', `${tData.passScore ?? 50}%`],
      ['ចៃដន្យសំណួរ (Randomize Questions):', tData.randomizeQuestions ? 'បាទ/ចាស (Yes)' : 'ទេ (No)'],
      [],
      ['ល.រ (No.)', 'ខ្លឹមសារសំណួរ (Question)', 'អត្ថបទ (Passage)', 'ពិន្ទុ (Points)', 'ជម្រើស ក (A)', 'ជម្រើស ខ (B)', 'ជម្រើស គ (C)', 'ជម្រើស ឃ (D)', 'ចម្លើយត្រឹមត្រូវ (Answer)']
    ]

    testQuestions.forEach((q, idx) => {
      const answers = q.answers || []
      const optA = answers[0]?.text || ''
      const optB = answers[1]?.text || ''
      const optC = answers[2]?.text || ''
      const optD = answers[3]?.text || ''

      let correctAns = ''
      const correctIdx = answers.findIndex(a => a.correct)
      if (correctIdx >= 0) {
        correctAns = ['A (ក)', 'B (ខ)', 'C (គ)', 'D (ឃ)', 'E (ង)'][correctIdx] || `ជម្រើសទី ${correctIdx + 1}`
      }

      rows.push([
        idx + 1,
        q.text || '',
        q.passage || '',
        q.points || 1,
        optA,
        optB,
        optC,
        optD,
        correctAns
      ])
    })

    const worksheet = XLSX.utils.aoa_to_sheet(rows)
    worksheet['!cols'] = [
      { wch: 8 },
      { wch: 45 },
      { wch: 25 },
      { wch: 12 },
      { wch: 25 },
      { wch: 25 },
      { wch: 25 },
      { wch: 25 },
      { wch: 18 }
    ]

    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Exam Questions')
    XLSX.writeFile(workbook, `Exam_${(tData.name || 'exam').replace(/\s+/g, '_')}.xlsx`)
    toastSuccess(lang.value === 'kh' ? 'ទាញយកឯកសារ Excel (.xlsx) ជោគជ័យ' : 'Excel file downloaded successfully')
  } catch (e) {
    console.error('Failed to export test to Excel:', e)
    const msg = e.response?.data?.message || (lang.value === 'kh' ? 'មិនអាចទាញយក Excel (.xlsx) បានទេ' : 'Failed to export Excel file')
    toastError(msg)
  } finally {
    exportingExcelId.value = null
  }
}

const exportTestToPdf = async (test) => {
  exportingPdfId.value = test.id
  // Open window immediately in user gesture to avoid popup blocker
  let printWindow = null
  try {
    printWindow = window.open('', '_blank')
    if (printWindow) {
      printWindow.document.write(`
        <!DOCTYPE html>
        <html>
          <head>
            <title>Loading Exam PDF...</title>
            <style>
              body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; height: 80vh; color: #475569; margin: 0; }
              .box { text-align: center; }
              .spinner { width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top-color: #2563eb; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 12px; }
              @keyframes spin { to { transform: rotate(360deg); } }
            </style>
          </head>
          <body>
            <div class="box">
              <div class="spinner"></div>
              <h3 style="margin: 0 0 6px 0;">កំពុងទាញយកទិន្នន័យវិញ្ញាសា...</h3>
              <p style="margin: 0; font-size: 13px; color: #94a3b8;">Preparing exam printable PDF...</p>
            </div>
          </body>
        </html>
      `)
    }
  } catch (err) {}

  try {
    let tData = test
    if (!tData.questions || tData.questions.length === 0) {
      const res = await axios.get(`/api/admin/tests/${test.id}`)
      tData = res.data.test || test
      test.questions = tData.questions
    }

    const testQuestions = tData.questions || []
    if (!printWindow) {
      printWindow = window.open('', '_blank')
    }
    if (!printWindow) {
      toastError(lang.value === 'kh' ? 'សូមអនុញ្ញាត Popup ក្នុងកម្មវិធីរុករក ដើម្បីបោះពុម្ព PDF' : 'Please allow popups in your browser to export PDF')
      return
    }

    const questionsHtml = testQuestions.map((q, idx) => {
      const answersHtml = (q.answers || []).map((a, aIdx) => {
        const letter = ['ក', 'ខ', 'គ', 'ឃ', 'ង'][aIdx] || String.fromCharCode(65 + aIdx)
        return `
          <div style="display: inline-block; width: 48%; margin-bottom: 6px; font-size: 13px;">
            <strong style="color: ${a.correct ? '#166534' : '#475569'};">${letter}.</strong>
            <span style="${a.correct ? 'font-weight: 700; color: #166534;' : 'color: #334155;'}">${a.text} ${a.correct ? '✓' : ''}</span>
          </div>
        `
      }).join('')

      const passageHtml = q.passage ? `
        <div style="background: #f8fafc; border-left: 3px solid #3b82f6; padding: 8px 12px; margin-bottom: 8px; font-size: 12px; color: #475569; font-style: italic; white-space: pre-wrap;">
          ${q.passage}
        </div>
      ` : ''

      return `
        <div style="margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px dashed #e2e8f0; page-break-inside: avoid;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
            <div style="font-weight: 700; font-size: 14px; color: #0f172a;">
              <span>សំណួរទី ${idx + 1}៖</span> ${q.text}
            </div>
            <span style="font-size: 12px; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 9999px; font-weight: 600; white-space: nowrap; margin-left: 8px;">
              ${q.points || 1} ពិន្ទុ
            </span>
          </div>
          ${passageHtml}
          <div style="margin-top: 6px; padding-left: 12px;">
            ${answersHtml}
          </div>
        </div>
      `
    }).join('')

    printWindow.document.open()
    printWindow.document.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>${tData.name || 'Exam'} - PDF</title>
          <link rel="preconnect" href="https://fonts.googleapis.com">
          <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
          <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
          <style>
            @page {
              size: A4;
              margin: 15mm 15mm 15mm 15mm;
            }
            body {
              font-family: 'Kantumruy Pro', 'Plus Jakarta Sans', sans-serif;
              color: #1e293b;
              margin: 0;
              padding: 10px;
              line-height: 1.5;
            }
            .header {
              text-align: center;
              border-bottom: 2px solid #0284c7;
              padding-bottom: 12px;
              margin-bottom: 16px;
            }
            .header h1 {
              margin: 0 0 4px 0;
              font-size: 20px;
              color: #0f172a;
              font-weight: 700;
            }
            .header p {
              margin: 0;
              font-size: 12px;
              color: #64748b;
            }
            .meta-grid {
              display: grid;
              grid-template-columns: repeat(4, 1fr);
              gap: 8px;
              background: #f8fafc;
              border: 1px solid #e2e8f0;
              border-radius: 8px;
              padding: 10px 14px;
              margin-bottom: 20px;
              font-size: 12px;
            }
            .meta-item strong {
              color: #475569;
              display: block;
              font-size: 11px;
            }
            .meta-item span {
              font-weight: 600;
              color: #0f172a;
            }
            @media print {
              body { padding: 0; }
              button { display: none !important; }
            }
          </style>
        </head>
        <body>
          <div class="header">
            <h1>${tData.name || 'Exam'}</h1>
            <p>វិទ្យាស្ថានជាតិបណ្តុះបណ្តាលបច្ចេកទេស (National Technical Training Institute)</p>
          </div>
          <div class="meta-grid">
            <div class="meta-item">
              <strong>វេនប្រឡង (Shift):</strong>
              <span>${tData.sessionName || tData.session_name || 'ទូទៅ'}</span>
            </div>
            <div class="meta-item">
              <strong>រយៈពេល (Duration):</strong>
              <span>${tData.durationMinutes || 45} នាទី</span>
            </div>
            <div class="meta-item">
              <strong>ពិន្ទុសរុប (Total Marks):</strong>
              <span>${tData.totalMarks || 0} ពិន្ទុ</span>
            </div>
            <div class="meta-item">
              <strong>ពិន្ទុជាប់ (Pass Score):</strong>
              <span>${tData.passScore ?? 50}%</span>
            </div>
          </div>
          <div class="questions-list">
            ${questionsHtml || '<p style="text-align: center; color: #94a3b8; padding: 24px;">មិនមានសំណួរក្នុងវិញ្ញាសានេះទេ (No questions in this test)</p>'}
          </div>
          <script>
            window.onload = function() {
              setTimeout(function() {
                window.print();
              }, 400);
            }
          <\/script>
        </body>
      </html>
    `)
    printWindow.document.close()
    toastSuccess(lang.value === 'kh' ? 'បើកផ្ទាំងបោះពុម្ព PDF ជោគជ័យ' : 'PDF print preview opened')
  } catch (e) {
    console.error('Failed to export test to PDF:', e)
    if (printWindow) {
      try { printWindow.close() } catch (err) {}
    }
    const msg = e.response?.data?.message || (lang.value === 'kh' ? 'មិនអាចទាញយក PDF បានទេ' : 'Failed to export PDF')
    toastError(msg)
  } finally {
    exportingPdfId.value = null
  }
}

// ══════════════════════════════════════════════════════════════════════
// Bulk Question Importer State & Functions (Word / Docs / Excel)
// ══════════════════════════════════════════════════════════════════════
const showBulkImportModal = ref(false)
const bulkInputText = ref('')
const bulkPointsPerQuestion = ref(1)
const parsedPreviewQuestions = ref([])
const skipExampleQuestions = ref(false)
const skippedExamplesCount = ref(0)
const docFileInputRef = ref(null)
const uploadingDocFile = ref(false)
const uploadedDocName = ref('')
const isDragging = ref(false)
const showFormatGuide = ref(false)

const excelFileInputRef = ref(null)
const uploadingExcelFile = ref(false)
const uploadedExcelName = ref('')
const isDraggingExcel = ref(false)

const bulkImportTab = ref('upload')

const openBulkImportModal = () => {
  bulkInputText.value = ''
  parsedPreviewQuestions.value = []
  bulkPointsPerQuestion.value = 1
  uploadedDocName.value = ''
  uploadedExcelName.value = ''
  uploadingDocFile.value = false
  uploadingExcelFile.value = false
  showFormatGuide.value = false
  skipExampleQuestions.value = false
  skippedExamplesCount.value = 0
  bulkImportTab.value = 'upload'
  showBulkImportModal.value = true
}

const switchBulkImportTab = (tab) => {
  bulkImportTab.value = tab
}

const triggerDocFileInput = () => {
  docFileInputRef.value?.click()
}

const clearDocFile = () => {
  uploadedDocName.value = ''
  bulkInputText.value = ''
  parsedPreviewQuestions.value = []
  if (docFileInputRef.value) docFileInputRef.value.value = ''
}

const triggerExcelFileInput = () => {
  excelFileInputRef.value?.click()
}

const clearExcelFile = () => {
  uploadedExcelName.value = ''
  bulkInputText.value = ''
  parsedPreviewQuestions.value = []
  if (excelFileInputRef.value) excelFileInputRef.value.value = ''
}

const clearBulkInput = () => {
  bulkInputText.value = ''
  uploadedDocName.value = ''
  uploadedExcelName.value = ''
  parsedPreviewQuestions.value = []
  if (docFileInputRef.value) docFileInputRef.value.value = ''
  if (excelFileInputRef.value) excelFileInputRef.value.value = ''
}

const onDocFileSelected = async (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  await processDocFile(file)
}

const onDocFileDrop = async (event) => {
  isDragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (!file) return
  await processDocFile(file)
}

const processDocFile = async (file) => {
  const ext = file.name.split('.').pop().toLowerCase()
  if (!['docx', 'doc', 'txt'].includes(ext)) {
    toastError(lang.value === 'kh' ? 'សូមជ្រើសរើសឯកសារ Word (.docx, .doc) ឬ Text (.txt)' : 'Please select a Word (.docx, .doc) or Text (.txt) file')
    return
  }

  uploadingDocFile.value = true
  uploadedDocName.value = file.name

  try {
    let extractedText = ''

    if (ext === 'txt') {
      extractedText = await file.text()
    } else {
      const formData = new FormData()
      formData.append('file', file)
      const res = await axios.post('/api/admin/tests/parse-doc', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      if (res.data?.text) {
        extractedText = res.data.text
      }
    }

    if (extractedText.trim()) {
      bulkInputText.value = extractedText
      runBulkParser()
      toastSuccess(lang.value === 'kh' ? `បានអានឯកសារ '${file.name}' ដោយជោគជ័យ!` : `Successfully loaded '${file.name}'!`)
    } else {
      toastError(lang.value === 'kh' ? 'មិនអាចអានទិន្នន័យពីឯកសារ Word នេះបានទេ' : 'Could not extract text from this document')
    }
  } catch (err) {
    toastError(lang.value === 'kh' ? 'មានបញ្ហាក្នុងការអានឯកសារ' : 'Error reading document')
  } finally {
    uploadingDocFile.value = false
    if (docFileInputRef.value) docFileInputRef.value.value = ''
  }
}

const onExcelFileSelected = async (event) => {
  const file = event.target.files?.[0]
  if (!file) return
  await processExcelFile(file)
}

const onExcelFileDrop = async (event) => {
  isDraggingExcel.value = false
  const file = event.dataTransfer?.files?.[0]
  if (!file) return
  await processExcelFile(file)
}

const processExcelFile = async (file) => {
  const ext = file.name.split('.').pop().toLowerCase()
  if (!['xlsx', 'xls', 'csv'].includes(ext)) {
    toastError(lang.value === 'kh' ? 'សូមជ្រើសរើសឯកសារ Excel (.xlsx, .xls, .csv)' : 'Please select an Excel file (.xlsx, .xls, .csv)')
    return
  }

  uploadingExcelFile.value = true
  uploadedExcelName.value = file.name

  try {
    const data = await file.arrayBuffer()
    const workbook = XLSX.read(data, { type: 'array' })
    const sheetName = workbook.SheetNames[0]
    const worksheet = workbook.Sheets[sheetName]
    const rows = XLSX.utils.sheet_to_json(worksheet, { header: 1 })

    if (!rows || rows.length === 0) {
      toastError(lang.value === 'kh' ? 'ឯកសារ Excel គ្មានទិន្នន័យទេ' : 'Excel file is empty')
      return
    }

    let startIdx = 0
    if (rows.length > 0) {
      const firstCell = String(rows[0][0] || '').toLowerCase()
      if (firstCell.includes('question') || firstCell.includes('សំណួរ') || firstCell.includes('no') || firstCell.includes('ល.រ') || firstCell.includes('title')) {
        startIdx = 1
      }
    }

    const parsed = []
    for (let i = startIdx; i < rows.length; i++) {
      const r = rows[i]
      if (!r || r.length === 0) continue

      const qText = String(r[0] || '').trim()
      if (!qText) continue

      const optA = String(r[1] ?? '').trim()
      const optB = String(r[2] ?? '').trim()
      const optC = String(r[3] ?? '').trim()
      const optD = String(r[4] ?? '').trim()

      const rawChoices = [optA, optB, optC, optD].filter(c => c !== '')
      if (rawChoices.length < 2) continue

      const correctRaw = String(r[5] ?? '').trim().toUpperCase()
      let correctIdx = 0
      if (correctRaw === 'A' || correctRaw === 'ក' || correctRaw === '1') correctIdx = 0
      else if (correctRaw === 'B' || correctRaw === 'ខ' || correctRaw === '2') correctIdx = 1
      else if (correctRaw === 'C' || correctRaw === 'គ' || correctRaw === '3') correctIdx = 2
      else if (correctRaw === 'D' || correctRaw === 'ឃ' || correctRaw === '4') correctIdx = 3
      else {
        const matchIdx = rawChoices.findIndex(c => c.toLowerCase() === correctRaw.toLowerCase())
        if (matchIdx >= 0) correctIdx = matchIdx
      }

      const pts = parseInt(r[6]) || parseInt(bulkPointsPerQuestion.value) || 1
      const passage = String(r[7] ?? '').trim()

      parsed.push({
        text: qText,
        passage: passage,
        isExample: false,
        points: pts,
        correctIndex: correctIdx < rawChoices.length ? correctIdx : 0,
        answers: rawChoices.map((c, cIdx) => ({
          text: c,
          correct: cIdx === (correctIdx < rawChoices.length ? correctIdx : 0)
        }))
      })
    }

    if (parsed.length > 0) {
      parsedPreviewQuestions.value = parsed
      toastSuccess(lang.value === 'kh' ? `បានអាន ${parsed.length} សំណួរពី '${file.name}' ដោយជោគជ័យ!` : `Successfully loaded ${parsed.length} questions from '${file.name}'!`)
    } else {
      toastError(lang.value === 'kh' ? 'រកមិនឃើញសំណួរត្រឹមត្រូវក្នុងឯកសារ Excel ទេ។ សូមពិនិត្យទម្រង់គំរូ។' : 'No valid questions found in Excel file. Please check template.')
    }
  } catch (err) {
    console.error('Failed to parse Excel file:', err)
    toastError(lang.value === 'kh' ? 'មានបញ្ហាក្នុងការអានឯកសារ Excel' : 'Failed to read Excel file')
  } finally {
    uploadingExcelFile.value = false
    if (excelFileInputRef.value) excelFileInputRef.value.value = ''
  }
}

const downloadQuestionSampleExcel = () => {
  const headers = [
    ['សំណួរ (Question)*', 'ជម្រើស ក (Option A)*', 'ជម្រើស ខ (Option B)*', 'ជម្រើស គ (Option C)', 'ជម្រើស ឃ (Option D)', 'ចម្លើយត្រឹមត្រូវ (Answer: A/B/C/D ឬ ក/ខ/គ/ឃ)*', 'ពិន្ទុ (Points)', 'អត្ថបទ (Passage - Optional)']
  ]
  const sampleData = [
    ['តើ HTML តំណាងឱ្យអ្វី?', 'HyperText Markup Language', 'High Tech Machine Language', 'Hyper Tool Multi Language', 'Home Text Markup Language', 'A', 4, ''],
    ['ចម្លើយរបស់ប្រមាណវិធី √(2025) គឺ៖', '45', '25', '15', '35', 'ក', 4, ''],
    ['ដេរីវេទី១នៃអនុគមន៍ f(x)=e^4x គឺ៖', '-4e^3x', '3e^3x', '3xe^3x', '4e^4x', 'D', 4, ''],
    ['What is the capital of Cambodia?', 'Siem Reap', 'Battambang', 'Phnom Penh', 'Kampot', 'C', 2, 'Cambodia is located in Southeast Asia.']
  ]

  const ws = XLSX.utils.aoa_to_sheet([...headers, ...sampleData])
  ws['!cols'] = [
    { wch: 35 },
    { wch: 25 },
    { wch: 25 },
    { wch: 25 },
    { wch: 25 },
    { wch: 22 },
    { wch: 12 },
    { wch: 30 }
  ]

  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Questions Template')
  XLSX.writeFile(wb, 'Question_Import_Template.xlsx')
  toastSuccess(lang.value === 'kh' ? 'ទាញយកគំរូសំណួរ Excel ជោគជ័យ' : 'Downloaded question Excel template')
}

const insertSampleBulkText = () => {
  bulkInputText.value = `១. (៤ពិន្ទុ) តើ HTML តំណាងឱ្យអ្វី?
ក. HyperText Markup Language *
ខ. High Tech Machine Language
គ. Hyper Tool Multi Language
ឃ. Home Text Markup Language

២. (៤ពិន្ទុ) ចម្លើយរបស់ប្រមាណវិធី √(2025) គឺ៖
ក. 45 *	ខ. 25	គ. 15	ឃ. 35

៣. (៤ពិន្ទុ) ដេរីវេទី១នៃអនុគមន៍ f(x)=e^4x គឺ៖
ក. -4e^3x	ខ. 3e^3x	គ. 3xe^3x	ឃ. 4e^4x *`
  runBulkParser()
}

const runBulkParser = () => {
  const result = parseBulkQuestions(bulkInputText.value, skipExampleQuestions.value)
  parsedPreviewQuestions.value = result.questions
  skippedExamplesCount.value = result.skippedExamples
}

const normalizeJsonQuestions = (rawList, skipExamples = true) => {
  if (!Array.isArray(rawList)) return { questions: [], skippedExamples: 0 }
  let skipped = 0
  const normalized = []
  for (const q of rawList) {
    if (!q || (!q.text && !q.question)) continue
    const qText = q.text || q.question || ''
    const isEx = !!q.isExample || /^(?:(?:សំណួរទី|សំណួរ|Example|Ex|Sample)\s*(?:0|០)|(?:0|០)[.:)\-៖]|Q(?:0|០)[.:)\-៖])/i.test(qText.trim())
    if (skipExamples && isEx) {
      skipped++
      continue
    }

    const qPoints = parseInt(q.points) || parseInt(bulkPointsPerQuestion.value) || 1
    const rawAnswers = Array.isArray(q.answers) ? q.answers : (Array.isArray(q.options) ? q.options : [])

    let correctIdx = -1
    if (typeof q.correctIndex === 'number' && q.correctIndex >= 0 && q.correctIndex < rawAnswers.length) {
      correctIdx = q.correctIndex
    } else if (typeof q.correct_index === 'number' && q.correct_index >= 0 && q.correct_index < rawAnswers.length) {
      correctIdx = q.correct_index
    } else {
      correctIdx = rawAnswers.findIndex(a => (typeof a === 'object' && a !== null ? Boolean(a.correct) : false))
    }
    if (correctIdx < 0) correctIdx = 0

    const answers = rawAnswers.map((a, idx) => {
      if (typeof a === 'string') {
        return { text: a, correct: idx === correctIdx }
      }
      return {
        text: a.text || a.option || '',
        correct: idx === correctIdx || Boolean(a.correct)
      }
    })

    if (qText && answers.length >= 2) {
      normalized.push({
        text: qText,
        passage: q.passage || '',
        isExample: isEx,
        points: qPoints,
        correctIndex: correctIdx,
        answers: answers
      })
    }
  }
  return { questions: normalized, skippedExamples: skipped }
}

const parseBulkQuestions = (rawText, skipExamples = true) => {
  if (!rawText || !rawText.trim()) return { questions: [], skippedExamples: 0 }

  try {
    const json = JSON.parse(rawText.trim())
    if (Array.isArray(json)) {
      const res = normalizeJsonQuestions(json, skipExamples)
      if (res.questions.length > 0) return res
    } else if (json && Array.isArray(json.questions)) {
      const res = normalizeJsonQuestions(json.questions, skipExamples)
      if (res.questions.length > 0) return res
    }
  } catch (e) {}

  const khmerDigits = { '០': '0', '១': '1', '២': '2', '៣': '3', '៤': '4', '៥': '5', '៦': '6', '៧': '7', '៨': '8', '៩': '9' }
  const toArabic = (str) => String(str).replace(/[០-៩]/g, d => khmerDigits[d] || d)
  const cleanBoxes = (s) => s.replace(/[\u25a0-\u25ff\u2610-\u2612\u25aa\u25ab]/g, '').trim()

  const lines = rawText.split(/\r?\n/)
  const parsed = []
  let currentQ = null
  let currentPassage = ''
  let passageBuffer = []
  let inReadingSection = false
  let isSkippingCurrentExample = false
  let skippedCount = 0

  // Question start detection
  const isQStart = (line) => {
    const t = line.trim()
    if (/^\(?\s*[\d\u17E0-\u17E9]+\s*(?:ពិន្ទុ|points?|pts?)\s*\)?/i.test(t)) return true
    if (/^(?:(?:សំណួរទី|សំណួរ|Example|Ex|Sample)\s*[\d\u17E0-\u17E9]+[៖:.\s)\-]*|[\d\u17E0-\u17E9]+[.)\-:៖]\s*|Q[\d\u17E0-\u17E9]+[.)\-:៖]\s*)/i.test(t)) return true
    return false
  }

  // Check if a line is an example question
  const isExampleQuestion = (line) => {
    const t = line.trim()
    if (/^(?:(?:សំណួរទី|សំណួរ|Example|Ex|Sample)\s*(?:0|០)|(?:0|០)[.)\-:៖]|Q(?:0|០)[.)\-:៖]|\((?:0|០)\)|\[(?:0|០)\])/i.test(t)) return true
    if (/has\s+been\s+done\s+as\s+an\s+example/i.test(t)) return true
    return false
  }

  // Check if a line begins an answer option
  const isOptionLine = (line) => {
    const t = line.trim()
    return /^(?:[A-Da-d\u1780-\u1783][.:\-៖]|\([A-Da-d\u1780-\u1783]\)|\[[A-Da-d\u1780-\u1783]\])/.test(t)
  }

  const isSectionHeader = (line) => {
    const t = line.trim()
    if (/^={3,}|^_{3,}|^-{3,}/.test(t)) return true
    if (/^(?:[I|V|X]+\.|\bPart\s+[A-Za-z0-9]+|\bSection\s+[A-Za-z0-9]+|ផ្នែក\s*[ក-អ០-៩A-Za-z]+|វិញ្ញាសា|ព្រះរាជាណាចក្រ|ក្រសួង|វិទ្យាស្ថាន)/i.test(t)) return true
    return false
  }

  const optSplitRegex = /\t+|\s{2,}(?=[A-Da-d\u1780-\u1783][.:\-៖]|\([A-Da-d\u1780-\u1783]\)|\[[A-Da-d\u1780-\u1783]\])/

  const charToIdx = (char) => {
    if (!char) return 0
    const c = char.trim()
    const khmerConsonants = { 'ក': 0, 'ខ': 1, 'គ': 2, 'ឃ': 3, 'ង': 4, 'ច': 5 }
    if (khmerConsonants[c] !== undefined) return khmerConsonants[c]
    const khmerNum = { '១': 0, '២': 1, '៣': 2, '៤': 3, '៥': 4, '៦': 5 }
    if (khmerNum[c] !== undefined) return khmerNum[c]
    const upper = c.toUpperCase()
    if (upper >= 'A' && upper <= 'F') return upper.charCodeAt(0) - 65
    const num = parseInt(c) - 1
    if (!isNaN(num) && num >= 0) return num
    return 0
  }

  for (let i = 0; i < lines.length; i++) {
    let line = cleanBoxes(lines[i]).trim()
    if (!line) continue

    if (line.match(/reading/i) || line.match(/អត្ថបទអាន/i)) {
      inReadingSection = true
    }

    if (line.match(/^(?:vocabulary|grammar|ផ្នែកទី\s*[១|I]|Part\s+[A-B]:\s*(?:Vocabulary|Grammar))/i)) {
      inReadingSection = false
      currentPassage = ''
      passageBuffer = []
    }

    // A passage header
    if (line.match(/^(?:Text|Passage|Story|អត្ថបទអាន|Reading\s*\d*)\s*[:៖\-]/i) || line.match(/^Part\s+[A-Z0-9]+:\s*Reading/i)) {
      if (passageBuffer.length > 0 && currentQ && currentQ.answers.length > 0) {
        currentPassage = passageBuffer.join('\n\n').trim()
        passageBuffer = []
      }
      passageBuffer.push(line)
      continue
    }

    // Separate Answer Line
    const ansMatch = line.match(/^(?:Answer|Ans|Correct Answer|Correct|ចម្លើយ|ចម្លើយត្រឹមត្រូវ|ចម្លើយត្រូវ)\s*[:\-=៖]\s*([A-Fa-f1-6\u1780-\u1785\u17E1-\u17E6])/i)
    if (ansMatch && !isSkippingCurrentExample && currentQ && currentQ.answers.length > 0) {
      const targetIdx = charToIdx(ansMatch[1])
      if (targetIdx >= 0 && targetIdx < currentQ.answers.length) {
        currentQ.correctIndex = targetIdx
        currentQ.answers.forEach((a, idx) => { a.correct = (idx === targetIdx) })
      }
      continue
    }

    if (isQStart(line)) {
      if (passageBuffer.length > 0) {
        currentPassage = passageBuffer.join('\n\n').trim()
        passageBuffer = []
      }

      if (currentQ && currentQ.text && currentQ.answers.length >= 2) {
        parsed.push(currentQ)
        currentQ = null
      }

      // Check if this is an example question (Question 0)
      if (skipExamples && isExampleQuestion(line)) {
        isSkippingCurrentExample = true
        skippedCount++
        currentQ = null
        continue
      } else {
        isSkippingCurrentExample = false
      }

      let qPoints = bulkPointsPerQuestion.value || 1
      const pointMatch = line.match(/\(?\s*([\d\u17E0-\u17E9]+)\s*(?:ពិន្ទុ|points?|pts?|marks?)\s*\)?/i)
      if (pointMatch) {
        qPoints = parseInt(toArabic(pointMatch[1])) || 1
      }

      let cleanText = line
        .replace(/^(?:(?:សំណួរទី|សំណួរ|Example|Ex|Sample)\s*[\d\u17E0-\u17E9]+[៖:.\s)\-]*|[\d\u17E0-\u17E9]+[.)\-:៖]\s*|Q[\d\u17E0-\u17E9]+[.)\-:៖]\s*)/i, '')
        .replace(/\(?\s*[\d\u17E0-\u17E9]+\s*(?:ពិន្ទុ|points?|pts?|marks?)\s*\)?/gi, '')
        .trim()

      if (!cleanText) cleanText = line

      const isEx = isExampleQuestion(line)

      currentQ = {
        text: cleanText,
        passage: inReadingSection ? currentPassage : '',
        isExample: isEx,
        points: qPoints,
        correctIndex: 0,
        answers: []
      }
    } else if (isSkippingCurrentExample) {
      continue
    } else if (currentQ) {
      if (isOptionLine(line)) {
        const rawTokens = line.split(optSplitRegex).map(t => t.trim()).filter(Boolean)
        for (const opt of rawTokens) {
          let optText = opt.replace(/^(?:[A-Da-d\u1780-\u1783][.:\-៖]|\([A-Da-d\u1780-\u1783]\)|\[[A-Da-d\u1780-\u1783]\])\s*/i, '').trim()
          optText = cleanBoxes(optText)

          let isCorrect = false
          if (/\*$/.test(optText) || /\s\*\s?/.test(optText) || /\(correct\)/i.test(optText) || /\[correct\]/i.test(optText) || /\[x\]/i.test(optText) || /\(ត្រឹមត្រូវ\)/.test(optText) || /\[ត្រឹមត្រូវ\]/.test(optText) || /\(ចម្លើយត្រូវ\)/.test(optText) || /\[ចម្លើយត្រូវ\]/.test(optText) || /\(ត្រូវ\)/.test(optText) || /\[ត្រូវ\]/.test(optText) || /\(ចម្លើយត្រឹមត្រូវ\)/.test(optText) || /\[ចម្លើយត្រឹមត្រូវ\]/.test(optText) || /[✓✔]/.test(optText)) {
            isCorrect = true
            optText = optText.replace(/\*|\(correct\)|\[correct\]|\(ត្រឹមត្រូវ\)|\[ត្រឹមត្រូវ\]|\(ចម្លើយត្រូវ\)|\[ចម្លើយត្រូវ\]|\(ត្រូវ\)|\[ត្រូវ\]|\(ចម្លើយត្រឹមត្រូវ\)|\[ចម្លើយត្រឹមត្រូវ\]|\[x\]|\[X\]|[✓✔]/gi, '').trim()
          }

          const newAns = { text: optText, correct: isCorrect }
          currentQ.answers.push(newAns)

          if (isCorrect) {
            currentQ.correctIndex = currentQ.answers.length - 1
            currentQ.answers.forEach((a, idx) => {
              a.correct = (idx === currentQ.correctIndex)
            })
          }
        }
      } else {
        if (currentQ.answers.length === 0) {
          currentQ.text += ' ' + line
        } else {
          if (isSectionHeader(line) || line.match(/Comprehension Questions/i) || line.match(/^(?:Text|Passage|Story|Part\s+[A-B]|II\.)/i)) {
            if (line.match(/reading/i) || line.match(/អត្ថបទ/i)) inReadingSection = true
            if (line.match(/^(?:vocabulary|grammar)/i)) inReadingSection = false
            if (inReadingSection && !line.match(/Comprehension Questions/i) && !isSectionHeader(line) && !line.match(/Instruction:/i)) {
              passageBuffer.push(line)
            }
          } else if (inReadingSection && !line.match(/Comprehension Questions/i) && !isSectionHeader(line) && !line.match(/Instruction:/i)) {
            passageBuffer.push(line)
          } else {
            currentQ.answers[currentQ.answers.length - 1].text += ' ' + line
          }
        }
      }
    } else {
      if (inReadingSection && !line.match(/Comprehension Questions/i) && !isSectionHeader(line) && !line.match(/Instruction:/i)) {
        passageBuffer.push(line)
      }
    }
  }

  if (currentQ && currentQ.text && currentQ.answers.length >= 2) {
    parsed.push(currentQ)
  }

  parsed.forEach(q => {
    if (q.correctIndex === null || q.correctIndex === undefined || q.correctIndex >= q.answers.length) {
      q.correctIndex = 0
      if (q.answers.length > 0) q.answers[0].correct = true
    }
  })

  return { questions: parsed, skippedExamples: skippedCount }
}

const commitBulkQuestions = () => {
  if (parsedPreviewQuestions.value.length === 0) return

  const toAdd = parsedPreviewQuestions.value.map(q => ({
    text: q.text,
    passage: q.passage || '',
    isExample: !!q.isExample,
    points: parseInt(q.points) || parseInt(bulkPointsPerQuestion.value) || 1,
    correctIndex: q.correctIndex || 0,
    answers: q.answers.map((a, i) => ({
      text: a.text,
      correct: i === q.correctIndex
    }))
  }))

  questions.value = toAdd

  activeQuestionIdx.value = 0
  isEditingQuestionText.value = false
  editingOptionIdx.value = null
  updateTotalMarks()
  bulkInputText.value = ''
  parsedPreviewQuestions.value = []
  showBulkImportModal.value = false
  toastSuccess(lang.value === 'kh' ? `បាននាំចូល ${toAdd.length} សំណួរដោយជោគជ័យ!` : `Successfully imported ${toAdd.length} questions!`)
}

onMounted(() => {
  loadData()
})
</script>
