🛠️ Sistema de Gestão de Manutenção - HMDL
Sistema desenvolvido para centralizar e otimizar o controle de chamados técnicos, inventário de equipamentos e gestão de infraestrutura hospitalar. Focado em agilidade, o sistema permite a abertura de chamados via QR Code e integração em tempo real com notificações via Telegram.

🚀 Funcionalidades Principais
Gestão de Chamados: Fluxo completo de abertura, atendimento e conclusão de ordens de serviço.

Cronologia de Atendimento: Registro detalhado de cada etapa do processo (logs), incluindo suporte a múltiplas evidências fotográficas.

Abertura via QR Code: Facilidade para o solicitante abrir chamados diretamente do local/equipamento.

Notificações Inteligentes: Integração com bot do Telegram para alertas imediatos de novos chamados para a equipe técnica.

Inventário de Equipamentos: Controle de patrimônio vinculado aos setores do hospital.

Automação de Backup: Script customizado para sincronização simultânea em servidor local (UCS) e nuvem (GitHub).

💻 Tecnologias Utilizadas
Backend: PHP 8.x

Banco de Dados: MySQL / MariaDB

Frontend: Bootstrap 5, Bi-Icons

Integrações: API do Telegram (Bot)

Infraestrutura: Servidor Linux (Ubuntu), Apache2

Versionamento: Git

📦 Estrutura de Arquivos Relevante
chamados.php: Interface principal e lógica de abertura de novas solicitações.

tratar_chamado.php: Painel técnico para execução de manutenções e registros fotográficos.

salvar.sh: Script de automação para salvamento e deploy do código.

includes/db.php: Configuração de conexão com o banco de dados.

🛠️ Como Contribuir/Salvar Alterações
O projeto utiliza um fluxo de automação simplificado via terminal:

Realize as alterações no código.

Execute o comando: ./salvar.sh.

Insira a descrição da melhoria realizada.

O script enviará automaticamente os dados para o servidor de arquivos local e para o GitHub.
