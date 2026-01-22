## LOGIN

**Scénario normal**
- L’utilisateur arrive sur `/suiviCommande/index.php`.
- Le PHP envoie au service : `LOGIN <identifiant> <mot_de_passe>`.
- Le service répond : `OK LOGIN_SUCESS`.

**Scénario erreur (login incorrect)**
- Même début. Réponse : `ERROR LOGIN_INCORRECT`.

**Scénario erreur (format incorrect)**
- Même début. Réponse : `ERROR LOGIN_FORMAT`.

**Scénario erreur (BDD)**
- Même début. Réponse : `ERROR DATABASE`.

## CREATE_BORDEREAU

**Scénario normal**
- L’utilisateur s'est login.
- Le PHP récupère `$_SESSION['id_commande']`.
- Le PHP envoie au service : `CREATE_BORDEREAU <id_commande>`.
- Le service répond : `LABEL=... ALREADY_EXISTS=0 ou 1 STATUS=1`.
- La page affiche une frise d’étapes 1→5, avec la 1ère marquée comme active.


**Scénario erreur (LOGIN)**
- L’utilisateur ne s'est pas login `LOGIN FIRST`.

**Scénario erreur (BDD)**
- L’utilisateur s'est login.
- Le PHP récupère `$_SESSION['id_commande']`.
- Le PHP envoie au service : `CREATE_BORDEREAU <id_commande>`.
- Réponse : `ERROR DATABASE`.

## STATUS


**Scénario normal**
- L’utilisateur a créé un bordereau.
- Le PHP envoie au service : `STATUS <id_bordereau>`.
- Le service répond : `OK STATUS=...`.

**Scénario erreur (LOGIN)**
- L’utilisateur ne s'est pas login `LOGIN FIRST`.

**Scénario erreur (aucune commande)**
- L’utilisateur a créé un bordereau.
- Une fonction du service cherche le bordereau dans la BDD . Réponse : `ERREUR, aucune commande trouvée`.

**Scénario erreur (BDD)**
- L’utilisateur s'est login.
- Le PHP récupère `$_SESSION['id_commande']`.
- Le PHP envoie au service : `CREATE_BORDEREAU <id_commande>`.
- Réponse : `ERROR DATABASE`.

## STATUS_UP

**Scénario normal**
- L’utilisateur s'est login.
- Une fonction du service cherche le bordereau dans la BDD .
- Un cron lance `STATUS_UP <id_bordereau>`.
- Le service augmente le status.
- La page affiche une frise d’étapes 1→5, avec une étape de plus cochée.

**Scénario erreur (LOGIN)**
- L’utilisateur ne s'est pas login `LOGIN FIRST`.

**Scénario erreur (aucune commande)**
- L’utilisateur s'est login.
- Une fonction du service cherche le bordereau dans la BDD . Réponse : `ERREUR, aucune commande trouvée`.


**Scénario erreur (BDD)**
- L’utilisateur s'est login.
- L’utilisateur s'est login.
- Une fonction du service cherche le bordereau dans la BDD . Réponse : `ERROR DATABASE`.