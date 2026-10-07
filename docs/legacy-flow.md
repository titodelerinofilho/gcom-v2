# Revisão do fluxo do legado

A revisão compara o código local de `gcom-dts-producao/` com o novo backend. Não constitui homologação de consultas no Oracle de produção. Nenhuma credencial do legado foi utilizada.

## Seleção e agrupamento

`cad_comissao_cliprinc.php` seleciona pedidos por cliente principal (`PCCLIENT.CODREVENDA`; na busca nova, NVL com CODCLI permite também o próprio cliente sem revenda), praça e período: `CONDVENDA IN (1,7)`, `CODCOB <> 'ORDS'`, `POSICAO = 'F'` e `DTCANCEL IS NULL`. O novo `OrderRepository::search` preserva esses filtros e inclui o dia final inteiro; limita cada busca a 500 pedidos. A importação e a geração também verificam elegibilidade, inclusive movimentos de cancelamento. A checagem Oracle representa o estado no instante da consulta; Oracle e PostgreSQL não compartilham uma transação distribuída.

Pedidos de clientes finais distintos podem compor uma comissão do mesmo principal. Cabeçalho completo, cliente final, filial, praça, tabela, plano, frete e itens faturados ficam preservados. Snapshots anteriores a esta revisão não são reescritos; dados ausentes exigem tratamento de migração, sem inventar valores.

## Preços e comissão

A regra administrativa contém pareamentos por `CODFILIAL` e `NUMREGIAO` do pedido. Uma filial específica prevalece sobre `*`. Pares iniciais: CE 1/2, MA 5/6, PI 7/8, BA 30/32 e PE 31/33, respectivamente PSD/PSCF; Bahia e Pernambuco foram confirmados pelo usuário. Ausência de pareamento ou referência bloqueia o cálculo.

Produtos comuns usam `PCTABPR.PVENDA1…7` conforme `PCPLPAG.NUMPR`. ATG usa o `PCPEDI.PTABELA` preservado, conforme confirmação anterior do usuário, com percentual administrativo próprio. A memória mostra PSD, PSCF, PTABELA, referência aplicada, diferença, plano e contexto de cada item.

`bruto = (Σ QT × PVENDA − Σ QT × referência − frete) × percentual / 100`.

Frete é descontado **antes** da margem, corrigindo a divergência entre simulação e total do legado. O bruto é arredondado ao final; o rateio por pedido preserva esse total para futuros estornos. Débitos e devoluções pendentes do principal são aplicados automaticamente depois. A interface envia os IDs esperados da simulação; mudanças exigem nova conferência. O líquido precisa ser positivo, sem descartar ajustes quando o valor é insuficiente.

O desconto na referência é administrativo e exige `PERCENTUALDESC` real no snapshot. O helper legado não selecionava esse campo explicitamente. A consulta de composição captura `PCPEDICESTA`, `PCPRECOCESTAC`, `PCPRECOCESTAI` e preços, respeitando filial e exclusão. Usa `EXISTS` para evitar multiplicar componentes pelo join. O usuário confirmou `QTMP` por unidade do combo: somamos QTMP × PVENDA1 dos componentes e multiplicamos pela quantidade de combos. Exigimos uma única composição ativa para filial/tabela PSD, preservando também a comparação PSCF. Não reproduzimos `SUM(DISTINCT valor)`, que pode eliminar componentes distintos de mesmo preço.

## Devoluções

`MovementRepository::returns` corresponde a `getDevolucoes`, `getDevolucoesAtg`, `consultaCalculoDevolucaoNormal` e `consultaCalculoDevolucaoAtg`:

- `CODOPER = 'ED'`, `DTCANCEL IS NULL`, `CODDEVOL NOT IN (34)`.
- Normal: pedido obrigatório, `CONDVENDA <> 8`, praças fora de 573/570/539/1097/1098, principal por `CODREVENDA`.
- ATG: admite pedido zero/ausente ou condição diferente de 8; não exclui essas praças. Listagem e confirmação admitem cliente principal ou cliente direto; o helper de confirmação do legado já admitia ambos.
- Listagem dos últimos 90 dias; confirmação consulta novamente os itens do `NUMTRANSENT`, sem depender da linha marcada na tela.

Referência de devolução é **`PCTABPR.PTABELA1`**, não `PVENDA` do plano. A dedução normal inicial é `(PUNIT − PTABELA1 PSD) × QT × 80%`; ATG usa 100%. Ambos os percentuais são administrativos. Cada linha resolve sua própria filial/tabela; o legado utilizava a região da última linha da lista. Margem negativa bloqueia o registro para conferência. O sistema preserva consulta e cálculo e impede registrar o mesmo NUMTRANSENT duas vezes.

O parâmetro de pedidos pagos dos helpers antigos não era utilizado. Portanto não acrescentamos esse filtro. `marcar_dev.php` é o caminho usado pela tela; `marcar_dev2.php` é uma alternativa com cálculo diferente, não a fonte normativa desta revisão.

## Cancelamentos e atrasos

`cancel_cons.php` consulta `PCMOV` com `CODOPER = 'S'`, `QT < 0`, `DTCANCEL IS NOT NULL`, principal e período, incluindo motivo de `PCNFCAN`. A tela antiga não gravava dedução. A pedido do usuário, a simulação e geração agora verificam pedidos já comissionados e registram estorno integral para a próxima comissão, usando a comissão original, não preços atuais. Devoluções importadas do mesmo pedido são abatidas do estorno para não cobrar duas vezes. Cancelamentos parciais bloqueiam nova geração até conferência; devoluções posteriores de pedido estornado também são bloqueadas. Ajustes manuais sem rastreio por item requerem conferência financeira de eventual sobreposição.

`PCPREST` preserva os filtros de títulos abertos/não cancelados, `PCCOB.BOLETO` ou cobranças ECOB/SMC e atraso positivo via `F_QTDIASVENCIDOS`, inclusive recebimento previsto e dias úteis da filial. A consulta é informativa; não transforma automaticamente o título inteiro em débito da comissão, como também não fazia o legado.

## Matriz de consultas

| Fonte antiga | Implementação nova | Uso |
| --- | --- | --- |
| Seleção de pedidos em `cad_comissao_cliprinc.php` | `OrderRepository::search/assertEligible` | Consultas Winthor, importação, simulação, geração |
| `getInformacoesPedido` | `OrderRepository::fetch` + `CommissionCalculator` | Itens, preços PSD/PSCF, plano e memória |
| Consulta de combos em `comissao_cliprinc_conf.php` | `OrderRepository::fetch` | Composição por filial/tabela, QTMP por unidade, comparação PSD/PSCF |
| Quatro helpers de devolução | `MovementRepository::returns` + `ReturnImporter` | Listagem, confirmação, cálculo e dedução |
| `cancel_cons.php` | `cancellations/cancelledOrder` + `CancellationSynchronizer` | Consulta e novo estorno após comissão |
| `getAtrasosClientes` | `MovementRepository::overdue` | Títulos atrasados para conferência |
| MySQL comissões, débitos, devoluções e baixas | Entidades/repositórios Doctrine PostgreSQL | Deduções pendentes, vinculação única, persistência e auditoria |
| Rotina 749, solicitada para a versão nova | `PclancRepository::findByRecnum` | Consulta opcional PCLANC pela chave RECNUM |

SQLs foram adaptados e parametrizados, não copiados literalmente. Todos os SQLs enviados a `Statement::query` estão em variáveis nomeadas antes da chamada. Eventos de banco continuam sendo emitidos pela camada Statement e capturados pelos listeners de logs.

## Relatórios e cadastro de débitos do menu legado

`cadastrar_debito.php` equivale ao cadastro manual de ajuste `type=debt`: cliente principal, valor, motivo e referência. O valor fica pendente e é abatido na próxima comissão, com vínculo único preservado.

A tela Relatórios cobre `com_rel.php` (por cliente/pedido e somente ATG ou normal), `dev_listaded.php`/`relatorio_devolucoes.php` e `debitos_deduzidos.php`/`relatorio_debitos.php`. Filtra comissões por principal, pedido, status, modalidade e período de cadastro ou pagamento; filtra ajustes por principal, tipo, pendente/deduzido e período de cadastro ou abatimento. Mostra comissão vinculada, situação e pagamento, com exportação dos mesmos filtros em PDF/XLSX/CSV. Inclui cancelamentos deduzidos. Uma dedução vinculada a comissão pendente ainda não significa pagamento realizado. Os relatórios não importam os registros MySQL históricos automaticamente.
