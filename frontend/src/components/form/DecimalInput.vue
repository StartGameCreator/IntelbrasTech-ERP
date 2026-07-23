<template>
  <input
    :value="texto"
    type="text"
    inputmode="decimal"
    autocomplete="off"
    @focus="aoFocar"
    @input="aoDigitar"
    @blur="aoSair"
  />
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: [Number, String], default: 0 },
  decimals: { type: Number, default: 2 },
  fixed: { type: Boolean, default: false },
  min: { type: Number, default: 0 },
})
const emit = defineEmits(['update:modelValue'])
const texto = ref('')

function numero(valor) {
  if (typeof valor === 'number') return Number.isFinite(valor) ? valor : 0
  let limpo = String(valor ?? '').trim().replace(/\s/g, '')
  if (limpo.includes(',')) limpo = limpo.replace(/\./g, '').replace(',', '.')
  const resultado = Number(limpo)
  return Number.isFinite(resultado) ? resultado : 0
}

function formatar(valor) {
  const minimo = props.fixed ? props.decimals : 0
  return new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: minimo,
    maximumFractionDigits: props.decimals,
    useGrouping: false,
  }).format(numero(valor))
}

function aoFocar(event) {
  event.target.select()
}

function aoDigitar(event) {
  texto.value = event.target.value.replace(/[^0-9,.-]/g, '')
}

function aoSair() {
  const fator = 10 ** props.decimals
  const valor = Math.max(props.min, Math.round((numero(texto.value) + Number.EPSILON) * fator) / fator)
  emit('update:modelValue', valor)
  texto.value = formatar(valor)
}

watch(() => props.modelValue, valor => { texto.value = formatar(valor) }, { immediate: true })
</script>
