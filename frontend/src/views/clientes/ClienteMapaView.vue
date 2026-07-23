<template>
  <div class="space-y-4 animate-fade-in">
    <div class="page-header">
      <h1 class="page-title">Mapa de Clientes</h1>
      <RouterLink to="/clientes" class="btn-secondary btn-sm"><ArrowLeftIcon class="w-4 h-4"/> Voltar</RouterLink>
    </div>
    <div class="card overflow-hidden" style="height:calc(100vh - 200px);min-height:400px;">
      <div v-if="loading" class="h-full flex items-center justify-center">
        <ArrowPathIcon class="w-8 h-8 animate-spin text-brand-400" />
      </div>
      <LMap v-else ref="mapaRef" :zoom="5" :center="[-15.7801,-47.9292]" style="height:100%;width:100%;background:#0a0a0f;">
        <LTileLayer url="https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png" attribution="© CARTO" :maxZoom="19"/>
        <LCircleMarker v-for="c in clientes" :key="c.id" :lat-lng="[c.latitude,c.longitude]" :radius="6"
          :color="c.ativo ? '#6366f1' : '#475569'" :fillColor="c.ativo ? '#6366f1' : '#475569'" :fillOpacity="0.8" :weight="1">
          <LPopup>
            <div class="p-2 min-w-[160px]" style="color:#f1f5f9;background:#1a1a2e;">
              <p class="font-semibold text-sm">{{ c.razao_social }}</p>
              <p class="text-xs opacity-70">{{ c.cidade }}/{{ c.estado }}</p>
              <a :href="`/clientes/${c.id}`" class="text-xs text-indigo-400 mt-1 block">Ver cadastro →</a>
            </div>
          </LPopup>
        </LCircleMarker>
      </LMap>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { LMap, LTileLayer, LCircleMarker, LPopup } from '@vue-leaflet/vue-leaflet'
import 'leaflet/dist/leaflet.css'
import api from '@/services/api'
import { ArrowLeftIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'
const clientes = ref([]); const loading = ref(false)
onMounted(async () => {
  loading.value = true
  try { const { data } = await api.get('/clientes/mapa'); clientes.value = data }
  finally { loading.value = false }
})
</script>
<style>
.leaflet-popup-content-wrapper { background:#1a1a2e!important;border:1px solid #252542!important;color:#f1f5f9!important;border-radius:10px!important; }
.leaflet-popup-tip { background:#1a1a2e!important; }
</style>
