export type FaqQuestion = { question: string; answer: readonly string[] };
export type FaqTopic = {
  id: string;
  title: string;
  description: string;
  audience: string;
  href?: string;
  linkRole?: string;
  steps: readonly string[];
  questions: readonly FaqQuestion[];
};

export const faqTopics: readonly FaqTopic[] = [
  {
    id: "primeiros-passos",
    title: "Primeiros passos e acesso",
    audience: "Todos os usuários",
    description: "Conheça os perfis, encontre as telas e prepare sua primeira operação.",
    steps: [
      "Entre com o email e a senha fornecidos pelo administrador.",
      "Use o menu lateral para acessar as áreas do sistema. No celular, abra o menu pelo botão no cabeçalho.",
      "Confira o cliente principal e os documentos no Winthor antes de iniciar uma comissão.",
      "Siga o fluxo: selecionar pedidos, simular, registrar, aprovar e confirmar o pagamento.",
    ],
    questions: [
      {
        question: "O que cada perfil pode fazer?",
        answer: [
          "Operação importa pedidos, lança comissões e registra deduções. Financeiro aprova, reprova e confirma pagamentos. Auditoria acessa a trilha de eventos. Administrador configura a empresa, as regras e os usuários, além de ter acesso aos demais perfis.",
          "Um usuário pode ter mais de um perfil. Os botões e as áreas disponíveis acompanham suas permissões.",
        ],
      },
      {
        question: "Por que não vejo um botão ou uma opção do menu?",
        answer: [
          "Algumas ações exigem um perfil específico e outras dependem da etapa da comissão. Peça ao administrador para conferir suas permissões; em uma comissão paga, por exemplo, as ações de aprovação deixam de estar disponíveis.",
        ],
      },
      {
        question: "Esqueci minha senha. Como recuperar o acesso?",
        answer: [
          "Solicite ao administrador uma nova senha pelo cadastro de usuários. Esta versão não envia recuperação de senha por email. Se a sessão expirar, faça login novamente antes de repetir a operação.",
        ],
      },
      {
        question: "O GCOM altera pedidos ou baixa títulos no Winthor?",
        answer: [
          "Não. A integração com o Winthor é somente leitura. Comissões, aprovações, deduções e confirmações de pagamento são registradas no GCOM; os procedimentos financeiros no Winthor continuam separados.",
        ],
      },
    ],
  },
  {
    id: "visao-geral",
    title: "Visão geral",
    audience: "Todos os usuários",
    href: "/",
    description: "Acompanhe o movimento das comissões e os indicadores do painel.",
    steps: [
      "Abra Visão geral e confira os valores e a etapa a que cada indicador se refere.",
      "Observe a evolução mensal e o ranking de clientes.",
      "Use Comissões e Relatórios para localizar os documentos que compõem os valores.",
    ],
    questions: [
      {
        question: "O valor gerado é igual ao valor pago?",
        answer: [
          "Não necessariamente. A comissão pode estar pendente, aprovada, paga ou reprovada. Além disso, o pagamento pode ocorrer em outro mês ou ter um valor manual justificado. Confira o período, a situação e a data considerada antes de comparar totais.",
        ],
      },
      {
        question: "O painel mostra todo o faturamento do Winthor?",
        answer: [
          "O painel acompanha as comissões registradas no GCOM. Para analisar vendas agenciadas, débitos atuais e cancelamentos de um revenda, use a Ficha do Cliente Revenda.",
        ],
      },
    ],
  },
  {
    id: "pedidos",
    title: "Pedidos e importação",
    audience: "Consulta: todos · Importação: Operação",
    href: "/orders",
    description: "Localize pedidos importados e confira seus itens e dados de origem.",
    steps: [
      "Abra Pedidos e filtre pelo código do cliente para localizar registros já importados.",
      "Para importar um pedido individual, clique em Importar pedido e preencha os campos solicitados.",
      "Abra os detalhes e confira cliente, itens, quantidades e preços.",
      "Na criação de uma comissão, a simulação também importa os pedidos selecionados.",
    ],
    questions: [
      {
        question: "Importar um pedido já cria uma comissão?",
        answer: [
          "Não. A importação guarda os dados para consulta e cálculo. A comissão só é registrada depois da seleção dos pedidos, da simulação e da confirmação do lançamento.",
        ],
      },
      {
        question: "Por que um pedido não aparece na busca de nova comissão?",
        answer: [
          "Confira o cliente principal, a modalidade, a praça dos pedidos e as datas. O pedido precisa atender aos critérios de elegibilidade da modalidade. Pedidos já vinculados a uma comissão ficam fora da seleção; a reprovação libera os vínculos para um novo lançamento.",
        ],
      },
      {
        question: "Os dados de um pedido importado acompanham toda alteração no Winthor?",
        answer: [
          "A importação preserva uma cópia dos dados utilizados pelo GCOM. Ela não é uma sincronização permanente. Ao encontrar uma diferença, confira o momento da importação e os dados originais antes de lançar ou aprovar uma comissão; comissões já registradas mantêm sua memória de cálculo.",
        ],
      },
    ],
  },
  {
    id: "comissoes",
    title: "Criar e conferir comissões",
    audience: "Operação",
    href: "/commissions",
    description:
      "Do cliente principal à memória de cálculo: registre uma comissão com os documentos conferidos.",
    steps: [
      "Em Comissões, clique em Nova comissão e informe o cliente principal, a modalidade Normal ou ATG, a praça dos pedidos e o período.",
      "Clique em Buscar pedidos no Winthor. Confira filial, tabela/região, plano de pagamento e pareamento de preços antes de selecionar os pedidos.",
      "Clique em Simular comissão. A tela leva você até o cálculo quando a simulação termina. Na Conferência no Winthor, selecione as devoluções que deseja abater e simule novamente.",
      "Confira venda, referência, frete, comissão bruta, deduções e líquido. Registre a comissão quando os valores e os documentos estiverem corretos.",
      "Abra a comissão registrada para acompanhar sua aprovação, pagamento e histórico.",
    ],
    questions: [
      {
        question: "Qual código de cliente devo informar?",
        answer: [
          "Informe o código do cliente principal, que recebe a comissão. Na modalidade Normal, os compradores precisam estar vinculados a ele pelo CODREVENDA. Não use o código do comprador vinculado como se fosse o beneficiário principal.",
        ],
      },
      {
        question: "Qual a diferença entre Normal e ATG?",
        answer: [
          "Normal corresponde às vendas agenciadas de clientes vinculados, em praças PSCF. ATG é o autoagenciamento, utilizado nas praças PSD e podendo incluir vendas próprias do cliente sem revenda. As modalidades usam referências e percentuais definidos na regra administrativa.",
        ],
      },
      {
        question: "Posso reunir vários pedidos na mesma comissão?",
        answer: [
          "Sim, desde que pertençam ao mesmo cliente principal e atendam aos filtros e critérios da operação. Confira os pedidos selecionados na simulação; não misture beneficiários diferentes.",
        ],
      },
      {
        question: "Como são calculados o bruto e o líquido?",
        answer: [
          "O bruto usa a base e o percentual da regra vigente no lançamento. Na base de margem, considera a diferença entre venda e referência, com frete quando configurado. O líquido é o bruto menos as deduções aplicadas.",
          "A memória por item e por pedido mostra as bases utilizadas. O operador não deve substituir o resultado calculado por um valor digitado.",
        ],
      },
      {
        question: "Por que o sistema bloqueia um pedido ou o cálculo?",
        answer: [
          "Pode faltar um pareamento PSD/PSCF, um plano válido ou preços completos preservados. Uma base final sem valor positivo também impede o lançamento. Leia a mensagem, confira os dados de origem e solicite revisão da configuração ao administrador; não troque de tabela apenas para contornar o bloqueio.",
        ],
      },
      {
        question: "O que fazer se um preço divergir da rotina 316?",
        answer: [
          "Confira o mesmo pedido e item, distinguindo preço efetivo de venda, preço de tabela e referência da comissão. Separe também a data da importação de alterações posteriores no Winthor.",
          "Informe ao responsável o pedido, produto e valores divergentes antes da aprovação. Não aplique novamente um desconto por conta própria: ele pode já estar incorporado no preço. A reprodução completa das políticas de desconto da rotina 561 ainda não faz parte desta versão.",
        ],
      },
    ],
  },
  {
    id: "aprovacao-pagamento",
    title: "Aprovação e pagamento",
    audience: "Financeiro",
    href: "/commissions",
    description: "Confira o lançamento, aprove e registre o pagamento efetivamente realizado.",
    steps: [
      "Abra uma comissão Aguardando aprovação e confira pedidos, memória de cálculo, deduções e justificativas.",
      "Aprove quando a conferência estiver correta. Se houver erro, use Reprovar comissão e informe a justificativa.",
      "Em uma comissão aprovada, confirme o valor e a data do pagamento realizado. Se o valor for diferente do líquido, use a opção manual e justifique.",
      "Informe o RECNUM da rotina 749 quando disponível. É possível registrar o pagamento e vincular o lançamento depois.",
      "Confira o status Paga e o comprovante da comissão.",
    ],
    questions: [
      {
        question: "Quais são as etapas de uma comissão?",
        answer: [
          "Aguardando aprovação: lançamento registrado para conferência. Pronta para pagamento: aprovado, mas ainda sem confirmação de pagamento. Paga: pagamento confirmado no GCOM. Reprovada: lançamento recusado com justificativa e histórico preservado.",
        ],
      },
      {
        question: "Uma comissão criada pelo Financeiro já fica aprovada?",
        answer: [
          "Não. A etapa de aprovação continua necessária, inclusive quando o usuário Financeiro criou a comissão.",
        ],
      },
      {
        question: "Posso reprovar uma comissão paga?",
        answer: [
          "Não. A reprovação está disponível enquanto a comissão estiver pendente ou aprovada sem pagamento. Ela preserva o histórico e libera pedidos e deduções para um novo lançamento.",
        ],
      },
      {
        question: "Posso pagar um valor diferente do líquido calculado?",
        answer: [
          "Sim, pela opção de valor manual, com justificativa obrigatória. O sistema preserva o líquido calculado e o valor efetivamente confirmado. A diferença não cria saldo, parcelamento ou pagamento parcial automaticamente.",
        ],
      },
      {
        question: "O que é RECNUM e como ele se relaciona com o NUMTRANS?",
        answer: [
          "RECNUM identifica a linha do lançamento financeiro associada à rotina 749. NUMTRANS é uma informação desse lançamento, não um substituto do RECNUM. Confira os detalhes antes de vincular; um RECNUM só pode ficar associado a uma comissão.",
          "Localizar ou vincular um lançamento não comprova sozinho a conciliação do valor, do beneficiário ou a baixa financeira. A confirmação no GCOM também não executa uma baixa no Winthor.",
        ],
      },
    ],
  },
  {
    id: "deducoes",
    title: "Débitos, devoluções e cancelamentos",
    audience: "Consulta: todos · Cadastro: Operação",
    href: "/adjustments",
    description: "Entenda o que está pendente e o que já foi abatido em uma comissão.",
    steps: [
      "Abra Débitos e devoluções para conferir referência, justificativa, valor e situação das deduções.",
      "Para registrar uma dedução manual, clique em Nova dedução e informe o cliente principal, tipo, referência de origem, valor e justificativa.",
      "Na simulação da comissão, confira os débitos registrados e cancelamentos aplicados automaticamente. Selecione as devoluções na Conferência no Winthor.",
      "Use Relatórios para localizar deduções pendentes ou já deduzidas e a comissão em que foram aplicadas.",
    ],
    questions: [
      {
        question: "Todo título vencido no Winthor vira um desconto na comissão?",
        answer: [
          "Não. Os títulos vencidos aparecem para conferência, mas não são descontados diretamente apenas por estarem em atraso. Débitos registrados no GCOM são deduções; títulos da carteira são informações de cobrança. Evite confundir as duas situações.",
        ],
      },
      {
        question: "As devoluções são todas descontadas automaticamente?",
        answer: [
          "Na conferência de uma nova comissão, você escolhe as devoluções a aplicar e simula novamente. Só as selecionadas são importadas nessa etapa; as demais continuam disponíveis. Devoluções já registradas como deduções pendentes seguem o fluxo de abatimento do GCOM.",
        ],
      },
      {
        question: "O que acontece com um pedido comissionado que é cancelado?",
        answer: [
          "Cancelamentos integrais de pedidos já comissionados geram uma dedução na próxima comissão. Cancelamentos parciais exigem conferência. A comissão anterior mantém sua memória e seu histórico.",
        ],
      },
      {
        question: "O valor da mercadoria devolvida é igual ao abatimento?",
        answer: [
          "Não necessariamente. Um é o valor da mercadoria; o outro é a dedução de comissão calculada conforme a regra da devolução. Confira a modalidade, o percentual e a referência antes de comparar os valores.",
        ],
      },
    ],
  },
  {
    id: "revenda",
    title: "Ficha do Cliente Revenda",
    audience: "Todos os usuários",
    href: "/resellers",
    description: "Veja comissões, vendas agenciadas, qualidade da carteira e a ficha em PDF.",
    steps: [
      "Informe o código do cliente principal e o período, de até 366 dias, e clique em Consultar ficha.",
      "Confira o resumo financeiro, os gráficos mensais, os clientes com maior volume e a composição da classificação.",
      "Consulte as listas de comissões pagas, devoluções abatidas, débitos e cancelamentos. Cada lista tem paginação independente.",
      "Use Exportar ficha PDF para gerar o documento completo com os filtros da ficha exibida.",
    ],
    questions: [
      {
        question: "Como funciona a classificação Ouro, Prata e Bronze?",
        answer: [
          "A classificação gerencial soma até 100 pontos: vendas agenciadas valem 40, pontualidade da carteira 30, cancelamentos 15 e devoluções 15. Ouro começa em 80 pontos e Prata em 55; abaixo disso, Bronze.",
          "A meta de volume é proporcional a 60 pedidos por ano. Nos critérios de qualidade, a penalidade aumenta até zerar os pontos quando a razão chega a 20%. Histórico reduzido ou atraso de pelo menos 60 dias limita a Bronze; sem vendas faturadas, não há classificação. A tela explica cada critério. O rating não altera nem bloqueia comissões.",
        ],
      },
      {
        question: "Os débitos também são filtrados pelo período da ficha?",
        answer: [
          "Não. Os débitos mostram a carteira atual, incluindo títulos a vencer, do próprio revenda e dos clientes atualmente vinculados. Vendas, devoluções e cancelamentos usam suas datas específicas dentro do período. A ficha informa essas diferenças de base.",
        ],
      },
      {
        question: "A ficha inclui comissões ATG?",
        answer: [
          "Não. A ficha acompanha vendas agenciadas e comissões da modalidade Normal. Vendas próprias, comissões ATG e comissões reprovadas ficam fora desses indicadores. Para consultar ATG, use Comissões e Relatórios.",
        ],
      },
      {
        question: "O PDF exporta somente a página aberta das listas?",
        answer: [
          "Não. Ele consulta novamente os filtros e inclui todas as linhas das listas, até o limite combinado de 1.000 registros. Acima disso, a exportação é recusada, sem cortar dados silenciosamente. A posição pode mudar entre a consulta na tela e a exportação; confira o instante informado no PDF.",
        ],
      },
    ],
  },
  {
    id: "winthor",
    title: "Consultas Winthor",
    audience: "Todos os usuários",
    href: "/winthor",
    description: "Confira informações de origem antes de tomar uma decisão na comissão.",
    steps: [
      "Abra Consultas Winthor e escolha o assunto: pedidos, devoluções, cancelamentos ou títulos em atraso.",
      "Preencha os filtros apresentados, incluindo o código do cliente principal quando solicitado.",
      "Execute a consulta e confira documentos, datas, clientes e valores.",
      "Use a criação de comissão para selecionar pedidos e devoluções; a consulta isolada não registra uma comissão.",
    ],
    questions: [
      {
        question: "Consultar uma devolução já significa abatê-la?",
        answer: [
          "Não. A consulta mostra informações do Winthor. O abatimento depende da seleção na simulação ou do registro de uma dedução e de seu vínculo com uma comissão.",
        ],
      },
      {
        question: "Por que a carteira consultada hoje difere de uma comissão antiga?",
        answer: [
          "A consulta mostra a situação atual da origem, enquanto a comissão preserva os dados de seu lançamento. Pagamentos, cancelamentos, vínculos de clientes e outras informações podem ter mudado desde então.",
        ],
      },
      {
        question: "O que fazer quando o Winthor está indisponível?",
        answer: [
          "Anote a mensagem e o identificador da requisição, caso seja exibido, e acione o responsável pelo ambiente. A indisponibilidade não significa que o cliente está sem pedidos ou débitos. Aguarde a conexão voltar antes de repetir uma operação que depende dessa consulta.",
        ],
      },
    ],
  },
  {
    id: "relatorios",
    title: "Relatórios e histórico",
    audience: "Todos os usuários",
    href: "/reports",
    description: "Filtre comissões e deduções, exporte os resultados e consulte relatórios salvos.",
    steps: [
      "Escolha Comissões e pagamentos ou Débitos, devoluções e cancelamentos.",
      "Informe o período e a data considerada. Refine por cliente, status, pedido, modalidade ou situação do abatimento conforme o relatório.",
      "Consulte os resultados e confira os filtros antes de exportar em PDF, XLSX ou CSV.",
      "Use o histórico para abrir relatórios salvos e conferir os dados e filtros preservados.",
    ],
    questions: [
      {
        question: "Qual data devo usar para conferir pagamentos?",
        answer: [
          "Selecione Data do pagamento. Uma comissão criada em um mês e paga em outro entra no período do pagamento nessa consulta. Para acompanhar lançamentos, use Data do cadastro; para deduções aplicadas, use Data do abatimento na comissão.",
        ],
      },
      {
        question: "Como localizar uma devolução que já foi abatida?",
        answer: [
          "Escolha o relatório de deduções, o tipo Devolução e a situação Já deduzido. Confira o cliente, a referência e a comissão vinculada. Para verificar o momento do desconto, use a data do abatimento.",
        ],
      },
      {
        question: "Por que um relatório salvo difere de uma consulta nova?",
        answer: [
          "O relatório salvo preserva os dados do momento em que foi gerado. Uma consulta nova pode refletir pagamentos, aprovações ou deduções posteriores. Compare os filtros e os instantes de geração antes de concluir que há uma divergência.",
        ],
      },
      {
        question: "O que fazer se o PDF atingir o limite de registros?",
        answer: [
          "O PDF de comissões aceita até 1.000 comissões por arquivo. Reduza o período ou refine os filtros e gere arquivos separados. Para trabalhar com os dados em planilha, use XLSX ou CSV conforme as opções da tela.",
        ],
      },
    ],
  },
  {
    id: "auditoria",
    title: "Auditoria",
    audience: "Auditoria e Administrador",
    href: "/audit",
    linkRole: "ROLE_AUDITOR",
    description: "Identifique quem realizou uma ação e confira seu contexto e histórico.",
    steps: [
      "Abra Auditoria com um perfil autorizado.",
      "Use os filtros disponíveis para localizar a ação investigada.",
      "Abra os detalhes do evento e confira responsável, data, assunto e informações registradas.",
      "Na comissão, consulte também o histórico para acompanhar sua trajetória.",
    ],
    questions: [
      {
        question: "Para que serve a auditoria?",
        answer: [
          "Ela permite rastrear operações como lançamentos, aprovações, pagamentos, alterações de configuração e exportações. Use os registros para conferir a sequência de ações e o contexto preservado.",
        ],
      },
      {
        question: "Uma reprovação apaga a comissão e sua auditoria?",
        answer: [
          "Não. A comissão reprovada mantém seu histórico e a justificativa. A liberação dos pedidos e deduções para um novo lançamento não elimina a rastreabilidade do lançamento anterior.",
        ],
      },
    ],
  },
  {
    id: "calculo",
    title: "Configuração do cálculo",
    audience: "Administrador",
    href: "/settings",
    linkRole: "ROLE_ADMIN",
    description: "Configure percentuais, bases e referências com uma regra versionada.",
    steps: [
      "Abra Cálculo da comissão e confira a regra em uso.",
      "Revise bases, percentuais Normal/ATG/devoluções, pareamentos PSD/PSCF, frete e desconto na referência.",
      "Valide a regra com exemplos conhecidos da empresa antes de publicá-la.",
      "Clique em Publicar nova regra. Confira a simulação dos próximos lançamentos com a configuração publicada.",
    ],
    questions: [
      {
        question: "Mudar a regra recalcula as comissões anteriores?",
        answer: [
          "Não. Novas configurações valem para novos lançamentos. Comissões existentes preservam a versão da regra, os preços e a memória de cálculo utilizados.",
        ],
      },
      {
        question: "O que é o pareamento PSD/PSCF?",
        answer: [
          "É a relação entre a tabela de referência PSD e a tabela PSCF no contexto de filial/região configurado para a operação. O plano de pagamento também participa da escolha da coluna de preço. Confira o contexto de cada pedido; não presuma que qualquer tabela atende à mesma operação.",
        ],
      },
      {
        question: "Devo habilitar desconto na referência para corrigir qualquer divergência?",
        answer: [
          "Não. A opção aplica o percentual preservado do item à referência conforme a regra. Ela não reproduz todas as políticas da rotina 561. Confirme a regra comercial e a origem da diferença antes de alterar a configuração.",
        ],
      },
    ],
  },
  {
    id: "empresa",
    title: "Empresa e logos",
    audience: "Administrador",
    href: "/settings/enterprise",
    linkRole: "ROLE_ADMIN",
    description: "Mantenha a identificação da empresa, o prefixo das comissões e sua logo.",
    steps: [
      "Abra Empresa e preencha razão social, nome fantasia, CNPJ, contatos, endereço e prefixo das comissões.",
      "Clique em Salvar empresa antes de enviar a logo.",
      "Na seção Logos, escolha uma imagem PNG ou JPEG, confira a prévia e clique em Enviar logo da empresa.",
      "Use Remover logo da empresa para retirar a imagem, ou envie outra para substituí-la. A logo GCOM permanece como identificação do sistema.",
    ],
    questions: [
      {
        question: "Alterar o prefixo muda os códigos das comissões existentes?",
        answer: [
          "Não. O prefixo cadastrado vale para novas comissões. Códigos e registros anteriores permanecem preservados.",
        ],
      },
      {
        question: "Quais imagens posso enviar como logo?",
        answer: [
          "PNG ou JPEG de até 1 MB, com dimensões máximas de 4096 × 4096 pixels. A prévia ajuda a conferir a escolha. A logo da empresa aparece junto da GCOM no menu e nos PDFs.",
        ],
      },
      {
        question: "Por que a empresa aparece como GCOM?",
        answer: [
          "GCOM é a identificação inicial enquanto os dados da empresa ainda não foram cadastrados. O administrador deve salvar o nome fantasia e os demais dados para personalizar o ambiente.",
        ],
      },
    ],
  },
  {
    id: "usuarios",
    title: "Usuários e permissões",
    audience: "Administrador",
    href: "/users",
    linkRole: "ROLE_ADMIN",
    description: "Cadastre pessoas, atribua perfis e controle o acesso ao ambiente.",
    steps: [
      "Abra Usuários e use Novo usuário para cadastrar nome, email, senha e perfis.",
      "Atribua os perfis necessários para a função da pessoa.",
      "Use a edição do cadastro para atualizar dados, permissões ou senha.",
      "Desative o acesso de quem não deve mais utilizar o sistema, preservando seu histórico.",
    ],
    questions: [
      {
        question: "Posso atribuir mais de um perfil?",
        answer: [
          "Sim. Por exemplo, uma pessoa que lança e confere comissões pode ter Operação e Financeiro. Administrador tem acesso aos demais perfis; Auditoria é o perfil para consultar a trilha de eventos.",
        ],
      },
      {
        question: "Desativar um usuário apaga suas operações?",
        answer: [
          "Não. A desativação impede o acesso e mantém as referências e os registros históricos. O usuário precisa estar ativo para continuar usando a aplicação.",
        ],
      },
    ],
  },
  {
    id: "problemas",
    title: "Dúvidas e problemas comuns",
    audience: "Todos os usuários",
    description: "Saiba o que conferir e quais informações informar ao pedir ajuda.",
    steps: [
      "Leia a mensagem apresentada e confira os filtros, datas e permissões da operação.",
      "Anote a tela, o horário, o pedido ou a comissão envolvidos e o identificador da requisição, quando disponível.",
      "Envie essas informações ao responsável, sem compartilhar senha ou token de acesso.",
      "Antes de repetir um lançamento ou pagamento após um erro, confira se ele já aparece registrado.",
    ],
    questions: [
      {
        question: "A tela não trouxe resultados. Isso significa que não existem registros?",
        answer: [
          "Primeiro confira cliente, período, modalidade, status e base de data. Uma busca sem resultados é diferente de uma mensagem de falha na consulta. Em caso de erro, não interprete a ausência de dados como ausência de dívida ou de movimento.",
        ],
      },
      {
        question: "Apareceu Sessão inválida ou Sessão expirada. O que fazer?",
        answer: [
          "Atualize a página e faça login novamente se solicitado. Confira o estado da operação antes de reenviar. Se o problema continuar, informe o horário e a tela ao responsável pelo ambiente.",
        ],
      },
      {
        question: "O que informar quando aparece Falha interna?",
        answer: [
          "Informe o identificador da requisição apresentado na mensagem, o horário e a ação realizada. Inclua o número do pedido ou da comissão, quando houver. O responsável usará essas informações para localizar o erro; não tente compensar uma falha técnica alterando valores financeiros.",
        ],
      },
    ],
  },
];

export function normalizeFaqText(value: string): string {
  return value
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLocaleLowerCase("pt-BR")
    .trim();
}
