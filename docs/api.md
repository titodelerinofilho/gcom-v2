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
| GET `/commissions` | Filtro `status=pending/approved/paid/rejected` |
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
| POST `/commissions/checks` | `customerCode`, `mode=normal/atg`; consulta títulos vencidos/devoluções e sincroniza estornos, sem importar devoluções não selecionadas |
| POST `/commissions/preview` | `orderIds`, `square` obrigatório, `mode`, `adjustmentIds` opcionais; retorna regra, memória, bruto/deduções/líquido |
| GET `/winthor/orders/available` | Operador: `customer` principal, `from`, `to`, `square` obrigatórios (até 366 dias), `mode=normal/atg`; DTO com pedidos elegíveis ainda não comissionados |
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

Simulação e criação recebem `returnTransactions` (lista de transações Winthor em strings). A interface envia `[]` inicialmente: devoluções Winthor só entram quando selecionadas. As selecionadas são importadas sem duplicidade e podem ser desmarcadas antes do lançamento; continuam pendentes para outro lançamento. Clientes antigos que omitem o campo preservam a aplicação dos ajustes já importados. Débitos, ajustes manuais e estornos de cancelamento pendentes são automáticos. O retorno `checks` contém títulos vencidos, total em aberto, devoluções disponíveis/selecionadas e `fingerprint`. Envie `expectedChecksFingerprint` e `expectedAdjustmentIds` na criação; mudanças na conferência retornam 409. Débitos consultados no Oracle são informativos, sem abatimento direto. Uma devolução que compõe um estorno de cancelamento já registrado deve acompanhá-lo para preservar o estorno integral.

Cada devolução em `checks.returns` inclui `items` com pedido, produto, descrição, quantidade e `paymentStatus`: `paid` quando há comissão paga para esse item, `not_found` quando nenhum pagamento foi encontrado neste GCOM e `unmatched` quando faltam dados para correlacionar. `paidCommissions` contém ID, código, modalidade e data do pagamento. A correlação exige o mesmo principal, pedido, produto, cliente final e filial, além de comissão `paid` com pagamento confirmado. O histórico MySQL do legado não é consultado. O indicador é informativo, sem selecionar ou bloquear deduções, e é preservado na memória do lançamento. Mudanças nos pagamentos correlacionados também invalidam o `fingerprint` da simulação.

POST `/commissions/{id}/reject` exige perfil Financeiro e `reason` entre 10 e 2000 caracteres. Aceita comissões pendentes ou aprovadas sem pagamento; encerra em `rejected`, preservando cálculo, pedidos e deduções originais no histórico. Libera os vínculos para reutilização. `rejectionReason`, `rejectedAt` e `rejectedBy` são retornados nas listas/detalhes; comissões reprovadas não podem ser aprovadas ou pagas.

## Praça e modalidade

GET `/commissions/squares` lista códigos de praças, tipos PSD/PSCF e os pares de praças/tabelas. Busca, simulação e criação exigem `square`; comissão normal rejeita praças PSD. A busca normal usa estritamente `PCCLIENT.CODREVENDA`, com o `PCPEDC.CODPRACA` escolhido. Simulação e criação repetem essa validação no Oracle, inclusive contra alterações posteriores à busca. O cálculo preserva `square` e `context.comparisonSquare` e usa o pareamento correspondente na filial configurada; `orderRegion` permanece sendo a tabela original do pedido. Histórico anterior permanece intacto.

## Relatórios filtrados

GET `/reports/commissions` retorna `{items,total,amount,page}` e aceita `customer`, `orderNumber`, `status=pending/approved/paid/rejected`, `mode=normal/atg`, `dateBasis=created/paid`, `from`, `to`, `page`.
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

### Histórico de relatórios

Toda exportação em PDF/XLSX/CSV preserva os dados consultados, os filtros, o período, a data e o responsável em `report_snapshot`. A resposta inclui `X-Report-Id` e `X-Report-Url`; o link da aplicação é `/reports/history/{id}` e exige autenticação. GET `/reports/history?page=1` lista os registros e GET `/reports/history/{id}` apresenta seus metadados. GET `/reports/history/{id}.{format}` gera outro arquivo com os mesmos dados, sem consultar novamente as comissões e sem criar outro snapshot. O banco impede atualização e remoção desses registros.

Os três formatos traduzem status, modalidades, tipos de ajustes e conferência do vínculo com o Winthor. Incluem `Gerado em` (data/hora do arquivo atual) e `Dados preservados em` (data/hora original), no fuso `America/Fortaleza`. CSV possui três linhas de metadados antes dos títulos das colunas. PDF organiza os dados em uma tabela compacta com observações adicionais; XLSX/CSV conservam as colunas detalhadas.

### Auditoria por comissão paga

GET `/audit/commissions?query=&page=1` permite buscar por código da comissão, código/nome do principal ou número do pedido. POST `/audit/commissions/{id}/verify` exige `ROLE_AUDITOR`, autenticação e CSRF. Consulta o Winthor somente para leitura, inclusive pedidos cancelados, compara cabeçalho, produtos e notas com os snapshots e registra `commission.verified` com o resultado preservado. Não altera pedidos, cálculos ou pagamentos.

O retorno contém `commission`, `payment`, `checkedAt`, `orders` e `events`. Cada pedido indica `unchanged`, `changed`, `missing` ou `incomplete`, além das diferenças (`section`, `record`, `field`, `saved`, `current`). `currentInvoices` informa cancelamentos atuais da NF. Os eventos incluem responsáveis, datas, aprovação, reprovação, pagamento e vínculo com a rotina 749, incluindo comissões anteriores reprovadas que utilizaram os mesmos pedidos. Snapshots anteriores à captura completa das notas não permitem comparar retroativamente todos os campos da NF; essa limitação é explícita. Novas importações preservam também as notas da `PCNFSAID`.
