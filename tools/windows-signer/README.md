# IntelbrasTech Assinador Local — experimental

Conector opcional para testar certificados instalados em `Cert:\CurrentUser\My` no Windows. Ele não substitui o certificado A1 armazenado no servidor.

1. Extraia o pacote ZIP e execute `INSTALAR.cmd`, ou use diretamente `iniciar-assinador.cmd` no modo portátil.
2. Inicie o assinador como o mesmo usuário que instalou o certificado.
3. Copie o código de pareamento exibido.
4. No ERP, abra **Fiscal → Certificado Digital → Dispositivo (experimental)**.
5. Informe o código, conecte e selecione o certificado.

O serviço aceita conexões somente em `127.0.0.1:17473`, limita CORS ao localhost e ao domínio IntelbrasTech e nunca exporta a chave privada. A rota de assinatura é experimental; a transmissão fiscal continua usando o fluxo A1 do servidor até a homologação completa do driver local.
