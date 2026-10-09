# Ficha do Cliente Revenda

No menu **Ficha do Cliente Revenda**, informe o código do cliente principal e o período.
O período inicial cobre os últimos 12 meses, até hoje; cada consulta aceita até 366 dias.
Um código de cliente vinculado é recusado, indicando seu cliente principal.

A ficha reúne valores gerados, descontos, comissões a receber e pagamentos efetivos;
gráficos mensais de vendas e comissões; os cinco clientes vinculados com maior volume;
devoluções abatidas; títulos em aberto próprios e da carteira; e cancelamentos.
As comissões pagas, devoluções e cancelamentos têm paginação independente de cinco
linhas. Débitos têm dez linhas por página e mostram também títulos a vencer.
Os pagamentos respeitam o valor efetivamente informado, inclusive pagamentos manuais.

## Fontes e datas

O Winthor é consultado somente para leitura, sem importação ou alteração de dados:

- O vínculo é o `CODREVENDA` atual em `PCCLIENT`. Vendas próprias do principal e ATG
  ficam fora das vendas agenciadas. Pedidos usam condições 1/7, posição faturada,
  praças PSCF e os filtros de elegibilidade da integração existente.
- Vendas usam a data do pedido. Devoluções de mercadorias usam a data do movimento.
  Cancelamentos incluem pedido e cancelamento fiscal, usam a data de cancelamento
  e evitam contar duas vezes o mesmo pedido cancelado no período.
- Títulos em aberto refletem a posição atual de toda a carteira, independentemente
  do período escolhido. Dias de atraso seguem a regra de dias úteis do Winthor.

As comissões vêm dos registros e snapshots do GCOM, somente modalidade normal e
sem reprovadas. Valores gerados e devoluções abatidas usam a data de criação da
comissão; pagamentos usam a data de pagamento, mesmo quando a comissão foi criada
antes do período. Uma devolução pendente não aparece como abatida. O valor devolvido
de mercadorias é diferente do valor descontado da comissão e aparece separadamente.

A ficha mostra o instante da consulta e essas bases de comparação. Carteira atual,
cancelamentos e devoluções do período não representam necessariamente a mesma safra
de vendas. Indisponibilidade do Winthor produz um erro, sem apresentar uma carteira
vazia como se estivesse sem débitos.

## Classificação gerencial

A regra inicial `revenda-v1` é explicável e não interfere no cálculo ou na aprovação
de comissões. A pontuação vai de zero a cem:

| Critério | Peso | Regra |
| --- | --- | --- |
| Vendas agenciadas | 40 | Quantidade de pedidos, até a meta proporcional de 60 pedidos por ano. |
| Pontualidade | 30 | Débitos vencidos atuais divididos pelas vendas do período. |
| Cancelamentos | 15 | Pedidos cancelados divididos pela soma de faturados e cancelados. |
| Devoluções | 15 | Valor de mercadorias devolvidas dividido pelas vendas do período. |

Nos três critérios de qualidade, a penalidade aumenta linearmente até zerar os
pontos quando a razão chega a 20%. Ouro exige pelo menos 80 pontos; Prata, 55;
abaixo disso, Bronze. Atraso de pelo menos 60 dias limita a classificação a Bronze.
Uma amostra abaixo de `max(3, ceil(10 × dias do período / 365))` pedidos também limita
a Bronze. Sem pedidos faturados, a ficha mostra **Sem classificação**, com zero pontos.
Percentuais sem base de comparação são apresentados como indisponíveis.

Essas metas e limites são uma proposta inicial de gestão e precisam ser homologados
com a empresa. A página e o PDF mostram os pontos e a explicação de cada critério.

## PDF e acesso

**Baixar PDF** refaz a consulta com os filtros da ficha exibida e gera um documento A4
com identidade da empresa, resumo, gráficos, classificação, evolução mensal e todas
as linhas das quatro listas, independentemente da página aberta na tela. O PDF não
é um snapshot financeiro persistido; contém a posição no instante da exportação.
Acima de 1.000 linhas somadas nas listas, a exportação é recusada com orientação
para reduzir o período, evitando truncamento silencioso.

As rotas autenticadas são `GET /api/resellers/profile` e
`GET /api/resellers/profile.pdf`, com `customer`, `from` e `to`. A consulta recebe
também `paidPage`, `returnsPage`, `debtsPage` e `cancellationsPage`.
A exportação registra auditoria com filtros, versão da classificação, pontuação,
instante da consulta e hash do conteúdo. Não exige migrations nem modifica comissões.

## Validação e imagens

Os testes cobrem filtros e agregações das consultas, valores manuais, pagamentos de
comissões criadas fora do período, exclusão de ATG/reprovadas, devoluções já abatidas,
paginação, classificação, autenticação, erros e auditoria do PDF. As consultas Oracle
foram verificadas com um adaptador SQLite; a execução no Winthor real e seus tempos
de resposta ainda precisam de homologação.

As imagens abaixo usam somente dados sintéticos:

- [Desktop](screenshots/reseller-profile-desktop.png).
- [Celular](screenshots/reseller-profile-mobile.png).
