<template>
  <div class="flex items-center gap-3 select-none">
    <!-- Brand Logo Icon / Institutional Seal -->
    <div
      :class="[
        'flex items-center justify-center shrink-0 transition-transform duration-200 group-hover:scale-105',
        sizeClasses
      ]"
    >
      <img
        :src="settings.logoUrl || defaultLogo"
        alt="Brand Logo"
        class="h-full w-full object-contain select-none pointer-events-none drop-shadow-xs"
      />
    </div>

    <!-- Brand Typography -->
    <div v-if="!iconOnly" class="min-w-0">
      <div class="flex items-center gap-1.5">
        <span
          :class="[
            'font-black tracking-tight leading-none text-slate-900',
            textColorClass,
            fontSizeClass
          ]"
        >
          {{ brandTitle }}
        </span>
        <span
          v-if="badgeText"
          :class="[
            'text-[10px] font-extrabold uppercase px-1.5 py-0.5 rounded-md leading-none',
            badgeClass
          ]"
        >
          {{ badgeText }}
        </span>
      </div>
      <p
        :class="[
          'text-[11px] font-bold uppercase tracking-wider leading-none mt-1',
          subColorClass
        ]"
      >
        {{ brandSubtitle }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useSettings } from '../../composables/useSettings'

const defaultLogo = '/logo.png'
const { settings } = useSettings()

const props = defineProps({
  variant: {
    type: String,
    default: 'admin', // 'admin' | 'superadmin' | 'student' | 'public' | 'dark'
  },
  size: {
    type: String,
    default: 'md', // 'sm' | 'md' | 'lg'
  },
  title: {
    type: String,
    default: ''
  },
  subtitle: {
    type: String,
    default: ''
  },
  iconOnly: {
    type: Boolean,
    default: false
  },
  badge: {
    type: String,
    default: ''
  }
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm': return 'h-11 w-11'
    case 'lg': return 'h-16 w-16'
    default: return 'h-13 w-13'
  }
})

const fontSizeClass = computed(() => {
  switch (props.size) {
    case 'sm': return 'text-sm'
    case 'lg': return 'text-lg'
    default: return 'text-base'
  }
})

const textColorClass = computed(() => {
  if (props.variant === 'dark') return 'text-white'
  return 'text-slate-900'
})

const subColorClass = computed(() => {
  if (props.variant === 'dark') return 'text-slate-400'
  return 'text-slate-400'
})

const brandTitle = computed(() => {
  if (props.title) return props.title
  if (settings.institutionName) {
    return settings.institutionName
  }
  if (settings.portalTitle) {
    return settings.portalTitle
  }
  return 'RPITSSR'
})

const brandSubtitle = computed(() => {
  if (props.subtitle) return props.subtitle
  if (settings.portalSubtitle) {
    return settings.portalSubtitle
  }
  return 'EXAM SYSTEM'
})

const badgeText = computed(() => props.badge)

const badgeClass = computed(() => {
  switch (props.variant) {
    case 'superadmin': return 'bg-purple-100 text-purple-700'
    case 'student': return 'bg-emerald-100 text-emerald-700'
    default: return 'bg-blue-100 text-blue-700'
  }
})
</script>
