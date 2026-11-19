<template>
  <div>
    <section class="portal-hero">
      <div class="portal-container portal-hero-grid">
        <div class="portal-reveal">
          <span class="portal-kicker"><Sparkles /> Soluções integradas IntelbrasTech</span>
          <h1>Tecnologia que transforma. <em>Energia que impulsiona o futuro.</em></h1>
          <p>Projetamos ambientes mais seguros, conectados e eficientes — da energia solar à automação, com atendimento próximo em cada etapa.</p>
          <div class="portal-actions"><RouterLink class="portal-button" to="/energia-solar">Simular energia solar <ArrowRight /></RouterLink><RouterLink class="portal-button secondary" to="/contato">Falar com especialista</RouterLink></div>
          <div class="portal-trust"><span><ShieldCheck /> Projeto responsável</span><span><Wrench /> Instalação especializada</span><span><Headphones /> Suporte próximo</span></div>
        </div>
        <div class="portal-hero-visual" role="img" aria-label="Casa moderna com painéis solares">
          <div class="portal-energy-card"><Sun /><span>Energia inteligente</span><b>Do projeto ao pós-venda</b></div>
        </div>
      </div>
    </section>

    <section class="portal-section portal-intro"><div class="portal-container"><div class="portal-centered portal-intro-copy"><span class="portal-overline">Tecnologia para viver e produzir melhor</span><h2>Um parceiro para soluções que precisam funcionar de verdade</h2><p>Unimos diagnóstico, projeto, equipamentos e execução para entregar uma experiência completa, sem promessas genéricas e com escopo transparente.</p></div><PortalShowcaseCarousel /></div></section>

    <section class="portal-section portal-surface"><div class="portal-container"><div class="portal-section-heading"><div><span class="portal-overline">Nossas soluções</span><h2>Conexões que simplificam o seu dia</h2></div><RouterLink to="/contato">Solicitar orientação <ArrowUpRight /></RouterLink></div><div class="portal-solution-grid"><RouterLink v-for="solution in solutions" :key="solution.slug" class="portal-solution-card" :to="solution.slug === 'energia-solar' ? '/energia-solar' : `/solucoes/${solution.slug}`"><component :is="solution.icon" /><span>{{ solution.eyebrow }}</span><h3>{{ solution.title }}</h3><p>{{ solution.description }}</p><b>Conhecer solução <ArrowRight /></b></RouterLink></div></div></section>

    <section class="portal-section portal-solar-band"><div class="portal-container portal-solar-grid"><div><span class="portal-overline light">Energia solar</span><h2>Transforme a conta de energia em uma decisão de longo prazo</h2><p>Atendemos projetos residenciais e empresariais On Grid, Off Grid e híbridos. A pré-simulação abre o diagnóstico; o dimensionamento final depende de análise técnica e documental.</p><ul><li><CircleCheck /> Estudo do perfil de consumo</li><li><CircleCheck /> Projeto e instalação</li><li><CircleCheck /> Monitoramento e manutenção</li></ul><RouterLink class="portal-button white" to="/energia-solar">Começar pré-simulação</RouterLink></div><div class="portal-solar-photo"></div></div></section>

    <section class="portal-section"><div class="portal-container"><div class="portal-section-heading"><div><span class="portal-overline">Como trabalhamos</span><h2>Clareza do primeiro contato à entrega</h2></div></div><div class="portal-steps"><article><b>01</b><h3>Entendimento</h3><p>Ouvimos a necessidade e reunimos as informações essenciais.</p></article><article><b>02</b><h3>Projeto</h3><p>Definimos escopo, solução e premissas técnicas com transparência.</p></article><article><b>03</b><h3>Execução</h3><p>Instalação organizada, testes e orientação para uso correto.</p></article><article><b>04</b><h3>Suporte</h3><p>Acompanhamento e assistência para manter tudo funcionando.</p></article></div></div></section>

    <section class="portal-section portal-cta"><div class="portal-container"><span>Vamos conversar?</span><h2>Conte o que você precisa transformar.</h2><p>Nossa equipe encaminhará sua solicitação para o especialista certo.</p><RouterLink class="portal-button white" to="/contato">Solicitar atendimento <ArrowRight /></RouterLink></div></section>
  </div>
</template>

<script setup>
import { ArrowRight, ArrowUpRight, CircleCheck, Headphones, ShieldCheck, Sparkles, Sun, Wrench } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'
import { solutions } from '@/data/portalContent'
import PortalShowcaseCarousel from '@/components/portal/PortalShowcaseCarousel.vue'
</script>
