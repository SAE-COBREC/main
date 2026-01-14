#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <unistd.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <postgresql/libpq-fe.h>
#include <stdbool.h>

#define PORT 9000
#define BUFFER_SIZE 256
#define TAILLE_CHAINE_MAX 12
#define FICHIER_COMMANDES "commandes.txt"
#define FICHIER_SCRIPT "script.bash"

int cherche_si_commande_exist(PGconn *conn, int id_commande)
{
    /*fonction qui permet de regarder si une commande existe pour l'id d'une commande passé en argument
    elle renvoie :
    1 si la commande existe
    0 si rien n'est trouvé
    -1 en cas d'erreur

    elle prend en paramètre:
    la connexion
    l'id de la commande a rechercher pour savoir si elle existe
    */
    char id_commande_bdd[TAILLE_CHAINE_MAX];                               // on initialise une variable de tableau de caractère pour stocker l'id de la commande en chaine de caractère pour intéragir avec la BDD
    PGresult *res;                                                         // on initialise la variable de résultat qui sera de type PGresult
    snprintf(id_commande_bdd, sizeof(id_commande_bdd), "%d", id_commande); // on convetit la variable en tableau de caractere
    const char *params[1] = {id_commande_bdd};                             // stock les variable dans un tableau params pour les passer en arguments dans la requête SQL
    res = PQexecParams(conn, "SELECT id_commande FROM cobrec1._bordereau WHERE id_commande = $1",
                       1,      // nombre de paramètre
                       NULL,   // type des parametres (il déduit tout seul les types)
                       params, // valeurs des paraètres (dans le tableau)
                       NULL,   // taille des paramètres (si on est en binaire)
                       NULL,   // format des paramètres (1 pour binaire 0 pour texte)
                       0);     // format du resultat (1 pour binaire 0 pour texte)

    if (PQresultStatus(res) != PGRES_TUPLES_OK) // est-ce que PostgreSQL a exécuté la requête SELECT sans erreur ? si oui PGRES_TUPLES_OK si non Erreur SQL
    {
        fprintf(stderr, "Erreur SELECT: %s\n", PQerrorMessage(conn)); // PQerrorMessage affichera un emssage plus claire et précis que juste Erreur SELECT (par ex relation not exist)
        PQclear(res);                                                 // libère la mémoire de PGresult sinon la mémoire s'acumulent
        return -1;                                                    // renvoie -1 si erreur
    }
    if ((PQntuples(res) > 0)) // PQntuples renvoie le nombre de ligne retourné par la reqeute select
    {
        PQclear(res);
        return 1; // on renvoie 1 pour dire qu'on a bien trouvé
    }
    else
    {
        PQclear(res); // libère la mémoire de PGresult sinon la mémoire s'acumulent
        return 0;     // renvoi 0 si rien n'est trouvé
    }
}

int nouveau_bordereau(PGconn *conn)
{
    /*fonction qui créer un nouveau bordereau
    elle renvoie :
    -1 en cas d'erreur
    un int si le bordereau a été crée (le nouveau bordereau)

    elle prend en paramètre :
    la connexion*/
    PGresult *res = PQexec(conn, "SELECT MAX(id_bordereau) FROM cobrec1._bordereau");

    if (PQresultStatus(res) != PGRES_TUPLES_OK)
    {
        fprintf(stderr, "Erreur SELECT: %s\n", PQerrorMessage(conn));
        PQclear(res);
        return -1;
    }

    char *val = PQgetvalue(res, 0, 0);
    int max = (val && strlen(val) > 0) ? atoi(val) : 0;

    PQclear(res);
    return max;
}

void enregistrer_commande(PGconn *conn, int id_commande, int bordereau, int status)
{
    /*fonction qui enregistre la commande dans la BDD
    elle renvoie :
    rien c'est un void

    elle prend en paramètre :
    la connexion
    l'id de la commande a enregistrer
    le bordereau qui doit être unique
    le status init a 0 car c'est le début du parcours
    */

    // initialisation des variable pour convertir en tableau de caaractère
    char id_bordereau_bdd[TAILLE_CHAINE_MAX];
    char id_commande_bdd[TAILLE_CHAINE_MAX];
    char status_bdd[TAILLE_CHAINE_MAX];

    // convertion des int en tableau de caractère
    snprintf(id_bordereau_bdd, sizeof(id_bordereau_bdd), "%d", bordereau);
    snprintf(id_commande_bdd, sizeof(id_commande_bdd), "%d", id_commande);
    snprintf(status_bdd, sizeof(status_bdd), "%d", status);

    // initialisation et remplissage du tableau des paramètres
    const char *params[3] = {
        id_bordereau_bdd,
        id_commande_bdd,
        status_bdd};

    // récupération des résultats de la requete
    PGresult *res = PQexecParams(
        conn,
        "INSERT INTO cobrec1._bordereau (id_bordereau, id_commande, etat_suivis) VALUES ($1, $2, $3)",
        3, // meme paramètre que dansla fonction cherche_si_commande_exist
        NULL,
        params,
        NULL,
        NULL,
        0);

    if (PQresultStatus(res) != PGRES_COMMAND_OK) // si il y a une erreur
    {
        fprintf(stderr, "Erreur INSERT: %s\n", PQerrorMessage(conn)); // on l'affiche su stderr
    }
    // libère la mémoire de PGresult sinon la mémoire s'acumulent
    PQclear(res);
}

int chercher_status_par_bordereau(PGconn *conn, int bordereau_recherche)
{
    /*fonction qui cherche le status d'une commande a partir de son bordereau
    elle renvoie :
    -1 si il y a une erreur
    -2 si le bordereau n'a pas été trouvé
    un int de l'état de la commande

    elle prend en paramètre :
    la connexion
    l'id du bordereau recherché*/
    char id_bordereau_bdd[TAILLE_CHAINE_MAX];
    PGresult *res;
    snprintf(id_bordereau_bdd, sizeof(id_bordereau_bdd), "%d", bordereau_recherche);
    const char *params[1] = {id_bordereau_bdd};
    res = PQexecParams(conn, "SELECT etat_suivis FROM cobrec1._bordereau WHERE id_bordereau = $1",
                       1,
                       NULL,
                       params,
                       NULL,
                       NULL,
                       0);

    if (PQresultStatus(res) != PGRES_TUPLES_OK)
    {
        fprintf(stderr, "Erreur SELECT: %s\n", PQerrorMessage(conn));
        PQclear(res);
        return -1;
    }
    if (PQntuples(res) == 0)
    {
        // bordereau non trouvé
        PQclear(res);
        return -2; // code spécial pour "inconnu" comme dit dans le commentaire de la fonction
    }
    // on récup le statut qui est de type string suite a la requete ligne 0 colonne 0 dans cet ordre
    char *val = PQgetvalue(res, 0, 0);
    return atoi(val);
}

void change_status(PGconn *conn, int bordereau_recherche, int new_stat)
{
    /*fonction qui change le status du bordereau passé en paramètre par le status passé en paramètre
    elle renvoie :
    rien c'est un void

    elle prend en paramètre :
    la connexion
    l'id du bordereau ou il faut changer le status
    le nouveau status de type int*/

    // même délire que toutes les autres fonction se référé à la fonction cherche_si_commande_exist qui est entièrement commenté
    char id_bordereau_bdd[TAILLE_CHAINE_MAX];
    char new_status_bdd[TAILLE_CHAINE_MAX];

    snprintf(id_bordereau_bdd, sizeof(id_bordereau_bdd), "%d", bordereau_recherche);
    snprintf(new_status_bdd, sizeof(new_status_bdd), "%d", new_stat);
    const char *params[2] = {
        new_status_bdd,
        id_bordereau_bdd};

    PGresult *res = PQexecParams(conn, "UPDATE cobrec1._bordereau SET etat_suivis = $1  WHERE id_bordereau = $2",
                 2,
                 NULL,
                 params,
                 NULL,
                 NULL,
                 0);
    if (PQresultStatus(res) != PGRES_COMMAND_OK) {
        fprintf(stderr, "Erreur UPDATE: %s\n", PQerrorMessage(conn));
    }
    PQclear(res);
}

int main()
{
    int sock, client_fd;
    struct sockaddr_in server_addr, client_addr;
    socklen_t client_len = sizeof(client_addr);
    char buffer[BUFFER_SIZE];
    int opt = 1;
    int bordereau;

    sock = socket(AF_INET, SOCK_STREAM, 0);
    if (sock < 0) // si l'initialisatio na échoué erreur de socket
    {
        perror("socket");
        return 1;
    }

    if (setsockopt(sock, SOL_SOCKET, SO_REUSEADDR, &opt, sizeof(opt)))
    {
        perror("setsockopt");
        exit(EXIT_FAILURE);
    }

    memset(&server_addr, 0, sizeof(server_addr));
    server_addr.sin_family = AF_INET;
    server_addr.sin_addr.s_addr = INADDR_ANY;
    server_addr.sin_port = htons(PORT);

    if (bind(sock, (struct sockaddr *)&server_addr, sizeof(server_addr)) < 0)
    {
        perror("bind");
        close(sock);
        return 1;
    }

    if (listen(sock, 5) < 0)
    {
        perror("listen");
        close(sock);
        return 1;
    }

    printf("Serveur en écoute sur le port %d\n", PORT);

    while (1)
    {
        printf("En attente de connexion...\n");
        client_fd = accept(sock, (struct sockaddr *)&client_addr, &client_len); // attend que le client se connecte pour boucler
        if (client_fd < 0)
        {
            perror("accept");
            continue;
        }

        printf("Client connecté.\n");

        // Fork
        pid_t pid = fork();
        if (pid < 0) {
            perror("fork");
            close(client_fd);
            continue;
        }
        if (pid > 0) {
            close(client_fd);
            continue;
        }
        close(sock);

        // Connexion à la base de données dans le processus fils
        PGconn *conn;
        conn = PQconnectdb(
            "host=10.253.5.101"
            "port=5432 "
            "dbname=saedb "
            "user=sae "
            "password=kira13");

        if (PQstatus(conn) != CONNECTION_OK) // si la connexion échoue
        {
            fprintf(stderr, "Erreur connexion: %s\n", PQerrorMessage(conn));
            PQfinish(conn);
            close(client_fd);
            exit(1);
        }

        do
        {
            memset(buffer, 0, BUFFER_SIZE); // nettoie le buffer
            ssize_t n = read(client_fd, buffer, BUFFER_SIZE - 1);

            if (n <= 0) // si le client c'est déco
            {
                // on quitte la boucle et on arrete le programme
                break;
            }

            char *ligne = strtok(buffer, "\r\n");
            if (!ligne)
                continue;

            if (strncmp(ligne, "CREATE_LABEL ", 13) == 0)
            {
                int already = 0;
                int id_commande = atoi(ligne + 13);
                int existe = cherche_si_commande_exist(conn, id_commande);
                if (existe == -1)
                {
                    write(client_fd, "ERROR DATABASE\n", 15);
                    continue;
                }
                else if (existe == 1)
                {
                    already = 1;
                }
                else
                {
                    already = 0;
                    bordereau = nouveau_bordereau(conn);
                    if (bordereau == -1)
                    {
                        fprintf(stderr, "Erreur à la création du bordereau.");
                    }
                    else if (bordereau >= 0)
                    {
                        // incrémente le bordereau car le numéro de bordereau renvoyé par la fonction est le plus grand de la BDD donc on ajoute 1 pour ne pas avoir de doublon
                        bordereau++;
                        printf("Nouveau bordereau : %d\n", bordereau);
                        // appelle de la fonction enregistrer_commande pour enregistrer la nouvelle commande avec le bordereau créé
                        // init a 0 car le statut de départ est 0
                        enregistrer_commande(conn, id_commande, bordereau, 1);

                        // Ajouter la ligne dans script.bash pour ajouter la commande a cron
                        FILE *script = fopen(FICHIER_SCRIPT, "a");
                        if (script != NULL) {
                            fprintf(script, "echo \"STATUS_UP %d\" | nc -q 1 10.253.5.101 9000\n", bordereau);
                            fclose(script);
                            printf("Ajouté au script: STATUS_UP %d\n", bordereau);
                        } else {
                            perror("Erreur ouverture script.bash");
                        }
                    }
                }

                char response[BUFFER_SIZE];
                snprintf(response, sizeof(response),
                         "LABEL=%d ALREADY_EXISTS=%d STEP=1 LABEL_STEP=\"Chez Alizon\"\n",
                         bordereau, already);
                write(client_fd, response, strlen(response));
            }
            else if (strncmp(ligne, "STATUS ", 7) == 0)
            {
                int label = atoi(ligne + 7);
                int ret = chercher_status_par_bordereau(conn, label);
                if (ret == -1)
                {
                    const char *rep = "ERREUR de SELECT.\n";
                    write(client_fd, rep, strlen(rep));
                }
                else if (ret == -2)
                {
                    const char *rep = "ERREUR, aucune commande trouvé\n";
                    write(client_fd, rep, strlen(rep));
                }
                else
                {
                    const char *libelle = "Chez Alizon";
                    char response[BUFFER_SIZE];
                    snprintf(response, sizeof(response),
                             "OK STEP=%d LABEL_STEP=\"%s\"\n", ret, libelle);
                    write(client_fd, response, strlen(response));
                }
            }
            // STAT evo

            else if (strncmp(ligne, "STATUS_UP", 9) == 0) {
                int label = atoi(ligne + 10);
                // récupere le statut actuel
                int status_act = chercher_status_par_bordereau(conn, label);
                //verifie si la commande est arrivée
                if (status_act >= 5) {
                    // Suppression de la ligne correspondante dans script.bash
                    FILE *src = fopen(FICHIER_SCRIPT, "r");
                    FILE *tmp = fopen("script_tmp.bash", "w");
                    if (src && tmp) {
                        char line[256];
                        char pattern[64];
                        snprintf(pattern, sizeof(pattern), "echo \"STATUS_UP %d\" | nc -q 1 10.253.5.101 9000", label);
                        while (fgets(line, sizeof(line), src)) {
                            if (strstr(line, pattern) == NULL) {
                                fputs(line, tmp);
                            }
                        }
                        fclose(src);
                        fclose(tmp);
                        remove(FICHIER_SCRIPT);
                        rename("script_tmp.bash", FICHIER_SCRIPT);
                    } else {
                        if (src) fclose(src);
                        if (tmp) fclose(tmp);
                    }
                    const char *msg = "COMMANDE FINI\n";
                    write(client_fd, msg, strlen(msg));
                //incremente le status
                } else if (status_act >= 0) {
                    int new_status = status_act + 1;  
                    change_status(conn, label, new_status);

                    char response[BUFFER_SIZE];
                    snprintf(response, sizeof(response),
                            "OK BORDEREAU=%d STATUS=%d\n", label, new_status);
                    write(client_fd, response, strlen(response));
                } else if (status_act == -2) {
                    const char *msg = "ERREUR, aucune commande trouvee\n";
                    write(client_fd, msg, strlen(msg));
                } else { // -1
                    const char *msg = "ERREUR SELECT\n";
                    write(client_fd, msg, strlen(msg));
                }
            }
            else
            {
                // Commande inconnue
                const char *rep = "ERROR UNKNOWN_COMMAND\nLa commande que vous avez tapez n'existe pas.\n";
                write(client_fd, rep, strlen(rep));
            }
        } while (1);

        printf("Client déconnecté.\n");
        close(client_fd);
        PQfinish(conn); // coupe la connexion a postgresql
        exit(0); // Termine le processus fils proprement
    }
    close(sock);
    return 0;
}
