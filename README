# Integrador de Automação de Telecom

Protótipo em PHP puro (sem frameworks, sem XAMPP) que simula o fluxo real de um Implantador de Sistemas: receber uma base de leads "suja" exportada de um CRM, higienizar e validar os telefones, e enviar os contatos válidos para uma API de telecom via cURL.

Desenvolvido como projeto de estudo/portfólio para o processo seletivo de Implantador de Sistemas na **3C Plus**.

## O que o script faz

1. **Captura**: lê os leads de um arquivo `leads.csv` (nome + telefone).
2. **Higienização e validação**: usa expressões regulares para remover o DDI `+55` e qualquer caractere não numérico dos telefones, e valida se o resultado tem 10 dígitos (fixo) ou 11 dígitos (celular) — padrão brasileiro de DDD + número.
3. **Integração via cURL**: envia cada lead válido via `POST` em JSON, com os headers `Content-Type: application/json` e `Authorization: Bearer <token>`, para um endpoint de mailing.

O destino do `POST` é configurável: o projeto foi homologado em tempo real usando o [Webhook.site](https://webhook.site), e a mesma lógica se aplica a um endpoint real de API.

## Requisitos

- PHP 7.4+ com a extensão cURL habilitada (vem habilitada por padrão na maioria das instalações).
- Nenhuma dependência externa, nenhum Composer, nenhum servidor web — roda direto via CLI.

## Como usar

1. Clone o repositório e entre na pasta do projeto.
2. Crie um arquivo `leads.csv` na mesma pasta do `integrador.php`, com a seguinte estrutura (primeira linha é cabeçalho e é ignorada pelo script):

```csv
nome,telefone
João da Silva,+55 (45) 99999-8888
Maria Oliveira,45 9 9888-7777
Carlos Pereira,(45)98888 6666
Ana Souza,+55-45-3322-1144
Pedro Henrique,45999990000extra
Fernanda Lima,123
```

3. Abra `integrador.php` e configure a URL de destino na função `enviarLeadParaApi3CPlus`:

```php
$url = 'COLE_AQUI_A_SUA_URL_DO_WEBHOOK_SITE';
```

   - Para testar: gere uma URL gratuita em [webhook.site](https://webhook.site) e cole aqui.
   - Em produção: use o endpoint real da API de mailing.

4. Execute via terminal:

```bash
php integrador.php
```

## Exemplo de saída

```
===================================================
 RELATÓRIO DE HIGIENIZAÇÃO DE LEADS
===================================================
Total recebido do CRM: 6
Total válido para envio: 4
Total descartado: 2

--- Leads descartados (motivo) ---
- Pedro Henrique (45999990000extra): Quantidade de dígitos inválida após limpeza (12 dígitos)
- Fernanda Lima (123): Quantidade de dígitos inválida após limpeza (3 dígitos)

===================================================
 ENVIANDO LEADS VÁLIDOS (HOMOLOGAÇÃO VIA WEBHOOK.SITE)
===================================================
[ENVIADO] João da Silva enviado com sucesso -> HTTP 200
          -> UUID da requisição no Webhook.site: 31240d46-3de4-43a8-...
[ENVIADO] Maria Oliveira enviado com sucesso -> HTTP 200
...

Processo finalizado.
```

## Próximos passos (automação)

O script foi pensado para ser disparado de duas formas, dependendo do cenário de implantação:

- **Lote/agendado** — usando `cron` no Linux, para sincronizações periódicas com o CRM do cliente:
  ```
  0 * * * * php /caminho/integrador.php >> /var/log/integrador.log 2>&1
  ```
- **Tempo real** — usando um **webhook**: o CRM do cliente dispara um `POST` automático para um endpoint próprio sempre que um lead é criado/atualizado, que aplica a mesma lógica de higienização e repassa o contato imediatamente para a API de destino.

## Estrutura do projeto

```
.
├── integrador.php   # script principal
├── leads.csv         # base de leads (não versionada com dados reais)
└── README.md
```

## Aviso

Token de autenticação e URL de destino neste repositório são fictícios/placeholders, usados apenas para fins de estudo e demonstração técnica.