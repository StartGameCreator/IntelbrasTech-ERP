<template>
  <div class="registration" :data-theme="resolvedTheme">
    <header class="topbar">
      <RouterLink to="/login" class="brand">
        <span class="brand-mark">IT</span>
        <span class="brand-copy">
          <strong>IntelbrasTech</strong>
          <small>Marketplace Nacional de Serviços Técnicos</small>
        </span>
      </RouterLink>

      <div class="top-actions">
        <span class="already">Já possui acesso? <RouterLink to="/login">Entrar</RouterLink></span>
        <button class="theme-button" type="button" @click="toggleTheme" :title="themeTitle">
          <SunIcon v-if="resolvedTheme === 'dark'" />
          <MoonIcon v-else />
        </button>
      </div>
    </header>

    <main class="page-shell">
      <nav class="progress">
        <button
          v-for="item in steps"
          :key="item.id"
          type="button"
          :class="{ active: step === item.id, done: step > item.id, available: item.id <= maxStep }"
          @click="goToStep(item.id)"
        >
          <span class="number">{{ item.id }}</span>
          <span class="step-copy">
            <strong>{{ item.title }}</strong>
            <small>{{ item.subtitle }}</small>
          </span>
          <ArrowRightIcon v-if="item.id < 4" class="step-arrow" />
        </button>
      </nav>

      <section class="desktop-flow">
        <aside class="promo-panel">
          <span class="promo-badge">JUNTE-SE À MAIOR PLATAFORMA TÉCNICA DO BRASIL</span>
          <h1>Comece agora sua jornada profissional</h1>
          <p>Conectamos clientes a técnicos qualificados em todo o país com segurança, tecnologia e confiança.</p>

          <div class="promo-benefits">
            <div>
              <ShieldCheckIcon />
              <span><strong>Conexões reais</strong><small>Clientes verificados e chamados qualificados para seu perfil profissional.</small></span>
            </div>
            <div>
              <StarIcon />
              <span><strong>Reputação que cresce</strong><small>Avaliações reais que impulsionam sua credibilidade no mercado.</small></span>
            </div>
            <div>
              <MapPinIcon />
              <span><strong>Cobertura nacional</strong><small>Atue na sua região ou expanda para todo o Brasil.</small></span>
            </div>
            <div>
              <LockClosedIcon />
              <span><strong>Segurança total</strong><small>Seus dados protegidos e pagamentos seguros.</small></span>
            </div>
          </div>

          <img
            class="brazil-map"
            src="/images/platform/mapa-brasil-intelbrastech.png"
            alt="Mapa tecnológico do Brasil conectado pela IntelbrasTech"
          />

          <div class="coverage-card">
            <strong>+12.500 <small>profissionais ativos em todo o Brasil</small></strong>
            <p>Estrutura preparada para conectar técnicos, clientes e empresas em todos os estados.</p>
          </div>
        </aside>

        <article class="flow-panel profile-panel" :class="panelState(1)">
          <div class="panel-tag">PASSO 1</div>
          <h1>Como você quer<br>se cadastrar?</h1>
          <p>Escolha o tipo de conta que melhor representa seu perfil na plataforma.</p>

          <div class="profile-list">
            <button
              v-for="item in profiles"
              :key="item.id"
              type="button"
              :class="['profile-card', item.id, { selected: profile === item.id }]"
              @click="selectProfile(item.id)"
            >
              <span class="profile-icon">
                <component :is="item.icon" />
              </span>
              <span class="profile-body">
                <strong>{{ item.title }}</strong>
                <small>{{ item.description }}</small>
                <ul>
                  <li v-for="benefit in item.benefits" :key="benefit">{{ benefit }}</li>
                </ul>
                <span v-if="item.note" class="profile-note">{{ item.note }}</span>
              </span>
              <CheckCircleIcon v-if="profile === item.id" class="selected-check" />
            </button>
          </div>

          <button class="primary-action" type="button" :disabled="!profile" @click="advanceFromProfile">
            Continuar <ArrowRightIcon />
          </button>
          <p class="bottom-login">Já possui acesso? <RouterLink to="/login">Entrar</RouterLink></p>
        </article>

        <article class="flow-panel social-panel" :class="panelState(2)">
          <div class="panel-tag">PASSO 2</div>
          <h2>Escolha uma forma<br>de continuar</h2>
          <p>Utilize sua conta social para criar seu acesso de forma rápida e segura.</p>

          <div class="social-stack">
            <button type="button" @click="social('google')"><span class="google">G</span>Continuar com Google</button>
            <button type="button" @click="social('microsoft')"><span class="microsoft">▦</span>Continuar com Microsoft</button>
            <button type="button" @click="social('apple')"><span class="apple">●</span>Continuar com Apple</button>
            <button type="button" @click="social('facebook')"><span class="facebook">f</span>Continuar com Facebook</button>
          </div>

          <div class="divider"><span>OU</span></div>

          <button class="email-option" type="button" @click="continueWithEmail">
            <EnvelopeIcon />
            <span><strong>Continuar com e-mail</strong><small>Usaremos seu e-mail para criação da conta.</small></span>
          </button>

          <div class="trust-list">
            <div><ShieldCheckIcon /><span><strong>Seguro e confiável</strong><small>Suas informações estão protegidas.</small></span></div>
            <div><EnvelopeIcon /><span><strong>Sem spam</strong><small>Não publicaremos nada em suas redes sociais.</small></span></div>
            <div><BoltIcon /><span><strong>Rápido e prático</strong><small>Menos passos para você começar agora.</small></span></div>
          </div>

          <p class="legal">Ao continuar, você concorda com nossos <a href="#">Termos e Condições</a> e <a href="#">Política de Privacidade</a>.</p>
        </article>

        <article class="flow-panel data-panel" :class="panelState(3)">
          <div class="panel-tag">PASSO 3</div>
          <h2>Complete seus dados</h2>
          <p>Precisamos de algumas informações para finalizar seu cadastro.</p>

          <form @submit.prevent="submitRegistration">
            <section class="form-section">
              <h3>Dados pessoais</h3>
              <div class="form-grid">
                <label v-if="profile === 'cliente'" class="wide">
                  <span>Modalidade do cliente *</span>
                  <select v-model="form.modalidade_cliente" required @change="ajustarDocumentoCliente">
                    <option value="contratante_cpf">Cliente contratante - CPF</option>
                    <option value="contratante_cnpj">Cliente contratante - CNPJ</option>
                    <option value="condominio">CNPJ - Condominio</option>
                  </select>
                </label>
                <label>
                  <span>Nome completo *</span>
                  <input v-model="form.nome" required placeholder="Digite seu nome completo" />
                </label>
                <label>
                  <span>Nome de exibição *</span>
                  <input v-model="form.nome_exibicao" required placeholder="Como será exibido na plataforma" />
                </label>
                <label>
                  <span>CPF *</span>
                  <input v-model="form.cpf" :required="documentoCpfObrigatorio" placeholder="000.000.000-00" />
                </label>
                <label v-if="profile === 'cliente' && form.modalidade_cliente !== 'contratante_cpf'">
                  <span>{{ form.modalidade_cliente === 'condominio' ? 'CNPJ do condominio *' : 'CNPJ *' }}</span>
                  <input v-model="form.cnpj" required placeholder="00.000.000/0000-00" />
                </label>
                <label>
                  <span>Data de nascimento *</span>
                  <input v-model="form.data_nascimento" type="date" required />
                </label>
                <label>
                  <span>Telefone *</span>
                  <input v-model="form.telefone" required placeholder="(61) 9 9999-9999" />
                </label>
                <label>
                  <span>WhatsApp *</span>
                  <input v-model="form.whatsapp" required placeholder="(61) 9 9999-9999" />
                </label>
                <label class="wide">
                  <span>E-mail *</span>
                  <input v-model="form.email" type="email" required placeholder="seu@email.com" />
                </label>
              </div>
            </section>

            <section v-if="authMethod === 'email'" class="form-section">
              <h3>Segurança</h3>
              <div class="form-grid">
                <label>
                  <span>Senha *</span>
                  <div class="password">
                    <input v-model="form.senha" :type="showPassword ? 'text' : 'password'" minlength="8" required placeholder="Mínimo 8 caracteres" />
                    <button type="button" @click="showPassword = !showPassword"><EyeSlashIcon v-if="showPassword" /><EyeIcon v-else /></button>
                  </div>
                </label>
                <label>
                  <span>Confirmar senha *</span>
                  <input v-model="form.senha_confirmation" type="password" minlength="8" required placeholder="Confirme sua senha" />
                </label>
              </div>
            </section>

            <section v-if="profile === 'tecnico'" class="form-section">
              <h3>Dados profissionais</h3>
              <div class="form-grid">
                <label class="wide">
                  <span>Especialidades *</span>
                  <select v-model="form.categorias" multiple required>
                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.nome }}</option>
                  </select>
                </label>
                <label>
                  <span>Anos de experiência *</span>
                  <input v-model.number="form.anos_experiencia" type="number" min="0" required />
                </label>
                <label>
                  <span>Raio de atendimento (km) *</span>
                  <input v-model.number="form.raio_atendimento_km" type="number" min="1" required placeholder="Ex.: 50" />
                </label>
                <label>
                  <span>CREA</span>
                  <input v-model="form.crea" />
                </label>
                <label>
                  <span>CRT</span>
                  <input v-model="form.crt" />
                </label>
                <label>
                  <span>CFT</span>
                  <input v-model="form.cft" />
                </label>
                <label class="wide">
                  <span>Experiência e especialidades *</span>
                  <textarea v-model="form.especialidades" required placeholder="Conte um pouco sobre sua experiência profissional"></textarea>
                </label>
              </div>

              <div class="profile-assets">
                <div class="asset-header">
                  <h4>Foto e resumo profissional</h4>
                </div>
                <div class="asset-row single">
                  <input v-model="form.foto_url" type="url" placeholder="Link da foto do perfil profissional" />
                  <textarea v-model="form.experiencia" placeholder="Resumo das qualificacoes, principais servicos e diferenciais"></textarea>
                </div>
              </div>

              <div class="profile-assets">
                <div class="asset-header">
                  <h4>Certificados de competencia</h4>
                  <button type="button" @click="addCertificado">Adicionar certificado</button>
                </div>
                <div v-for="(certificado, index) in form.certificados" :key="`cert-${index}`" class="asset-row">
                  <input v-model="certificado.nome" placeholder="Nome do certificado ou curso" />
                  <input v-model="certificado.url" type="url" placeholder="Link do certificado" />
                  <button type="button" @click="removeCertificado(index)">Remover</button>
                </div>
              </div>

              <div class="profile-assets">
                <div class="asset-header">
                  <h4>Fotos de servicos entregues</h4>
                  <button type="button" @click="addPortfolio">Adicionar servico</button>
                </div>
                <div v-for="(servico, index) in form.portfolio" :key="`port-${index}`" class="asset-row portfolio-row">
                  <input v-model="servico.titulo" placeholder="Titulo do servico" />
                  <input v-model="servico.url" type="url" placeholder="Link da foto do servico" />
                  <textarea v-model="servico.descricao" placeholder="Descricao curta do servico entregue"></textarea>
                  <button type="button" @click="removePortfolio(index)">Remover</button>
                </div>
              </div>
            </section>

            <section v-if="profile === 'colaborador'" class="form-section">
              <h3>Acesso administrativo</h3>
              <div class="form-grid">
                <label>
                  <span>Cargo *</span>
                  <input v-model="form.cargo" required />
                </label>
                <label>
                  <span>Nível solicitado *</span>
                  <select v-model="form.nivel_colaborador" required>
                    <option value="administrador_jr">Administrador Jr.</option>
                    <option value="administrador_pleno">Administrador Pleno</option>
                    <option value="administrador_senior">Administrador Sênior</option>
                  </select>
                </label>
                <label class="wide">
                  <span>Motivação *</span>
                  <textarea v-model="form.motivacao" required minlength="30"></textarea>
                </label>
              </div>
            </section>

            <section class="form-section">
              <h3>Endereço e atuação</h3>
              <div class="form-grid">
                <label>
                  <span>CEP *</span>
                  <input v-model="form.cep" required placeholder="00000-000" @blur="buscarCep" />
                </label>
                <label>
                  <span>UF *</span>
                  <input v-model="form.estado" maxlength="2" required />
                </label>
                <label>
                  <span>Cidade *</span>
                  <input v-model="form.cidade" required />
                </label>
                <label>
                  <span>Bairro</span>
                  <input v-model="form.bairro" />
                </label>
                <label class="wide">
                  <span>Endereço *</span>
                  <input v-model="form.logradouro" required />
                </label>
                <label>
                  <span>Número *</span>
                  <input v-model="form.numero" required />
                </label>
              </div>
              <div v-if="profile === 'cliente'" class="location-consent">
                <div>
                  <strong>Compartilhamento de localizacao</strong>
                  <small>Ajuda a encontrar o tecnico ideal e permite acompanhar o atendimento quando houver servico no local.</small>
                </div>
                <div class="location-actions">
                  <button type="button" @click="usarLocalizacaoAproximada">Usar aproximada pelo CEP</button>
                  <button type="button" @click="pedirLocalizacaoExata">Permitir localizacao exata</button>
                </div>
                <p>{{ localizacaoStatus }}</p>
              </div>
            </section>

            <label class="terms">
              <input v-model="accepted" type="checkbox" required />
              <span>Li e aceito os <a href="#">Termos de Uso</a> e a <a href="#">Política de Privacidade</a>.</span>
            </label>

            <button class="primary-action" type="submit" :disabled="sending">
              {{ sending ? 'Enviando...' : 'Continuar' }} <ArrowRightIcon />
            </button>
          </form>
        </article>

        <article class="flow-panel success-panel" :class="panelState(4)">
          <div class="panel-tag">PASSO 4</div>
          <div class="success-art">
            <span class="success-ring"><CheckIcon /></span>
          </div>
          <h2>Cadastro enviado<br>com sucesso!</h2>
          <p>Sua conta foi registrada e está em análise.</p>
          <p>Em breve, nossa equipe verificará suas informações e você receberá um e-mail com o resultado.</p>

          <div class="next-steps">
            <strong>Próximos passos</strong>
            <ul>
              <li>Análise dos dados em até 24 horas</li>
              <li>Você receberá um e-mail</li>
              <li>Após aprovação, acesso liberado</li>
            </ul>
          </div>

          <RouterLink to="/login" class="primary-action login-action">Ir para o login</RouterLink>

          <div class="support">
            <span class="whatsapp">●</span>
            <span><strong>Dúvidas? Fale com a gente</strong><small>WhatsApp: (61) 9 9999-9999</small></span>
          </div>
        </article>
      </section>

      <section class="mobile-flow">
        <article v-if="step === 1" class="mobile-panel">
          <div class="panel-tag">PASSO 1</div>
          <h1>Como você quer se cadastrar?</h1>
          <p>Escolha o tipo de conta que melhor representa seu perfil na plataforma.</p>

          <div class="mobile-map-wrap">
            <img
              src="/images/platform/mapa-brasil-intelbrastech.png"
              alt="Mapa tecnológico do Brasil conectado pela IntelbrasTech"
            />
            <div>
              <strong>+12.500</strong>
              <small>profissionais ativos em todo o Brasil</small>
            </div>
          </div>

          <div class="profile-list">
            <button
              v-for="item in profiles"
              :key="item.id"
              type="button"
              :class="['profile-card', item.id, { selected: profile === item.id }]"
              @click="selectProfile(item.id)"
            >
              <span class="profile-icon"><component :is="item.icon" /></span>
              <span class="profile-body"><strong>{{ item.title }}</strong><small>{{ item.description }}</small></span>
              <CheckCircleIcon v-if="profile === item.id" class="selected-check" />
            </button>
          </div>
          <button class="primary-action" type="button" :disabled="!profile" @click="advanceFromProfile">Continuar <ArrowRightIcon /></button>
        </article>

        <article v-else-if="step === 2" class="mobile-panel">
          <button type="button" class="back" @click="step = 1"><ArrowLeftIcon /> Voltar</button>
          <div class="panel-tag">PASSO 2</div>
          <h2>Escolha uma forma de continuar</h2>
          <p>Utilize uma conta social ou prossiga com seu e-mail.</p>
          <div class="social-stack">
            <button type="button" @click="social('google')"><span class="google">G</span>Continuar com Google</button>
            <button type="button" @click="social('microsoft')"><span class="microsoft">▦</span>Continuar com Microsoft</button>
            <button type="button" @click="social('apple')"><span class="apple">●</span>Continuar com Apple</button>
            <button type="button" @click="social('facebook')"><span class="facebook">f</span>Continuar com Facebook</button>
          </div>
          <div class="divider"><span>OU</span></div>
          <button class="email-option" type="button" @click="continueWithEmail"><EnvelopeIcon /><span><strong>Continuar com e-mail</strong><small>Crie sua conta com e-mail e senha.</small></span></button>
        </article>

        <article v-else-if="step === 3" class="mobile-panel">
          <button type="button" class="back" @click="step = 2"><ArrowLeftIcon /> Voltar</button>
          <div class="panel-tag">PASSO 3</div>
          <h2>Complete seus dados</h2>
          <p>Preencha as informações para finalizar seu cadastro.</p>
          <form @submit.prevent="submitRegistration">
            <div class="form-grid mobile-grid">
              <label v-if="profile === 'cliente'"><span>Modalidade *</span><select v-model="form.modalidade_cliente" required @change="ajustarDocumentoCliente"><option value="contratante_cpf">Cliente CPF</option><option value="contratante_cnpj">Cliente CNPJ</option><option value="condominio">CNPJ - Condominio</option></select></label>
              <label><span>Nome completo *</span><input v-model="form.nome" required /></label>
              <label><span>Nome de exibição *</span><input v-model="form.nome_exibicao" required /></label>
              <label><span>CPF *</span><input v-model="form.cpf" :required="documentoCpfObrigatorio" /></label>
              <label v-if="profile === 'cliente' && form.modalidade_cliente !== 'contratante_cpf'"><span>CNPJ *</span><input v-model="form.cnpj" required /></label>
              <label><span>Data de nascimento *</span><input v-model="form.data_nascimento" type="date" required /></label>
              <label><span>Telefone *</span><input v-model="form.telefone" required /></label>
              <label><span>WhatsApp *</span><input v-model="form.whatsapp" required /></label>
              <label><span>E-mail *</span><input v-model="form.email" type="email" required /></label>
              <template v-if="authMethod === 'email'">
                <label><span>Senha *</span><input v-model="form.senha" type="password" minlength="8" required /></label>
                <label><span>Confirmar senha *</span><input v-model="form.senha_confirmation" type="password" minlength="8" required /></label>
              </template>
              <template v-if="profile === 'tecnico'">
                <label><span>Especialidades *</span><select v-model="form.categorias" multiple required><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.nome }}</option></select></label>
                <label><span>Anos de experiência *</span><input v-model.number="form.anos_experiencia" type="number" min="0" required /></label>
                <label><span>Raio de atendimento *</span><input v-model.number="form.raio_atendimento_km" type="number" min="1" required /></label>
                <label><span>Experiência *</span><textarea v-model="form.especialidades" required></textarea></label>
                <label><span>Foto do perfil</span><input v-model="form.foto_url" type="url" placeholder="Link da foto" /></label>
                <label><span>Resumo profissional</span><textarea v-model="form.experiencia"></textarea></label>
                <label><span>Certificado principal</span><input v-model="form.certificados[0].nome" placeholder="Nome do certificado" /></label>
                <label><span>Link do certificado</span><input v-model="form.certificados[0].url" type="url" placeholder="https://..." /></label>
                <label><span>Servico entregue</span><input v-model="form.portfolio[0].titulo" placeholder="Titulo do servico" /></label>
                <label><span>Foto do servico</span><input v-model="form.portfolio[0].url" type="url" placeholder="https://..." /></label>
              </template>
              <template v-if="profile === 'colaborador'">
                <label><span>Cargo *</span><input v-model="form.cargo" required /></label>
                <label><span>Nível solicitado *</span><select v-model="form.nivel_colaborador"><option value="administrador_jr">Administrador Jr.</option><option value="administrador_pleno">Administrador Pleno</option><option value="administrador_senior">Administrador Sênior</option></select></label>
                <label><span>Motivação *</span><textarea v-model="form.motivacao" minlength="30" required></textarea></label>
              </template>
              <label><span>CEP *</span><input v-model="form.cep" required @blur="buscarCep" /></label>
              <label><span>UF *</span><input v-model="form.estado" maxlength="2" required /></label>
              <label><span>Cidade *</span><input v-model="form.cidade" required /></label>
              <label><span>Endereço *</span><input v-model="form.logradouro" required /></label>
            </div>
            <div v-if="profile === 'cliente'" class="location-consent">
              <div><strong>Localizacao</strong><small>Compartilhe para encontrar o tecnico mais adequado.</small></div>
              <div class="location-actions"><button type="button" @click="usarLocalizacaoAproximada">CEP</button><button type="button" @click="pedirLocalizacaoExata">GPS</button></div>
              <p>{{ localizacaoStatus }}</p>
            </div>
            <label class="terms"><input v-model="accepted" type="checkbox" required /><span>Aceito os Termos de Uso e a Política de Privacidade.</span></label>
            <button class="primary-action" type="submit" :disabled="sending">{{ sending ? 'Enviando...' : 'Continuar' }} <ArrowRightIcon /></button>
          </form>
        </article>

        <article v-else class="mobile-panel mobile-success">
          <div class="panel-tag">PASSO 4</div>
          <div class="success-art"><span class="success-ring"><CheckIcon /></span></div>
          <h2>Cadastro enviado com sucesso!</h2>
          <p>Sua conta foi registrada e está em análise.</p>
          <div class="next-steps">
            <strong>Próximos passos</strong>
            <ul><li>Análise dos dados em até 24 horas</li><li>Você receberá um e-mail</li><li>Após aprovação, acesso liberado</li></ul>
          </div>
          <RouterLink to="/login" class="primary-action login-action">Ir para o login</RouterLink>
        </article>
      </section>
    </main>

    <footer class="footer">
      <div><ShieldCheckIcon /><strong>Conexões reais</strong><small>Clientes e profissionais verificados.</small></div>
      <div><StarIcon /><strong>Mais oportunidades</strong><small>Expanda seus serviços em todo o Brasil.</small></div>
      <div><ChartBarIcon /><strong>Crescimento profissional</strong><small>Fortaleça sua reputação e visibilidade.</small></div>
      <div><LockClosedIcon /><strong>Seguro e confiável</strong><small>Dados protegidos e pagamentos seguros.</small></div>
    </footer>
  </div>
</template>

<script setup>
import { computed, markRaw, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import {
  ArrowLeftIcon, ArrowRightIcon, BoltIcon, ChartBarIcon, CheckCircleIcon, CheckIcon,
  EnvelopeIcon, EyeIcon, EyeSlashIcon, LockClosedIcon, MapPinIcon, MoonIcon, ShieldCheckIcon,
  StarIcon, SunIcon, UserGroupIcon, UserIcon, WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline'

const toast = useToast()
const step = ref(Number(sessionStorage.getItem('it-register-step') || 1))
const maxStep = ref(Number(sessionStorage.getItem('it-register-max-step') || 1))
const profile = ref(sessionStorage.getItem('it-register-profile') || '')
const authMethod = ref(sessionStorage.getItem('it-register-auth-method') || '')
const categories = ref([])
const sending = ref(false)
const accepted = ref(false)
const showPassword = ref(false)
const localizacaoStatus = ref('Nenhuma localizacao compartilhada ainda.')
const theme = ref(localStorage.getItem('intelbrastech-theme') || 'auto')
const systemDark = ref(window.matchMedia('(prefers-color-scheme: dark)').matches)
const media = window.matchMedia('(prefers-color-scheme: dark)')

const resolvedTheme = computed(() => theme.value === 'auto' ? (systemDark.value ? 'dark' : 'light') : theme.value)
const themeTitle = computed(() => resolvedTheme.value === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro')

const steps = [
  { id: 1, title: 'Escolha seu perfil', subtitle: 'Selecione o tipo de conta' },
  { id: 2, title: 'Login Social', subtitle: 'Escolha como continuar' },
  { id: 3, title: 'Complete seus dados', subtitle: 'Preencha suas informações' },
  { id: 4, title: 'Cadastro realizado', subtitle: 'Envio para análise' },
]

const profiles = [
  {
    id: 'tecnico',
    title: 'Técnico Especialista',
    description: 'Ofereça serviços, receba chamados e construa sua reputação profissional.',
    benefits: ['Perfil e portfólio', 'Chamados por região', 'RAT digital', 'Avaliações'],
    icon: markRaw(WrenchScrewdriverIcon),
  },
  {
    id: 'cliente',
    title: 'Cliente',
    description: 'Encontre profissionais, solicite orçamentos e acompanhe atendimentos.',
    benefits: ['Abertura de chamados', 'Comparação de técnicos', 'Chat e vídeo', 'Histórico completo'],
    icon: markRaw(UserIcon),
  },
  {
    id: 'colaborador',
    title: 'Colaborador do Sistema',
    description: 'Solicite acesso administrativo à plataforma IntelbrasTech.',
    benefits: ['Administrador Jr.', 'Administrador Pleno', 'Administrador Sênior'],
    note: 'O nível será definido pelo Administrador Master.',
    icon: markRaw(UserGroupIcon),
  },
]

const blankForm = () => ({
  nome: '', nome_exibicao: '', email: '', senha: '', senha_confirmation: '',
  telefone: '', whatsapp: '', cpf: '', cnpj: '', data_nascimento: '', tipo_pessoa: 'PF',
  modalidade_cliente: 'contratante_cpf',
  cep: '', estado: '', cidade: '', bairro: '', logradouro: '', numero: '',
  latitude: null, longitude: null, localizacao_tipo: '', localizacao_compartilhada: false,
  categorias: [], anos_experiencia: null, raio_atendimento_km: 50,
  especialidades: '', experiencia: '', foto_url: '', crea: '', crt: '', cft: '', cargo: '',
  certificados: [{ nome: '', url: '' }],
  portfolio: [{ titulo: '', url: '', descricao: '' }],
  nivel_colaborador: 'administrador_jr', motivacao: '',
})
const form = ref(blankForm())
const documentoCpfObrigatorio = computed(() => profile.value !== 'cliente' || form.value.modalidade_cliente === 'contratante_cpf')

watch(step, value => {
  sessionStorage.setItem('it-register-step', String(value))
  maxStep.value = Math.max(maxStep.value, value)
  sessionStorage.setItem('it-register-max-step', String(maxStep.value))
})
watch(profile, value => sessionStorage.setItem('it-register-profile', value || ''))
watch(authMethod, value => sessionStorage.setItem('it-register-auth-method', value || ''))

function panelState(id) {
  return {
    active: step.value === id,
    completed: step.value > id,
    locked: id > maxStep.value,
  }
}

function selectProfile(id) {
  profile.value = id
  if (id === 'cliente') ajustarDocumentoCliente()
}

function advanceFromProfile() {
  if (!profile.value) return
  step.value = 2
}

function continueWithEmail() {
  authMethod.value = 'email'
  step.value = 3
}

function social(provider) {
  if (!profile.value) {
    toast.info('Escolha primeiro o seu perfil.')
    step.value = 1
    return
  }

  authMethod.value = provider
  sessionStorage.setItem('it-register-social-provider', provider)
  const base = String(api.defaults.baseURL || '/api/v1').replace(/\/$/, '')
  window.location.href = `${base}/auth/social/${provider}/redirect?intent=register&perfil=${encodeURIComponent(profile.value)}`
}

function goToStep(id) {
  if (id <= maxStep.value) step.value = id
}

function toggleTheme() {
  theme.value = resolvedTheme.value === 'dark' ? 'light' : 'dark'
  localStorage.setItem('intelbrastech-theme', theme.value)
}

function onThemeChange(event) {
  systemDark.value = event.matches
}

async function loadCategories() {
  try {
    categories.value = (await api.get('/platform/categorias')).data || []
  } catch {
    categories.value = []
  }
}

async function submitRegistration() {
  sending.value = true
  try {
    const payload = {
      ...form.value,
      social_provider: authMethod.value !== 'email' ? authMethod.value : null,
    }
    if (profile.value === 'tecnico') {
      payload.certificados = limparCertificados(form.value.certificados)
      payload.portfolio = limparPortfolio(form.value.portfolio)
      payload.metadados = form.value.foto_url ? { foto_url: form.value.foto_url } : {}
    }
    if (profile.value === 'cliente') {
      ajustarDocumentoCliente()
      payload.tipo_pessoa = form.value.modalidade_cliente === 'contratante_cpf' ? 'PF' : 'PJ'
      payload.metadados = {
        modalidade_cliente: form.value.modalidade_cliente,
        rastreamento_atendimento: 'cliente_autorizou_localizacao_para_matching_e_acompanhamento_de_servico',
      }
    }
    delete payload.foto_url
    const response = await api.post(`/platform/cadastros/${profile.value}`, payload)
    toast.success(response.data?.message || 'Cadastro enviado com sucesso.')
    step.value = 4
    sessionStorage.removeItem('it-register-step')
    sessionStorage.removeItem('it-register-max-step')
  } catch (error) {
    const errors = error.response?.data?.errors
    toast.error(errors ? Object.values(errors)[0][0] : (error.response?.data?.message || 'Não foi possível concluir o cadastro.'))
  } finally {
    sending.value = false
  }
}

function ajustarDocumentoCliente() {
  if (profile.value !== 'cliente') return
  form.value.tipo_pessoa = form.value.modalidade_cliente === 'contratante_cpf' ? 'PF' : 'PJ'
  if (form.value.modalidade_cliente !== 'contratante_cpf') {
    form.value.cpf = ''
  }
}

async function buscarCep() {
  const cep = String(form.value.cep || '').replace(/\D/g, '')
  if (cep.length !== 8) return

  try {
    const { data } = await api.get(`/cep/${cep}`)
    if (data?.erro) return
    form.value.estado = data.uf || form.value.estado
    form.value.cidade = data.localidade || form.value.cidade
    form.value.bairro = data.bairro || form.value.bairro
    form.value.logradouro = data.logradouro || form.value.logradouro
    usarLocalizacaoAproximada()
  } catch {
    toast.info('Nao foi possivel consultar o CEP automaticamente.')
  }
}

function usarLocalizacaoAproximada() {
  if (profile.value !== 'cliente') return
  form.value.localizacao_tipo = 'aproximada_cep'
  form.value.localizacao_compartilhada = true
  form.value.latitude = null
  form.value.longitude = null
  localizacaoStatus.value = 'Localizacao aproximada pelo CEP selecionada.'
}

function pedirLocalizacaoExata() {
  if (!navigator.geolocation) {
    toast.info('Este navegador nao permite compartilhar localizacao.')
    return
  }

  localizacaoStatus.value = 'Solicitando permissao de localizacao...'
  navigator.geolocation.getCurrentPosition(
    position => {
      form.value.latitude = Number(position.coords.latitude.toFixed(8))
      form.value.longitude = Number(position.coords.longitude.toFixed(8))
      form.value.localizacao_tipo = 'exata_gps'
      form.value.localizacao_compartilhada = true
      localizacaoStatus.value = 'Localizacao exata autorizada pelo cliente.'
    },
    () => {
      localizacaoStatus.value = 'Permissao negada. Vamos usar a localizacao aproximada pelo CEP.'
      usarLocalizacaoAproximada()
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
  )
}

function addCertificado() {
  form.value.certificados.push({ nome: '', url: '' })
}

function removeCertificado(index) {
  form.value.certificados.splice(index, 1)
  if (!form.value.certificados.length) addCertificado()
}

function addPortfolio() {
  form.value.portfolio.push({ titulo: '', url: '', descricao: '' })
}

function removePortfolio(index) {
  form.value.portfolio.splice(index, 1)
  if (!form.value.portfolio.length) addPortfolio()
}

function limparCertificados(items) {
  return (items || [])
    .map(item => ({
      nome: String(item.nome || '').trim(),
      url: String(item.url || '').trim(),
    }))
    .filter(item => item.nome || item.url)
}

function limparPortfolio(items) {
  return (items || [])
    .map(item => ({
      titulo: String(item.titulo || '').trim(),
      url: String(item.url || '').trim(),
      descricao: String(item.descricao || '').trim(),
    }))
    .filter(item => item.titulo || item.url || item.descricao)
}

onMounted(() => {
  media.addEventListener('change', onThemeChange)
  loadCategories()
})
onBeforeUnmount(() => media.removeEventListener('change', onThemeChange))
</script>

<style scoped>
.registration{--bg:#07101f;--surface:#0b1729;--surface2:#101d31;--text:#f8fafc;--muted:#9aa9c1;--border:#20314a;--accent:#315cff;--purple:#7c2cff;min-height:100vh;background:radial-gradient(circle at 50% 0%,rgba(45,74,150,.13),transparent 34%),var(--bg);color:var(--text);font-family:Inter,system-ui,sans-serif}
.registration[data-theme="light"]{--bg:#eef3fb;--surface:#fff;--surface2:#f7f9fd;--text:#101828;--muted:#667085;--border:#d7dfec;background:linear-gradient(180deg,#f7f9fd,#edf2fa)}
.topbar{height:72px;padding:0 28px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:color-mix(in srgb,var(--surface) 86%,transparent)}
.brand{display:flex;align-items:center;gap:10px}.brand-mark{width:40px;height:40px;border-radius:9px;display:grid;place-items:center;background:linear-gradient(135deg,#2f68ff,#7c2cff);font-weight:900;color:#fff}.brand-copy strong,.brand-copy small{display:block}.brand-copy strong{font-size:16px}.brand-copy small{font-size:10px;color:var(--muted)}.top-actions{display:flex;align-items:center;gap:16px}.already{font-size:12px;color:var(--muted)}.already a,.bottom-login a,.legal a,.terms a{color:#3f7cff}.theme-button{width:42px;height:32px;border:1px solid var(--border);border-radius:999px;background:var(--surface2);display:grid;place-items:center}.theme-button svg{width:17px}
.page-shell{padding:18px}.progress{max-width:1500px;margin:0 auto 18px;display:grid;grid-template-columns:repeat(4,1fr);gap:18px}.progress button{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:10px;text-align:left;color:var(--muted);opacity:.52}.progress button.available{opacity:1}.number{width:42px;height:42px;border-radius:50%;border:1px solid var(--border);display:grid;place-items:center;font-weight:800}.progress .active .number,.progress .done .number{background:linear-gradient(135deg,#315cff,#7c2cff);border-color:transparent;color:#fff}.step-copy strong,.step-copy small{display:block}.step-copy strong{font-size:12px;color:var(--text)}.step-copy small{font-size:10px;margin-top:3px}.step-arrow{width:20px;color:var(--muted)}
.desktop-flow{max-width:1720px;margin:auto;display:grid;grid-template-columns:1.02fr 1.04fr .82fr 1.24fr .86fr;gap:12px;align-items:start}.flow-panel{min-height:760px;border:1px solid var(--border);border-radius:14px;background:linear-gradient(180deg,color-mix(in srgb,var(--surface) 94%,#162b50 6%),var(--surface));padding:20px;transition:.25s}.flow-panel.locked{opacity:.35;filter:saturate(.45);pointer-events:none}.flow-panel.active{box-shadow:0 0 0 1px rgba(55,99,255,.24),0 22px 60px rgba(0,0,0,.18)}.flow-panel.completed{opacity:.82}.panel-tag{display:inline-flex;padding:5px 9px;border-radius:6px;background:rgba(86,56,255,.22);color:#c4b5fd;font-size:9px;font-weight:900;letter-spacing:.5px}.flow-panel h1,.flow-panel h2{font-size:25px;line-height:1.12;font-weight:900;margin-top:15px}.flow-panel>p{color:var(--muted);font-size:12px;line-height:1.55;margin-top:9px}.profile-list{display:flex;flex-direction:column;gap:10px;margin-top:20px}.profile-card{position:relative;display:grid;grid-template-columns:auto 1fr;gap:11px;text-align:left;border:1px solid var(--border);border-radius:12px;background:color-mix(in srgb,var(--surface2) 94%,transparent);padding:14px;color:var(--text)}.profile-card.selected{border-color:#2f68ff;box-shadow:0 0 0 1px #2f68ff,0 0 22px rgba(47,104,255,.22)}.profile-card.cliente.selected{border-color:#10b981}.profile-card.colaborador.selected{border-color:#9333ea}.profile-icon{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:rgba(47,104,255,.18);color:#4f8bff}.profile-card.cliente .profile-icon{background:rgba(16,185,129,.15);color:#20d89a}.profile-card.colaborador .profile-icon{background:rgba(147,51,234,.16);color:#b76cff}.profile-icon svg{width:24px}.profile-body strong,.profile-body small{display:block}.profile-body strong{font-size:14px}.profile-body small{font-size:11px;line-height:1.5;color:var(--muted);margin-top:5px}.profile-body ul{margin-top:10px;padding-left:16px;font-size:10px;line-height:1.65;color:var(--muted)}.profile-note{display:block;margin-top:9px;padding:7px;border-radius:6px;background:rgba(147,51,234,.14);color:#c084fc;font-size:9px}.selected-check{position:absolute;right:9px;top:9px;width:20px;color:#3b82f6}.primary-action{width:100%;min-height:42px;margin-top:16px;padding:11px 16px;border-radius:7px;background:linear-gradient(90deg,#315cff,#7c2cff);color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;gap:8px}.primary-action:disabled{opacity:.45}.primary-action svg{width:16px}.bottom-login{text-align:center;font-size:10px!important;margin-top:10px!important}
.social-stack{display:flex;flex-direction:column;gap:8px;margin-top:22px}.social-stack button{min-height:43px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);display:flex;align-items:center;gap:11px;padding:0 13px;color:var(--text);font-size:12px;font-weight:700}.social-stack button span{font-size:20px;width:25px;text-align:center}.google{color:#4285f4}.microsoft{color:#00a4ef}.apple{color:var(--text)}.facebook{color:#1877f2}.divider{display:flex;align-items:center;gap:10px;margin:17px 0;color:var(--muted);font-size:9px}.divider:before,.divider:after{content:'';height:1px;background:var(--border);flex:1}.email-option{width:100%;border:1px solid var(--border);border-radius:8px;background:var(--surface2);padding:12px;display:flex;gap:10px;align-items:center;text-align:left;color:var(--text)}.email-option>svg{width:23px;color:#8ab0ff}.email-option strong,.email-option small{display:block}.email-option strong{font-size:12px}.email-option small{font-size:10px;color:var(--muted);margin-top:3px}.trust-list{display:flex;flex-direction:column;gap:15px;margin-top:24px}.trust-list>div{display:flex;gap:10px}.trust-list svg{width:19px;color:#6aa2ff;flex:none}.trust-list strong,.trust-list small{display:block}.trust-list strong{font-size:10px}.trust-list small{font-size:9px;color:var(--muted);line-height:1.45;margin-top:3px}.legal{font-size:9px!important;margin-top:40px!important}
.form-section{margin-top:18px;padding-top:14px;border-top:1px solid var(--border)}.form-section h3{font-size:11px;margin-bottom:10px}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.form-grid label>span{display:block;font-size:9px;font-weight:700;margin-bottom:5px}.form-grid input,.form-grid select,.form-grid textarea{width:100%;border:1px solid var(--border);border-radius:7px;background:var(--surface2);color:var(--text);padding:10px;font-size:10px}.form-grid textarea{min-height:68px;resize:vertical}.form-grid select[multiple]{min-height:92px}.wide{grid-column:1/-1}.password{position:relative}.password input{padding-right:35px}.password button{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:26px;height:26px;display:grid;place-items:center;color:var(--muted)}.password svg{width:15px}.terms{display:flex;align-items:flex-start;gap:7px;margin-top:14px;font-size:9px;color:var(--muted)}.terms input{margin-top:2px}
.profile-assets{margin-top:12px;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--surface2) 82%,transparent);padding:10px}.asset-header{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px}.asset-header h4{font-size:10px;font-weight:900}.asset-header button,.asset-row button{border:1px solid var(--border);border-radius:7px;background:var(--surface);color:var(--text);padding:7px 9px;font-size:9px;font-weight:800}.asset-row{display:grid;grid-template-columns:1fr 1fr auto;gap:8px;margin-top:8px}.asset-row.single{grid-template-columns:1fr}.asset-row.portfolio-row{grid-template-columns:1fr 1fr}.asset-row.portfolio-row textarea,.asset-row.portfolio-row button{grid-column:1/-1}.asset-row input,.asset-row textarea{width:100%;border:1px solid var(--border);border-radius:7px;background:var(--surface);color:var(--text);padding:9px;font-size:10px}.asset-row textarea{min-height:58px;resize:vertical}.asset-row button{color:#fca5a5}
.location-consent{margin-top:12px;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--surface2) 84%,transparent);padding:12px;display:grid;gap:9px}.location-consent strong,.location-consent small{display:block}.location-consent strong{font-size:11px}.location-consent small,.location-consent p{font-size:9px;color:var(--muted);line-height:1.45}.location-actions{display:flex;gap:8px;flex-wrap:wrap}.location-actions button{border:1px solid var(--border);border-radius:7px;background:var(--surface);color:var(--text);padding:8px 10px;font-size:9px;font-weight:800}
.success-panel{display:flex;flex-direction:column}.success-art{height:230px;display:grid;place-items:center}.success-ring{width:112px;height:112px;border-radius:50%;display:grid;place-items:center;border:2px solid #31e6c7;box-shadow:0 0 40px rgba(49,230,199,.2),0 22px 0 -7px rgba(95,40,255,.45)}.success-ring svg{width:53px;color:#31e6c7}.next-steps{margin-top:20px;border:1px solid var(--border);border-radius:9px;background:var(--surface2);padding:13px}.next-steps strong{font-size:11px}.next-steps ul{font-size:9px;color:var(--muted);line-height:1.9;padding-left:17px;margin-top:6px}.login-action{text-decoration:none}.support{margin-top:auto;border:1px solid var(--border);border-radius:9px;padding:12px;display:flex;gap:10px;align-items:center}.support .whatsapp{width:34px;height:34px;border-radius:50%;background:rgba(16,185,129,.18);color:#20d89a;display:grid;place-items:center}.support strong,.support small{display:block}.support strong{font-size:10px}.support small{font-size:9px;color:var(--muted);margin-top:3px}

.promo-panel{min-height:760px;border:1px solid var(--border);border-radius:14px;background:radial-gradient(circle at 50% 65%,rgba(56,74,255,.16),transparent 37%),linear-gradient(180deg,color-mix(in srgb,var(--surface) 95%,#102750 5%),var(--surface));padding:22px;display:flex;flex-direction:column;overflow:hidden}
.promo-badge{align-self:flex-start;padding:5px 9px;border-radius:6px;background:rgba(91,47,255,.18);color:#a78bfa;font-size:8px;font-weight:900;letter-spacing:.35px}
.promo-panel>h1{font-size:25px;line-height:1.12;font-weight:900;margin-top:18px}
.promo-panel>p{font-size:11px;line-height:1.65;color:var(--muted);margin-top:11px}
.promo-benefits{display:flex;flex-direction:column;gap:13px;margin-top:21px}
.promo-benefits>div{display:flex;gap:10px;align-items:flex-start}
.promo-benefits svg{width:20px;flex:none;color:#538aff;padding:3px;border-radius:6px;background:rgba(47,104,255,.12);box-sizing:content-box}
.promo-benefits strong,.promo-benefits small{display:block}
.promo-benefits strong{font-size:10px}
.promo-benefits small{font-size:8px;line-height:1.5;color:var(--muted);margin-top:2px}
.brazil-map{width:112%;max-height:250px;object-fit:contain;align-self:center;margin:22px -6% 4px;mix-blend-mode:screen;filter:drop-shadow(0 0 18px rgba(72,79,255,.25))}
.coverage-card{margin-top:auto;border:1px solid var(--border);border-radius:9px;background:color-mix(in srgb,var(--surface2) 92%,transparent);padding:12px}
.coverage-card>strong{display:block;font-size:16px;color:#fff}
.registration[data-theme="light"] .coverage-card>strong{color:var(--text)}
.coverage-card>strong small{font-size:9px;font-weight:500;color:var(--muted)}
.coverage-card>p{font-size:8px;line-height:1.5;color:var(--muted);margin-top:7px}
.mobile-map-wrap{margin:17px 0 5px;border:1px solid var(--border);border-radius:12px;background:radial-gradient(circle,rgba(57,66,255,.18),transparent 62%),var(--surface2);padding:10px;text-align:center}
.mobile-map-wrap img{display:block;width:100%;height:190px;object-fit:contain;mix-blend-mode:screen}
.mobile-map-wrap>div{display:flex;align-items:baseline;justify-content:center;gap:6px;margin-top:3px}
.mobile-map-wrap strong{font-size:16px}
.mobile-map-wrap small{font-size:9px;color:var(--muted)}

.mobile-flow{display:none}.footer{max-width:1500px;margin:14px auto 0;padding:18px;display:grid;grid-template-columns:repeat(4,1fr);gap:14px;border-top:1px solid var(--border)}.footer>div{text-align:center}.footer svg{width:26px;margin:auto;color:#6d8dff}.footer strong,.footer small{display:block}.footer strong{font-size:10px;margin-top:6px}.footer small{font-size:9px;color:var(--muted);margin-top:3px}
.back{display:flex;align-items:center;gap:5px;color:#4f83ff;font-size:11px;margin-bottom:12px}.back svg{width:15px}
@media(max-width:1400px){.desktop-flow{grid-template-columns:1fr 1fr 1fr 1fr}.promo-panel{display:none}}
@media(max-width:1100px){.desktop-flow{grid-template-columns:repeat(2,1fr)}.flow-panel{min-height:auto}.progress{grid-template-columns:repeat(4,minmax(0,1fr))}.step-copy small{display:none}}
@media(max-width:767px){
  .topbar{padding:0 14px;height:62px}.brand-mark{width:35px;height:35px}.brand-copy strong{font-size:13px}.brand-copy small{font-size:8px}.already{display:none}.page-shell{padding:12px}.progress{display:flex;justify-content:center;gap:22px;margin-bottom:17px}.progress button{display:block;opacity:1}.progress .step-copy,.progress .step-arrow{display:none}.number{width:34px;height:34px;font-size:12px}.desktop-flow{display:none}.mobile-flow{display:block;max-width:520px;margin:auto}.mobile-panel{border:1px solid var(--border);border-radius:14px;background:var(--surface);padding:18px;min-height:calc(100vh - 145px)}.mobile-panel h1,.mobile-panel h2{font-size:23px;line-height:1.15;margin-top:14px}.mobile-panel>p{font-size:12px;color:var(--muted);line-height:1.55;margin-top:9px}.mobile-panel .profile-card{padding:15px}.mobile-panel .profile-body ul{display:none}.mobile-panel .primary-action{margin-top:18px}.mobile-grid{grid-template-columns:1fr}.mobile-grid .wide{grid-column:auto}.mobile-success{display:flex;flex-direction:column}.mobile-success .success-art{height:260px}.mobile-success .next-steps{margin-top:24px}.footer{grid-template-columns:repeat(2,1fr);padding:18px 10px}.footer small{display:none}
}
</style>
