<template>
  <Teleport to="body">
    <div class="modal-overlay" @click.self="$emit('cancelar')">
      <div class="modal-sm animate-slide-up">
        <div class="modal-header">
          <div class="flex items-center gap-3">
            <div :class="['w-10 h-10 rounded-xl flex items-center justify-center', iconeBg]">
              <ExclamationTriangleIcon v-if="tipo === 'danger'" class="w-5 h-5 text-danger" />
              <QuestionMarkCircleIcon v-else class="w-5 h-5 text-warning" />
            </div>
            <h3 class="text-base font-semibold text-[var(--text-primary)]">{{ titulo }}</h3>
          </div>
          <button @click="$emit('cancelar')" class="btn-ghost btn-icon">
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
        <div class="modal-body">
          <p class="text-sm text-[var(--text-secondary)]">{{ mensagem }}</p>
        </div>
        <div class="modal-footer">
          <button @click="$emit('cancelar')" class="btn-secondary">Cancelar</button>
          <button @click="$emit('confirmar')" :class="btnClasse">
            {{ labelConfirmar }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import { ExclamationTriangleIcon, QuestionMarkCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  titulo:         { type: String, default: 'Confirmar' },
  mensagem:       String,
  tipo:           { type: String, default: 'warning' },
  labelConfirmar: { type: String, default: 'Confirmar' },
})

defineEmits(['confirmar', 'cancelar'])

const iconeBg    = computed(() => props.tipo === 'danger' ? 'bg-danger/15' : 'bg-warning/15')
const btnClasse  = computed(() => props.tipo === 'danger' ? 'btn-danger' : 'btn-primary')
</script>
