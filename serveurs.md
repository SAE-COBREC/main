## Configuration initiale

### Lancer FortiClient

Assurez-vous que FortiClient est actif avant de commencer.

### Passer en mode root

```bash
su -
```

Entrez le mot de passe root `kira13`

### Se positionner dans le répertoire Docker

```bash
cd /docker
```

### Configurer le proxy

```bash
source proxy_mox
```

Lorsque demandé, entrez vos identifiants :
- Login : `sae301_a11`
- Mot de passe : `rneks5otPb(l`

### Démarrer les conteneurs Docker

```bash
docker compose start
```

### Vérifier les variables d'environnement

```bash
env
```

```bash
wget http://www.google.com
```

## Mise à jour du code

### Accéder au répertoire web

```bash
cd /docker/data/web
```

Ce répertoire contient les fichiers web de votre application.

### Récupérer les dernières modifications

```bash
git pull
```


### cat docker-compose.yml 