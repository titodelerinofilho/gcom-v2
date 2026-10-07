# Homologação e migração do legado

A nova versão não consulta o MySQL antigo em runtime. Não execute alteração no legado
antes de validar a nova aplicação com cópias e exemplos reais.

## Comparação de regras

1. Separe pedidos normais, PSD, ATG e combos, com/sem frete, desconto e devolução.
2. Compare cliente, filial, datas, todos os itens e totais importados do Winthor.
3. Reconstrua a memória de cálculo do legado e compare comissão bruta, deduções e líquido.
4. Homologue com o financeiro a regra configurada: percentuais normal/ATG/devolução, referências e pareamentos por filial/tabela/plano,
   frete antes da margem e desconto. A versão `configured-v2` preserva regra, contexto, comparação por item e rateio por pedido;
   comissões antigas permanecem inalteradas. Combos usam QTMP por unidade, conforme confirmação
   do usuário; valide a composição ativa por filial/tabela contra exemplos reais.
5. Para a 749, confira PCLANC/RECNUM e os campos de valor do registro; NUMTRANS pode ser exibido como detalhe,
   beneficiário, documento, vencimento e baixa. Valide o caso de múltiplos registros
   e confirme que cada RECNUM será associado a uma única comissão.

## Dados históricos

É necessário obter o schema e uma exportação protegida de `usuarios`, `comissoes`,
`debitos`, `devolucoes` e `logs` do MySQL para construir um importador confiável.
O código legado não contém migrations/schema suficientes para presumir esse contrato.
Mapeie IDs antigos para novos e mantenha um identificador de origem para evitar duplicações.
Registros históricos não devem produzir pagamentos ou baixas novamente.

Senhas antigas usam MD5; não reutilize esse formato no novo sistema. Planeje uma troca
de senha dos usuários. Snapshots capturados hoje não reproduzem automaticamente o estado
histórico de um pedido: marque claramente a data e origem quando importar registros antigos.

## Corte operacional

- Faça homologação com usuários de operação, financeiro e auditoria.
- Restrinja a edição no legado, exporte o último delta e confira contagens/totais.
- Faça um backup da nova base e valide restore em outra base.
- Libere acesso pela rede interna, monitore logs e registre o responsável pelo corte.
- Mantenha o legado acessível para consulta durante o período definido pela empresa.

Sugestões para próximas entregas: homologação das composições e preços no Oracle real, aprovação com
alçadas por valor, estorno auditado, recuperação de senha administrativa, anexos de
comprovantes, conciliação automática da 749 e alertas de backup com envio configurado.
