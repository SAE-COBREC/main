#!/bin/bash
# =============================================================
#  Script d'initialisation du serveur SAE 3.04
# =============================================================

set -e

echo "========================================"
echo "  Init serveur SAE 3.04"
echo "========================================"

# ------------------------------------------------------------
# 1. Charger la configuration proxy
# ------------------------------------------------------------
echo ""
echo ">> Chargement de la configuration proxy..."

if [ -f proxy_mox ]; then
    source proxy_mox
else
    echo "[WARN] Fichier proxy_mox introuvable, configuration proxy ignorée."
fi

# Identifiants du proxy
export PROXY_LOGIN="sae301_a11"
export PROXY_PASSWORD="rneks5otPb(l"

# Si le proxy nécessite une authentification dans l'URL
export http_proxy="http://${PROXY_LOGIN}:${PROXY_PASSWORD}@${http_proxy#http://}"
export https_proxy="http://${PROXY_LOGIN}:${PROXY_PASSWORD}@${https_proxy#http://}"

echo "[OK] Proxy configuré (login: $PROXY_LOGIN)"

# ------------------------------------------------------------
# 2. Démarrer les conteneurs Docker
# ------------------------------------------------------------
echo ""
echo ">> Démarrage des conteneurs Docker..."
docker compose start
echo "[OK] Conteneurs Docker démarrés."

# ------------------------------------------------------------
# 3. Vérifications
# ------------------------------------------------------------
echo ""
echo ">> Affichage des variables d'environnement :"
env

echo ""
echo ">> Test de connectivité (wget http://www.google.com)..."
wget --quiet --spider http://www.google.com && echo "[OK] Connectivité OK." || echo "[WARN] Impossible de joindre www.google.com."

# ------------------------------------------------------------
# 4. Mise à jour du code
# ------------------------------------------------------------
echo ""
echo ">> Mise à jour du code depuis le dépôt Git..."

if [ -d /docker/data/web ]; then
    cd /docker/data/web
    echo "[OK] Dossier /docker/data/web trouvé."
    git pull
    echo "[OK] Code mis à jour."
else
    echo "[WARN] Dossier /docker/data/web introuvable, git pull ignoré."
fi

# ------------------------------------------------------------
echo ""
echo "========================================"
echo "  Initialisation terminée !"
echo "========================================"
