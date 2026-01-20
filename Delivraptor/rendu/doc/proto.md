
Protocole de suivi de commande

Ce protocole permet à un client de la marketplace de suivre l'avancée de sa commande et de valider ou non la réception du colis.

---

## LOGIN

**Entrées côté client**
- identifiant
- mot de passe

**Requête envoyée au service**
```
LOGIN <identifiant> <mot_de_passe>
```

**Réponses possibles**
| Réponse                | Signification                |
|------------------------|------------------------------|
| ERROR LOGIN_FORMAT     | Format de login incorrect    |
| ERROR LOGIN_INCORRECT  | Identifiants invalides       |
| OK LOGIN_SUCESS        | Connexion réussie            |


---

## STATUS

**Entrées côté client**
- id_bordereau

**Requête envoyée au service**
```
STATUS <id_bordereau>
```

**Réponses possibles**
| Réponse                            | Signification                |
|------------------------------------|------------------------------|
| OK STATUS=X                        | Statut de la commande        |
| ERREUR                             | Aucune commande trouvée      |

*X étant un entier naturel compris entre 0 et 5.*


---

## CREATE_BORDEREAU

**Entrées côté client**
- id_commande

**Requête envoyée au service**
```
CREATE_BORDEREAU <id_commande>
```

**Réponses possibles**
| Réponse                                              | Signification                      |
|------------------------------------------------------|------------------------------------|
| LABEL=X           ALREADY_EXISTS=Y        STATUS=1   | Bordereau créé ou déjà existant    |

*X étant un entier naturel.*
*Y étant un entier naturel compris entre 0 et 1.*

---

## STATUS_UP

**Entrées côté client**
- id_bordereau

**Requête envoyée au service**
```
STATUS_UP <id_bordereau>
```

**Réponses possibles**
| Réponse                        | Signification                                 |
|--------------------------------|-----------------------------------------------|
| LIVRE: Livré en l'absence      | Livré sans destinataire + image               |
| LIVRE: Livré en main propre    | Livré en main propre                          |


