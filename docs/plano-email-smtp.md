# Plano: e-mail SMTP no WWork

> **Status:** fases 1 e 2 implementadas, com envio síncrono em vez de fila, porque a hospedagem não mantém worker. A fase 3 (e-mail do cliente final) segue pendente de decisão. O estado atual e o passo a passo de teste estão em `plans/email-notifications.md`.

Documento original de planejamento, mantido como histórico.

O desenho segue o Samaúma: um ponto único de envio, fila, falha que não desfaz a ação, e um e-mail por evento (sem reenvio se o mesmo fato chegar de novo).

## O que já existe

| Peça | Onde | Comportamento hoje |
|---|---|---|
| Config SMTP | `config/mail.php`, `.env.example` | `MAIL_MAILER=log`. Host de exemplo `127.0.0.1:2525`. Nenhum e-mail real sai. |
| Fila | `.env.example` `QUEUE_CONNECTION=database` | O bem-vindo do painel implementa `ShouldQueue`. Sem worker, o e-mail fica na tabela `jobs`. |
| Bem-vindo do painel | `app/Mail/OwnerWelcomeMail.php`, view `resources/views/mail/owner-welcome.blade.php` | Texto puro, inglês. Disparado só por `app/Actions/Auth/InvitePaidOwner.php` (dono criado no Filament, pago offline). Inclui senha temporária. |
| Cadastro pelo app | `app/Actions/Auth/RegisterOwner.php` | Cria a conta depois do Stripe e **não** envia e-mail. |
| Convite de parceiro | `app/Mail/PartnerInvited.php`, view `resources/views/mail/partner-invite.blade.php` | Texto puro, inglês, envio síncrono (`Mail::send`). Usado em `InvitePartner` e `ResendInvite`. Link `{frontend}/invites/{token}`. |
| Senha | `app/Actions/Auth/SendPasswordResetLink.php` | Já usa o broker do Laravel (`Password::sendResetLink`). |
| Push de serviço concluído | `app/Actions/OneSignal/NotifyJobFinished.php` | OneSignal para o dono (se quem fez não foi ele) e para o cliente, se houver `onesignal_player_id`. Sem e-mail. |
| Falha | `InvitePaidOwner` e `InvitePartner` | Exceção é engolida e vira atividade `mail.failed`. |

Não há dispatcher, não há chave de deduplicação e não há layout HTML.

## Bloqueio para e-mail do cliente final

`booking_requests` guarda `client_name` e `client_phone` (WhatsApp). Não há e-mail.

`clients.email` existe e é opcional.

Consequência: e-mail para dono e parceiro pode sair com os dados atuais (`users.email` é obrigatório). E-mail para quem pediu horário ou orçamento no link público só depois de um campo novo.

## Fora deste plano

- Inbox dentro do app e push novo. O OneSignal de serviço concluído permanece como está.
- E-mail de check-in, GPS, foto ou meta concluída.
- Troca de provedor (Mailgun, SES, Resend). O transporte é o SMTP já previsto no Laravel.
- E-mail em toda renovação mensal da assinatura. Só na passagem para `active`.

## Arquitetura

As actions continuam donas da regra de negócio. Depois do `save` bem-sucedido, chamam um notificador. O notificador não participa da transação de um jeito que desfaça o pedido, a visita ou a assinatura.

```
Action (já persistiu)
  -> MailNotifier::send(type, dedupeKey, mailable)
       -> se a chave já existe, para
       -> grava a chave
       -> Mail::queue(...)
       -> se o SMTP/fila falhar: atividade mail.failed e log
          a action original segue ok
```

Chave estável, um envio por destinatário e fato. Exemplos:

- `account.welcome:{userId}`
- `team.invited:{inviteId}`
- `booking.requested:{requestId}`
- `visit.offered:{visitId}`
- `subscription.past_due:{subscriptionId}:{stripeEventId}`

Webhook do Stripe pode repetir. A chave impede o segundo e-mail.

Todos os mailables novos implementam `ShouldQueue`, inclusive o convite de parceiro (hoje síncrono). Timeout de SMTP não segura a request.

Layout: um blade HTML compartilhado (marca, título, texto, um botão, rodapé). Os dois textos atuais passam a usar esse layout.

Idioma: locale do `User` (`en`, `pt`, `es`, `pl`, `ro`), a mesma do app. E-mail de cliente sem conta usa a locale do dono da agência.

Remetente: `MAIL_FROM_ADDRESS` e `MAIL_FROM_NAME=WWork`.

## Fluxos

### Fase 1 — destinatário já tem e-mail

| # | E-mail | Disparo | Para quem | Observação |
|---|---|---|---|---|
| 1 | Bem-vindo (cadastro no app) | `RegisterOwner`, depois da cobrança Stripe | Dono | Sem senha no corpo. A pessoa acabou de escolher a senha. Botão para o login. |
| 2 | Bem-vindo (painel) | `InvitePaidOwner` (já existe) | Dono | Mantém senha temporária e o aviso de trocar no primeiro acesso. Só muda o visual e entra no notificador. |
| 3 | Convite de parceiro | `InvitePartner` e `ResendInvite` | E-mail do convite | Mesmo link `/invites/{token}`. Reenvio gera chave nova (`team.invited:{inviteId}:resend:{sentAt}`) para poder mandar de novo de propósito. |
| 4 | Novo horário para aprovar | `BookSlot` cria request `pending` | Dono | Nome, serviço, data e hora. Link da agenda no app. |
| 5 | Novo orçamento para aprovar | `OpenQuote` cria request `pending` `kind=quote` | Dono | Nome, endereço e descrição. |
| 6 | Serviço oferecido ao parceiro | `CreateVisit` quando o responsável é `invited` e o status fica `offered` | Parceiro | Casa, data, hora e a parte dele. É o pedido para aceitar ou recusar. |
| 7 | Assinatura com pagamento pendente | `ApplyStripeEvent`, evento `invoice.payment_failed`, status `past_due` | Dono | O app já bloqueia convite novo nesse estado. O e-mail diz isso. |
| 8 | Assinatura ativa | `ApplyStripeEvent`, evento `invoice.payment_succeeded`, status `active` | Dono | Só quando o status **entra** em `active`. Renovação que já estava `active` não manda e-mail. |

### Fase 2 — ainda só dono e parceiro

| # | E-mail | Disparo | Para quem |
|---|---|---|---|
| 9 | Parceiro aceitou | `AcceptVisit` (`offered` → `todo`) | Dono |
| 10 | Parceiro recusou | `DeclineVisit` | Dono |
| 11 | Cliente aceitou o orçamento | `AnswerQuote` com aceite (cria a visita) | Dono |
| 12 | Assinatura cancelada | `ApplyStripeEvent`, `customer.subscription.deleted` | Dono |
| 13 | Redefinir senha | fluxo atual do broker | Usuário | Mesmo layout. Sem mudar a regra de quem pode pedir o link. |

### Fase 3 — precisa do e-mail do cliente

Campo novo, opcional: `booking_requests.client_email`. O formulário público pede o e-mail, sem tornar obrigatório. Se vier vazio, o dono continua sendo avisado (fases 1 e 2) e o cliente segue no WhatsApp, como hoje.

Para visita e fatura, o endereço é `clients.email`. Se estiver vazio, o e-mail não é enviado.

| # | E-mail | Disparo | Para quem | Condição |
|---|---|---|---|---|
| 14 | Agendamento confirmado | `ScheduleAcceptedRequest` e `CreateVisit` | Cliente | `clients.email` ou `client_email` do pedido |
| 15 | Orçamento pronto | `ReplyToQuote` (status `quoted`) | Cliente | `client_email`. Link público que já existe (`public_token`). |
| 16 | Pedido aprovado ou recusado | `DecideBookingRequest` | Cliente | `client_email` |
| 17 | Fatura | `ShareInvoice` | Cliente | `clients.email`. O texto/link de WhatsApp que a action já devolve permanece. O e-mail é um canal a mais, com o mesmo PDF. |
| 18 | Fatura paga | `MarkInvoicePaid` | Cliente | `clients.email` |

`ChangeAssignee`, cancelamento de visita e recusa de orçamento pelo cliente (`AnswerQuote` com recusa) ficam para uma leva seguinte, se a fase 2 estiver estável. Não entram na primeira implementação.

## SMTP e operação

No `.env` do backend, no mesmo espírito do Samaúma:

```
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_SCHEME=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=WWork
```

Porta 587 usa STARTTLS. Porta 465 usa `MAIL_SCHEME=smtps`.

Comando novo: `php artisan wwork:mail-test {email}`. Mostra mailer, host, porta e remetente (sem imprimir a senha) e manda uma mensagem curta. Serve para validar o SMTP antes dos fluxos.

Worker no servidor: `php artisan queue:work`. Sem ele, tudo que for `ShouldQueue` fica em `jobs`.

O `.env` de produção não entra no git. O plano só documenta as chaves. Os valores reais ficam no servidor.

## Arquivos previstos

Novos:

- `app/Support/MailNotifier.php`
- `app/Console/Commands/SendTestMailCommand.php` (`wwork:mail-test`)
- `resources/views/mail/layout.blade.php`
- uma view e um mailable por fluxo da fase escolhida
- migration de `booking_requests.client_email` somente se a fase 3 for aprovada
- testes de feature com `Mail::fake()` nos fluxos alterados

Alterados:

- `RegisterOwner`, `InvitePaidOwner`, `InvitePartner`, `ResendInvite`
- `BookSlot`, `OpenQuote`
- `CreateVisit`
- `ApplyStripeEvent`
- e, se a fase 2 entrar no mesmo ciclo: `AcceptVisit`, `DeclineVisit`, `AnswerQuote`
- `OwnerWelcomeMail` e `PartnerInvited` passam a usar o layout e a fila
- catálogos de idioma do backend para os assuntos, se o padrão do projeto for `lang/{locale}`

O front só muda na fase 3: campo opcional de e-mail no pedido público de horário e no orçamento.

## Ordem de entrega

1. SMTP, comando de teste e `MailNotifier` com dedupe. Nenhum fluxo novo ainda. Critério: o comando entrega uma mensagem na caixa indicada e um segundo disparo da mesma chave não duplica.
2. Unificar os dois e-mails que já existem e acrescentar o bem-vindo do `RegisterOwner`.
3. Fluxos 4, 5, 6, 7 e 8 (pedido, orçamento, serviço oferecido, pagamento pendente, assinatura ativa).
4. Fase 2 (aceite, recusa, orçamento aceito pelo cliente, cancelamento, senha).
5. Fase 3, só com aprovação explícita do campo `client_email`.

Cada fase fecha com `Mail::fake()` nos testes existentes desses endpoints, mais um teste de que a chave repetida não enfileira de novo.

## Decisões para validar

- [ ] Fases 1 e 2 neste ciclo, e fase 3 (e-mail do cliente) só depois.
- [ ] Remetente e host SMTP (quem opera a caixa define os valores no servidor; o código não grava segredo).
- [ ] Assinatura ativa: um e-mail só na entrada em `active`, não na renovação.
- [ ] Convite reenviado pode mandar outro e-mail. Os outros eventos não.
- [ ] Serviço concluído continua só no OneSignal.
- [ ] E-mail do cliente é opcional. Sem endereço, o fluxo de WhatsApp atual não muda.
