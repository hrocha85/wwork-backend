# AGENTS.md — wwork-backend

Lê isto no começo de **toda** tarefa. A planta das pastas e o produto estão no repo do PWA.

1. Se este workspace inclui o front: [../planos/00-indice.md](../planos/00-indice.md) e [../planos/09-pastas-e-componentes.md](../planos/09-pastas-e-componentes.md).
2. Senão: https://github.com/hrocha85/wwork-front/blob/main/planos/09-pastas-e-componentes.md e `05-backend-rotas.md`.
3. Rotas, corpos e erros: `planos/05`. Painel: `planos/07`. Senha: `planos/08`.

## Onde o código vai

- Escrita: `app/Actions/{Auth|Team|Clients|Visits|Field|Invoices|Billing|Subscription|Location|OneSignal}/`
- HTTP: `app/Http/Controllers/Api/V1`, `Requests`, `Resources`
- Recorte owner/invited **e** `Support/AgencyContext` (`country` × `trade`) em qualquer listagem entre agências. OneSignal e PDF usam o catálogo daquele ofício.
- Integrações: `app/Services` (`SeatPlan` + `PlanCatalog` / `plan_prices` por país, `InvoicePdf`, `VisitIcs`, `OneSignal`, `Money`)
- Fundador: `app/Filament` — Agency e User. Sem Resource de Client, Visit, Invoice, Payout.

Controller não calcula comissão. Action `RecordCheckEvent` no check-out chama OneSignal. Nome da Action = verbo do contrato.

Fatia 1: login do dono, Filament vazio, `sync_uuid` nas tabelas. Sem mapa.
