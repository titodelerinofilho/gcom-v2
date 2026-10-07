# Contrato da API

API JSON em `/api`, com cookies de sessão. Busque `GET /api/csrf` e envie seu
`csrfToken` em `X-CSRF-Token` antes de toda mutação, inclusive login. No logout,
envie `_csrf_token` em formulário URL-encoded. Respostas não devem ser cacheadas.
Erros contêm `error` e, quando tratados pelo subscriber, `requestId`.

Listagens retornam `{items, total, page}`, 30 registros por página, com `?page=2`.
Valores financeiros são strings com ponto decimal; datas de entrada usam `YYYY-MM-DD`.

| Método / endpoint | Dados e uso |
| --- | --- |
| GET `/health`, `/csrf` | Saúde do processo e token CSRF; públicos |
| POST `/login` | `email`, `password` |
| GET `/me` | Usuário atual e perfis |
| POST `/logout` | `_csrf_token` |
| GET `/orders` | Filtros `customer`, `available=1` |
| POST `/orders/import` | `orderNumber` string; somente operação |
| GET `/orders/{id}` | Cabeçalho e todos os itens originais |
| GET `/commissions` | Filtro `status=pending/approved/paid` |
| POST `/commissions` | `orderIds` int[], `adjustmentIds` int[] opcional, `ruleVersion` int (da simulação), `mode=normal/atg`, `expectedAdjustmentIds` da simulação, `reason` (10–2000 caracteres) |
| GET `/commissions/{id}` | Memória de cálculo, pedidos, deduções e pagamento |
| POST `/commissions/{id}/approve` | Sem dados; usuário com perfil Financeiro |
| POST `/commissions/{id}/payment` | `amount`, `paidAt`, `notes` (10–2000), `recnum` opcional |
| POST `/commissions/{id}/payment/winthor` | `recnum`; após pagamento, uma vez |
| GET `/adjustments` | Filtros `customer`, `available=1` |
| POST `/adjustments` | `customerCode`, `type=debt/return`, `amount`, `reason`, `sourceReference` |
| GET / POST `/users` | Admin; criação com `name`, `email`, `password` (12–128), `roles`, `active` |
| PATCH `/users/{id}` | Mesmos campos; senha vazia mantém a atual |
| GET `/audit` | Auditoria/admin; filtros `action`, `subject` |
| GET `/reports/summary` | `from`, `to` inclusive; totais, status, clientes e meses por criação |
| GET `/reports/commissions.{pdf,xlsx,csv}` | Mesmo período; exportação registra formato na auditoria |
| POST `/commissions/preview` | `orderIds`, `adjustmentIds` opcionais; retorna regra, memória, bruto/deduções/líquido |
| GET `/winthor/orders/available` | Operador: `customer` principal, `from`, `to` obrigatórios (até 366 dias); DTO com pedidos elegíveis ainda não comissionados |
| GET `/winthor/{orders,returns,cancellations,overdue}` | `customer`; pedidos/cancelamentos exigem `from`, `to`; pedidos admitem `square`; devoluções admitem `atg=1` |
| POST `/winthor/returns/import` | `customer`, `numtransent` strings, `atg` bool; calcula e preserva todos os itens, deduplicando NUMTRANSENT |
| GET `/settings/commission-calculation` | Regra vigente para consulta |
| POST `/settings/commission-calculation` | Admin: `expectedVersion`, `percentage` string, `basis=margin_psd/margin_table/sales`, `priceContexts` [{branch string (`*` ou filial), orderRegion int, psdRegion int, pscfRegion int}], `atgPercentage`, `returnPercentage`, `atgReturnPercentage` strings, `subtractFreight` bool, `applyReferenceDiscount` bool, `reason` |
| GET `/settings/commission-calculation/history` | Admin; histórico paginado imutável |

`payment.winthor` é `null` sem RECNUM. Com vínculo, contém `routine=749`, `recnum`,
`verification` e `details.records` com a linha localizada pela chave primária. O campo NUMTRANS permanece disponível dentro dos detalhes do Winthor. `winthor_lookup`
indica localização em PCLANC; `manual_reference` indica consulta desabilitada.
Não confundir localização com baixa/valor conciliados.

Criação rejeita `grossAmount`, percentual e opções da fórmula enviados pelo cliente.
Mudança de regra entre simulação e criação retorna 409; refaça a simulação.

Todas as deduções pendentes do principal são aplicadas, mesmo sem `adjustmentIds`. A simulação consulta e registra estornos de cancelamento, retornando `adjustmentIds` para conferência.

## Relatórios filtrados

GET `/reports/commissions` retorna `{items,total,amount,page}` e aceita `customer`, `orderNumber`, `status=pending/approved/paid`, `mode=normal/atg`, `dateBasis=created/paid`, `from`, `to`, `page`.
GET `/reports/adjustments` usa o mesmo retorno, com `customer`, `type=debt/return/cancellation`, `state=pending/deducted`, `status` da comissão vinculada, `mode` da devolução e `dateBasis=created/applied`. `deducted_at` é a data de criação da comissão que consumiu a dedução.
GET `/reports/{commissions,adjustments}.{pdf,xlsx,csv}` aplica os mesmos filtros, sem paginação, registrando-os na auditoria.

## Nova comissão por principal e período

A tela consulta `/winthor/orders/available` somente após informar os filtros. O resultado contém items com orderNumber, customerCode (cliente final), customerName, orderDate, branch, total decimal com ponto, priceContext e priceContextError. PriceContext identifica NUMREGIAO, PSD/PSCF resolvidos pela filial, plano, coluna PVENDA e versão/base da regra. Pareamento ou plano ausentes geram erro por pedido, sem escolher outra tabela. A busca usa PCPEDC.DATA, agrupamento por NVL(CODREVENDA,CODCLI) e até 500 pedidos; exclui snapshots vinculados a comissões. Input inválido retorna 422.

Ao simular, o frontend importa apenas os selecionados por `/orders/import`, obtém seus IDs de snapshot e chama `/commissions/preview`. O registro envia os mesmos IDs, mode, ruleVersion e expectedAdjustmentIds retornados pela simulação. Deduções pendentes não ficam limitadas ao período dos pedidos. Alterar principal/datas limpa seleção e simulação.

## Valor manual no pagamento

Em `POST /commissions/{id}/payment`, `amount` deve corresponder ao líquido calculado por padrão.
O financeiro pode enviar `manualAmount: true` e `manualReason` (10–2.000 caracteres) para usar outro valor positivo.
Use ponto e até duas casas decimais. A confirmação é única e mantém a memória de cálculo.
Os detalhes retornam `payment.calculatedAmount`, `payment.amount`, `payment.manualAmount` e `payment.manualReason`.
A auditoria e os exports preservam a origem; o total pago do dashboard considera o pagamento efetivo.
O vínculo opcional por RECNUM continua disponível e não modifica esses valores.
