Le protocole sert a permmettre a un client de la marketplace de suivre l'avancé de sa commande et de valider
ou non la récéption du colis .

Données d’entrée / sortie :

--------------- LOGIN --------------- 

Entrées coté client : 

identifiant, mot de passe

Requete envoyé au service : 

LOGIN identifiant mot_de_passe

Réponses possibles :

ERROR LOGIN_FORMAT
ERROR LOGIN_INCORRECT
OK LOGIN_SUCESS

Scénario normal :

--------------- STATUS --------------- 

Entrées coté client : 

id_bordereau

Requete envoyé au service : 

STATUS id_bordereau

Réponses possibles :

OK STATUS=... 
ERREUR, aucune commande trouvé

Scénario normal :

--------------- CREATE_BORDEREAU --------------- 

Entrées coté client : 

id_commande

Requete envoyé au service : 

CREATE_BORDEREAU id_commande

Réponses possibles :



Scénario normal :

--------------- CREATE_BORDEREAU --------------- 

Entrées coté client : 



Requete envoyé au service : 



Réponses possibles :


Scénario normal :


