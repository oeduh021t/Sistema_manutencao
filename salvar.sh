#!/bin/bash

# Cores para o terminal ficar organizado
VERDE='\033[0;32m'
AZUL='\033[0;34m'
NC='\033[0m' # Sem cor

echo -e "${AZUL}--- Iniciando Automação de Backup (Git) ---${NC}"

# 1. Adiciona todas as mudanças
git add .

# 2. Pergunta qual foi a melhoria realizada
echo -e "${VERDE}O que você alterou hoje?${NC}"
read mensagem

# 3. Faz o commit com a sua mensagem
git commit -m "$mensagem"

# 4. Envia para o servidor de arquivos (UCS)
echo -e "${AZUL}Enviando para o servidor de arquivos (.254)...${NC}"
git push origin-backup main

echo -e "${VERDE}✅ Tudo pronto! Alteração salva e código protegido.${NC}"
