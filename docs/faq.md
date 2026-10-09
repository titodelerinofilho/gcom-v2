# FAQ e guia de uso

A tela `/faq`, acessível pelo menu **FAQ e ajuda**, reúne o guia dos processos do
GCOM em 14 tópicos e 48 perguntas. Cada tópico informa o público, o passo a passo
e as dúvidas frequentes. O fluxo inicial apresenta seleção, conferência, aprovação
e pagamento de uma comissão.

A busca considera perguntas, respostas e passos, ignorando acentos e diferenças
entre maiúsculas e minúsculas. Todos os termos digitados precisam aparecer no
conteúdo pesquisado. Respostas dos resultados são abertas para facilitar a leitura;
sem busca, podem ser abertas pelo teclado ou clique. O filtro por tópico fica em
uma navegação lateral no desktop e em um seletor no celular.

Todos os usuários autenticados podem ler a ajuda. Atalhos para Empresa, Usuários,
Cálculo e Auditoria respeitam o perfil do usuário. A página não concede permissões
nem exige novas consultas de negócio ou migrations.

O conteúdo tipado está em `frontend/src/lib/faq.ts`; a apresentação em
`frontend/src/components/faq.tsx`. Ao alterar um fluxo, atualize o tópico correspondente
para descrever somente as funcionalidades disponíveis. Contagens são calculadas
pelo componente a partir do conteúdo.

Validação em navegador: abertura de respostas, busca por múltiplos termos sem
acentos, busca por RECNUM com caixa mista, filtro, busca sem resultados, limpeza,
atalhos por perfil e ausência de overflow horizontal em celular.

Imagens com dados sintéticos: [desktop](screenshots/faq-desktop.png) e
[celular](screenshots/faq-mobile.png).
