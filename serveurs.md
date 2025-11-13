# 🖥️ Guide de Configuration Serveur

> Documentation complète pour la configuration et la gestion des serveurs Docker

---

## 🚀 Configuration Initiale

### 🔐 Lancer FortiClient

Assurez-vous que **FortiClient** est actif avant de commencer la configuration.

### 👤 Passer en mode root

su -

**Mot de passe root :** `kira13`

---

### 📁 Se positionner dans le répertoire Docker

cd /docker

---

### 🌐 Configurer le proxy

source proxy_mox

**Identifiants proxy :**

| Paramètre | Valeur |
|-----------|--------|
| Login | `sae301_a11` |
| Mot de passe | `rneks5otPb(l` |

---

### 🐳 Démarrer les conteneurs Docker

docker compose start

---

### ✅ Vérifier la configuration

**Variables d'environnement :**

env

**Test de connectivité :**

wget http://www.google.com

---

## 🔄 Mise à jour du code

### 📂 Accéder au répertoire web

cd /docker/data/web

> Ce répertoire contient les fichiers web de votre application

---

### 🔃 Récupérer les dernières modifications

git pull

---

### 📋 Commandes utiles

**Consulter la configuration Docker :**

cat docker-compose.yml

---

## 🔗 Accès rapides

### 🌐 Interface Web

http://10.253.5.101/index.php


### 🔌 Connexion SSH

ssh sae@10.253.5.101