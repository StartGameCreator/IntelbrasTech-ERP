import { createHash } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const scriptDirectory = dirname(fileURLToPath(import.meta.url))
const imagePath = resolve(
  scriptDirectory,
  '../public/social/whatsapp-atendimento-20261122.png',
)
const portalLayoutPath = resolve(
  scriptDirectory,
  '../src/components/portal/PortalLayout.vue',
)

const expectedImageHash = '6d9378f335b0ff2068b1392341e788884917940ffc4e11511831c84e0d62acc9'
const officialServiceUrl = "const linkAtendimento = 'https://intelbrastech.com.br/atendimento'"

const imageHash = createHash('sha256')
  .update(readFileSync(imagePath))
  .digest('hex')

if (imageHash !== expectedImageHash) {
  console.error(
    'ERRO: a imagem protegida de /atendimento foi alterada sem atualizar a autorização registrada.',
  )
  process.exit(1)
}

const portalLayout = readFileSync(portalLayoutPath, 'utf8')
if (!portalLayout.includes(officialServiceUrl)) {
  console.error(
    'ERRO: o endereço oficial de atendimento deve permanecer https://intelbrastech.com.br/atendimento.',
  )
  process.exit(1)
}

console.log('Prévia social protegida de /atendimento validada.')
