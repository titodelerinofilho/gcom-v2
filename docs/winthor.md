# Rotina 749 · PCLANC

O vínculo da rotina 749 usa `RECNUM`, chave primária de `PCLANC`. `NUMTRANS` aparece como detalhe do lançamento, mas não é usado para localizar o vínculo.
O vínculo é opcional e pode ocorrer depois da confirmação do pagamento.

A consulta parametrizada fica em `src/Repository/Winthor/PclancRepository.php`, usando
`Database/Statement/Statement` e `Transaction` em transação Oracle somente leitura. Retorna o registro de PCLANC com o
RECNUM informado. O serviço valida a chave e preserva o
registro em JSON no PostgreSQL. As credenciais devem permitir somente leitura.

`WINTHOR_749_LOOKUP_ENABLED=1` habilita a consulta; `0` mantém apenas referência manual,
com indicação explícita na interface. Alterações no SQL são mudanças de código do
repository, revisadas e testadas antes de recompilar a imagem.

`winthor_lookup` significa que o lançamento foi localizado, não que valor, beneficiário
ou baixa foram conciliados automaticamente. Valide o schema e as permissões com o DBA.

Repositories não instanciam nem usam PDO diretamente. A camada `Database` materializa
os resultados e despacha `DatabaseQueryEvent`; o listener em `EventListener/Logs` grava
o log estruturado sem credenciais nem valores de parâmetros.

## Somente leitura no Winthor

Os repositories Oracle usam exclusivamente SELECT. Transações de importação e PCLANC executam SET TRANSACTION READ ONLY, seguido de COMMIT/ROLLBACK para encerrar a leitura. Não há INSERT, UPDATE, DELETE, MERGE, DDL nem chamada a rotina de lançamento no Winthor. O vínculo 749 apenas lê PCLANC. Cadastros, baixas, snapshots, usuários e auditoria são gravados no PostgreSQL do GCOM. Configure o usuário Oracle com permissões somente de leitura para reforçar isso no banco.
