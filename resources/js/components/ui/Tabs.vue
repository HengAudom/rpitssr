<template>
  <div class="overflow-x-auto pb-1">
    <div
      :class="[
        'flex items-center gap-1.5 p-1 min-w-max',
        pill ? 'bg-slate-100/90 rounded-2xl border border-slate-200/60' : 'border-b border-slate-200'
      ]"
    >
      <button
        v-for="tab in tabs"
        :key="tab.value"
        type="button"
        :class="[
          'inline-flex items-center gap-2 font-semibold transition-all duration-150 cursor-pointer select-none focus-ring whitespace-nowrap',
          pill
            ? [
                'rounded-xl px-4 py-2 text-sm',
                modelValue === tab.value
                  ? 'bg-white text-blue-600 shadow-soft-xs font-bold'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'
              ]
            : [
                'px-4 py-2.5 text-sm -mb-px border-b-2',
                modelValue === tab.value
                  ? 'border-blue-600 text-blue-600 font-bold'
                  : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'
              ]
        ]"
        @click="$emit('update:modelValue', tab.value)"
      >
        <span
          v-if="tab.icon"
          class="material-symbols-outlined text-lg"
          :style="modelValue === tab.value ? 'font-variation-settings: \'FILL\' 1;' : ''"
        >
          {{ tab.icon }}
        </span>

        <span>{{ tab.label }}</span>

        <!-- Counter Badge -->
        <span
          v-if="tab.count !== undefined"
          :class="[
            'rounded-full px-2 py-0.5 text-xs font-bold',
            modelValue === tab.value
              ? 'bg-blue-50 text-blue-700'
              : 'bg-slate-200/70 text-slate-600'
          ]"
        >
          {{ tab.count }}
        </span>
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  modelValue: { type: [String, Number], required: true },
  tabs: {
    type: Array,
    required: true // array of { label, value, icon, count }
  },
  pill: { type: Boolean, default: true }
})

defineEmits(['update:modelValue'])
</script>
