## LOGIN

**Scénario normal**
- L’utilisateur arrive sur `/suiviCommande/index.php`.
- Le PHP envoie au service : `LOGIN <identifiant> <mot_de_passe>`.
- Le service répond : `OK LOGIN_SUCESS`.

**Scénario erreur (login incorrect)**
- Même début. Réponse : `ERROR LOGIN_INCORRECT`.

**Scénario erreur (format incorrect)**
- Même début. Réponse : `ERROR LOGIN_FORMAT`.


## CREATE_BORDEREAU

**Scénario normal**
- L’utilisateur s'est login.
- Le PHP récupère `$_SESSION['id_commande']`.
- Le PHP envoie au service : `CREATE_BORDEREAU <id_commande>`.
- Le service répond : `LABEL=... ALREADY_EXISTS=0 ou 1 STATUS=1`.
- La page affiche une frise d’étapes 1→5, avec la 1ère marquée comme active.


## STATUS


**Scénario normal**
- L’utilisateur a créé un bordereau.
- Le PHP envoie au service : `STATUS <id_bordereau>`.
- Le service répond : `OK STATUS=...`.

**Scénario erreur (aucune commande)**
- Même début. Réponse : `ERREUR, aucune commande trouvée`.

## STATUS_UP

**Scénario normal**
- L’utilisateur s'est login.
- Un cron lance `STATUS_UP <id_bordereau>`.
- Le service augmente le status.
- La page affiche une frise d’étapes 1→5, avec une étape de plus cochée.