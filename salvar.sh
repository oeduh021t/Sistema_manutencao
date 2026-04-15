#!/bin/bash

# Cores para o terminal ficar organizado
VERDE='\033[0;32m'
AZUL='\033[0;34m'
NC='\033[0m' # Sem cor

echo -e "${AZUL}--- Iniciando Automação de Backup HMDL ---${NC}"

# 1. Adiciona todas as mudanças (respeitando o .gitignore)
git add .

# 2. Pergunta qual foi a melhoria realizada
echo -e "${VERDE}O que você alterou hoje?${NC}"
read mensagem

# 3. Faz o commit com a sua mensagem
git commit -m "$mensagem"

# 4. Envia para o servidor local de arquivos (UCS)
echo -e "${AZUL}Enviando para o servidor UCS (.254)...${NC}"
git push origin-backup main

# 5. Envia para o GitHub
echo -e "${AZUL}Enviando para o GitHub...${NC}"
git push github main

echo -e "${VERDE}✅ Tudo pronto! Código atualizado localmente e na nuvem.${NC}"
