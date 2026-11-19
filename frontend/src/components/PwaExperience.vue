<template><div class="pwa-tools" aria-live="polite"><div v-if="!online" class="offline"><span></span> Sem conexão — operações ficam protegidas até a internet voltar.</div><div v-if="installEvent" class="install"><div><b>Instale o aplicativo IntelbrasTech</b><small>Acesso rápido ao portal e ao ERP pelo celular.</small></div><button @click="install">Instalar</button><button class="close" aria-label="Fechar" @click="dismiss">×</button></div><div v-if="updateReady" class="update"><span>Uma nova versão está disponível.</span><button @click="applyUpdate">Atualizar agora</button></div></div></template>
<script setup>
import { onBeforeUnmount,onMounted,ref } from 'vue'
const online=ref(navigator.onLine),installEvent=ref(null),updateReady=ref(false);let registration
const sync=()=>online.value=navigator.onLine
const capture=e=>{e.preventDefault();if(sessionStorage.getItem('pwa-install-dismissed')!=='1')installEvent.value=e}
async function install(){if(!installEvent.value)return;await installEvent.value.prompt();await installEvent.value.userChoice;installEvent.value=null}
function dismiss(){installEvent.value=null;sessionStorage.setItem('pwa-install-dismissed','1')}
function applyUpdate(){registration?.waiting?.postMessage('SKIP_WAITING')}
function updateHandler(e){registration=e.detail;updateReady.value=true}
onMounted(()=>{window.addEventListener('online',sync);window.addEventListener('offline',sync);window.addEventListener('beforeinstallprompt',capture);window.addEventListener('pwa-update-ready',updateHandler);navigator.serviceWorker?.addEventListener('controllerchange',()=>location.reload())})
onBeforeUnmount(()=>{window.removeEventListener('online',sync);window.removeEventListener('offline',sync);window.removeEventListener('beforeinstallprompt',capture);window.removeEventListener('pwa-update-ready',updateHandler)})
</script>
<style scoped>
.pwa-tools{position:relative;z-index:99999}.offline{position:fixed;left:50%;top:12px;transform:translateX(-50%);display:flex;align-items:center;gap:8px;padding:9px 14px;border-radius:9px;background:#8b4310;color:#fff;font-size:12px;font-weight:700;box-shadow:0 8px 24px #0005}.offline span{width:8px;height:8px;border-radius:50%;background:#ffd166}.install,.update{position:fixed;right:18px;bottom:18px;max-width:390px;display:flex;align-items:center;gap:14px;padding:15px 16px;border:1px solid #d5e7dc;border-radius:14px;background:#fff;color:#10261c;box-shadow:0 16px 45px #061b1133}.install div{flex:1}.install b,.install small{display:block}.install small{margin-top:4px;color:#587064;font-size:11px}.install button,.update button{border:0;border-radius:8px;padding:9px 13px;background:#00a335;color:#fff;font-weight:800}.install .close{padding:4px;background:transparent;color:#718078;font-size:20px}.update{background:#101526;color:#fff;border-color:#313952}.update span{font-size:12px}@media(max-width:600px){.install,.update{left:12px;right:12px;bottom:12px}.offline{top:8px;width:calc(100% - 24px);justify-content:center;text-align:center}}
</style>
