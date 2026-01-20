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
#include <signal.h>
#include <semaphore.h>
#include <fcntl.h>
#include <getopt.h>
#include <time.h>
#include <openssl/sha.h>

#define PORT 9000
#define BUFFER_SIZE 256
#define TAILLE_CHAINE_MAX 12
#define FICHIER_COMMANDES "commandes.txt"
#define FICHIER_SCRIPT "script.bash"
#define NOM_SEMAPHORE "/sem_transporteur_v1"

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

    fprintf(stderr, "DEBUG id_commande = %d\n", id_commande);

    if (PQresultStatus(res) != PGRES_TUPLES_OK) // est-ce que PostgreSQL a exécuté la requête SELECT sans erreur ? si oui PGRES_TUPLES_OK si non Erreur SQL
    {
        fprintf(stderr, "Erreur SELECT: %s\n", PQerrorMessage(conn)); // PQerrorMessage affichera un emssage plus claire et précis que juste Erreur SELECT (par ex relation not exist)
        PQclear(res);                                                 // libère la mémoire de PGresult sinon la mémoire s'acumulent
        return -1;                                                    // renvoie -1 si erreur
    }
    if ((PQntuples(res) > 0)) // PQntuples renvoie le nombre de ligne retourné par la reqeute select
    {
        fprintf(stderr, "Erreur test");
        PQclear(res);
        return 1; // on renvoie 1 pour dire qu'on a bien trouvé
    }
    else
    {
        fprintf(stderr, "Erreur test2");
        PQclear(res); // libère la mémoire de PGresult sinon la mémoire s'acumulent
        return 0;     // renvoi 0 si rien n'est trouvé
    }
}
int cherche_dernier_bordereau(PGconn *conn)
{
    /*fonction qui cherche le dernier bordereau
    elle renvoie :
    -1 en cas d'erreur
    un int si le bordereau a été crée (le nouveau bordereau)

    elle prend en paramètre :
    la connexion*/
    PGresult *res = PQexec(conn, "SELECT MAX(id_bordereau) FROM cobrec1._bordereau");

    if (PQresultStatus(res) != PGRES_TUPLES_OK) // si il y a une erreur
    {
        fprintf(stderr, "Erreur SELECT: %s\n", PQerrorMessage(conn)); // on l'affiche su stderr
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
        return -2;
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
    if (PQresultStatus(res) != PGRES_COMMAND_OK)
    {
        fprintf(stderr, "Erreur UPDATE: %s\n", PQerrorMessage(conn));
    }
    PQclear(res);
}

int cherche_bordereau(PGconn *conn, int id_commande, int *bordereau)
{
    /*fonction qui cherche le un bordereau passé en paramètre par le status passé en paramètre
    elle renvoie :
    1 si le bordereau est trouvé
    0 si rien n'est trouvé
    -1 si il y a une erreur BDD

    elle prend en paramètre :
    la connexion
    l'id du bordereau ou il faut changer le status
    le nouveau status de type int*/

    char id_commande_bdd[TAILLE_CHAINE_MAX];
    PGresult *res;
    snprintf(id_commande_bdd, sizeof(id_commande_bdd), "%d", id_commande);
    const char *params[1] = {id_commande_bdd};
    res = PQexecParams(conn, "SELECT id_bordereau FROM cobrec1._bordereau WHERE id_commande = $1",
                       1,
                       NULL,
                       params,
                       NULL,
                       NULL,
                       0);
    if (PQresultStatus(res) != PGRES_TUPLES_OK) // est-ce que PostgreSQL a exécuté la requête SELECT sans erreur ? si oui PGRES_TUPLES_OK si non Erreur SQL
    {
        fprintf(stderr, "Erreur SELECT: %s\n", PQerrorMessage(conn)); // PQerrorMessage affichera un emssage plus claire et précis que juste Erreur SELECT (par ex relation not exist)
        PQclear(res);                                                 // libère la mémoire de PGresult sinon la mémoire s'acumulent
        return -1;                                                    // renvoie -1 si erreur
    }
    if ((PQntuples(res) > 0))
    {
        *bordereau = atoi(PQgetvalue(res, 0, 0));
        PQclear(res); // libère la mémoire de PGresult sinon la mémoire s'acumulent
        return 1;     // renvoi 1 si il y a qlq chose
    }
    else
    {
        PQclear(res); // libère la mémoire de PGresult sinon la mémoire s'acumulent
        return 0;     // renvoi 0 si rien n'est trouvé
    }
}

// focntion de hash du mot de passe
void hash_sha256(const char *input, char *output)
{
    unsigned char hash[SHA256_DIGEST_LENGTH];
    SHA256((unsigned char *)input, strlen(input), hash);
    for (int i = 0; i < SHA256_DIGEST_LENGTH; i++)
        sprintf(output + (i * 2), "%02x", hash[i]);
    output[64] = 0;
}

int verif_login(PGconn *conn, char *email, char *mdp)
{
    PGresult *res;
    const char *params[1] = {email};

    res = PQexecParams(conn,
                       "SELECT mdp FROM cobrec1._login WHERE identifiant = $1",
                       1, NULL, params, NULL, NULL, 0);

    if (PQresultStatus(res) != PGRES_TUPLES_OK)
    {
        fprintf(stderr, "Erreur SELECT: %s\n", PQerrorMessage(conn));
        PQclear(res);
        return -1;
    }

    if (PQntuples(res) > 0)
    {
        char *mdp_bdd = PQgetvalue(res, 0, 0); // hash stocké en BDD
        char hash[65];
        hash_sha256(mdp, hash); // hash du mot de passe en clair saisi
        int match = (strcmp(hash, mdp_bdd) == 0) ? 1 : 0;
        PQclear(res);
        return match;
    }
    else
    {
        PQclear(res);
        return 0;
    }
}

int main(int argc, char *argv[])
{
    srand(time(NULL)); // pour le rand obligatoire sinon on aura tout le temps le mem résultat
    signal(SIGCHLD, SIG_IGN);
    int sock, client_fd;
    struct sockaddr_in server_addr, client_addr;
    socklen_t client_len = sizeof(client_addr);
    char buffer[BUFFER_SIZE];
    int opt = 1;
    int bordereau = 0;
    bool connecte = false;

    int capacite = -1;
    int c;
    while ((c = getopt(argc, argv, "c:")) != -1)
    {
        if (c == 'c')
            capacite = atoi(optarg);
    }
    if (capacite <= 0)
    {
        printf("Entrez la capacité du transporteur : ");
        fflush(stdout);
        char input[16];
        if (fgets(input, sizeof(input), stdin) != NULL)
        {
            capacite = atoi(input);
        }
        if (capacite <= 0)
        {
            printf("Capacité invalide. Valeur par défaut 2 utilisée.\n");
            capacite = 2;
        }
    }
    // Sémaphore pour la gestion du stock
    sem_unlink(NOM_SEMAPHORE);
    sem_t *sem = sem_open(NOM_SEMAPHORE, O_CREAT, 0644, capacite);
    if (sem == SEM_FAILED)
    {
        perror("sem_open");
        return 1;
    }
    // comment le colis à été livré
    char livre_en_quoi[3][60] = {
        "Livré en main propre\n",
        "Livré dans la boite aux lettres du destinataire\n",
        "Refusé par le destinataire : "};

    // comment le colis à été refusé
    char raison_refus[5][60] = {
        "Colis dégradé\n",
        "Le colis ne correspond pas à l'article commandé\n",
        "Quantité livrée incorrecte\n",
        "Colis déjà ouvert\n",
        "Retard du colis trop important\n"};

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

    printf("Serveur en écoute sur le port %d avec une capacité de %d\n", PORT, capacite);

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

        pid_t pid = fork();
        if (pid < 0)
        {
            perror("fork");
            close(client_fd);
            continue;
        }
        if (pid > 0)
        {
            close(client_fd);
            continue;
        }
        close(sock);

        PGconn *conn;
        conn = PQconnectdb(
            "host=10.253.5.101 "
            "port=5432 "
            "dbname=saedb "
            "user=sae "
            "password=kira13 ");

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
                break; // o n sort de la boucle
            }

            char *ligne = strtok(buffer, "\r\n");
            if (!ligne)
                continue;

            if (strncmp(ligne, "LOGIN ", 6) == 0)
            {
                char *email = NULL;
                char *mdp = NULL;
                char *login = ligne + 6;
                email = strtok(login, " ");
                mdp = strtok(NULL, " \r\n");

                if (email && mdp)
                {
                    int result = verif_login(conn, email, mdp);
                    if (result == 1)
                    {
                        const char *rep = "OK LOGIN_SUCCESS\n";
                        write(client_fd, rep, strlen(rep));
                        connecte = true;
                    }
                    else if (result == 0)
                    {
                        const char *rep = "ERROR LOGIN_INCORRECT\n";
                        write(client_fd, rep, strlen(rep));
                    }
                    else
                    {
                        const char *rep = "ERROR DATABASE\n";
                        write(client_fd, rep, strlen(rep));
                    }
                }
                else
                {
                    const char *rep = "ERROR LOGIN_FORMAT\n";
                    write(client_fd, rep, strlen(rep));
                }
                continue;
            }

            else if (strncmp(ligne, "CREATE_BORDEREAU ", 17) == 0)
            {
                if (connecte == false)
                {
                    const char *rep = "LOGIN FIRST\n";
                    write(client_fd, rep, strlen(rep));
                }
                else
                {
                    int already = 0;
                    int id_commande = atoi(ligne + 17);
                    int existe = cherche_si_commande_exist(conn, id_commande);
                    if (existe == -1)
                    {
                        write(client_fd, "ERROR DATABASE\n", 15);
                        continue;
                    }
                    else if (existe == 1)
                    {
                        already = 1;
                        // récup le bordereau et le stock dans la variable bordereau
                        cherche_bordereau(conn, id_commande, &bordereau);
                    }
                    else
                    {
                        sem_wait(sem); // bloque une commande et la met en attente si il y a plus de place pour le transporteur
                        already = 0;
                        int dernierBordereau = cherche_dernier_bordereau(conn);
                        char temp2[25];
                        fflush(stdout);

                        if (dernierBordereau == -1)
                        {
                            fprintf(stderr, "Erreur à la création du bordereau.");
                        }
                        else if (dernierBordereau >= 0)
                        {
                            bordereau = dernierBordereau;

                            // incrémente le bordereau car le numéro de bordereau renvoyé par la fonction est le plus grand de la BDD donc on ajoute 1 pour ne pas avoir de doublon
                            bordereau++;
                            printf("DEBUG | dernierBordereau=%d | bordereau=%d | texte=%s\n",
                                   dernierBordereau, bordereau, "CREATION BORDEREAU");
                            printf("Nouveau bordereau : %d\n", bordereau);
                            // appelle de la fonction enregistrer_commande pour enregistrer la nouvelle commande avec le bordereau créé
                            // init a 0 car le statut de départ est 0
                            enregistrer_commande(conn, id_commande, bordereau, 1);

                            // ajoute la ligne dans script.bash pour ajouter la commande a cron
                            FILE *script = fopen(FICHIER_SCRIPT, "a");
                            if (script != NULL)
                            {
                                fprintf(script, "echo -e \"LOGIN Alizon Alizon1!\\nSTATUS_UP %d\" | nc -q 1 10.253.5.101 9000\n", bordereau);
                                fclose(script);
                                printf("Ajouté au script: STATUS_UP %d\n", bordereau);
                            }
                            else
                            {
                                perror("Erreur ouverture script.bash");
                            }
                        }
                    }
                    char response[BUFFER_SIZE];
                    snprintf(response, sizeof(response),
                             "LABEL=%d ALREADY_EXISTS=%d STATUS=1\n",
                             bordereau, already);
                    write(client_fd, response, strlen(response));
                }
            }
            else if (strncmp(ligne, "STATUS ", 7) == 0)
            {
                if (connecte == false)
                {
                    const char *rep = "LOGIN FIRST\n";
                    write(client_fd, rep, strlen(rep));
                }
                else
                {
                    int label = atoi(ligne + 7);
                    int ret = chercher_status_par_bordereau(conn, label);

                    printf("Demande STATUS pour bordereau %d : ret=%d\n", label, ret);

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
                        char response[BUFFER_SIZE];
                        // envoie la ligne de status
                        snprintf(response, sizeof(response),
                                 "OK STEP=%d \n", ret);
                        write(client_fd, response, strlen(response));

                        if (ret == 5)
                        {
                            // on récupère le mode de livraison depuis la bdd pour l'envoyer au socket
                            char id_bordereau_bdd[TAILLE_CHAINE_MAX];
                            snprintf(id_bordereau_bdd, sizeof(id_bordereau_bdd), "%d", label);
                            const char *params[1] = {id_bordereau_bdd};

                            PGresult *res_comment_livre = PQexecParams(conn,
                                                                       "SELECT mode_livraison FROM cobrec1._bordereau WHERE id_bordereau = $1",
                                                                       1, NULL, params, NULL, NULL, 0);
                            if (PQresultStatus(res_comment_livre) == PGRES_TUPLES_OK) // est-ce que PostgreSQL a exécuté la requête SELECT sans erreur ? si oui PGRES_TUPLES_OK si non Erreur SQL
                            {
                                char *mode_livraison = PQgetvalue(res_comment_livre, 0, 0);

                                char detail_msg[BUFFER_SIZE];
                                snprintf(detail_msg, sizeof(detail_msg), "LIVRE: %s", mode_livraison);
                                write(client_fd, mode_livraison, strlen(mode_livraison));
                                if (strcmp(mode_livraison, "Livré dans la boite aux lettres du destinataire\n") == 0)
                                { // on envoie l'image seulement si le destinataire était absent
                                    const char *image_vendeur = "../html/img/photo/Delivraptor/boite_au_lettre.jpg";
                                    FILE *fp = fopen(image_vendeur, "rb");
                                    if (fp)
                                    {
                                        // cette partie permet de savoir la taille du fichier
                                        fseek(fp, 0, SEEK_END);    // place le pointeur a la fin du fichier pour avoir la taille
                                        long filesize = ftell(fp); // demande la taille du fichier
                                        fseek(fp, 0, SEEK_SET);    // remet le pointeur au début du fichier

                                        char *bufferimage = malloc(filesize);
                                        if (bufferimage)
                                        {
                                            fread(bufferimage, 1, filesize, fp);
                                            char imageInfo[64];
                                            // envoie de l'entete
                                            snprintf(imageInfo, sizeof(imageInfo), "IMG_START %ld\n", filesize);
                                            write(client_fd, imageInfo, strlen(imageInfo));
                                            // envoie les donées
                                            write(client_fd, bufferimage, filesize);
                                            free(bufferimage);
                                        }
                                        fclose(fp);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            else if (strncmp(ligne, "STATUS_UP", 9) == 0)
            {
                if (connecte == false)
                {
                    const char *rep = "LOGIN FIRST\n";
                    write(client_fd, rep, strlen(rep));
                }
                else
                {
                    int label = atoi(ligne + 10);
                    // récupere le statut actuel
                    int status_act = chercher_status_par_bordereau(conn, label);
                    int new_status = status_act + 1;

                    printf("STATUS_UP pour %d : %d -> %d\n", label, status_act, new_status);
                    // verifie si la commande est arrivée
                    if (new_status == 5) // si le nouveau status est 5
                    {
                        change_status(conn, label, new_status); // change le status de la commande
                        sem_post(sem);                          // débloque une commande en attente si il y a une place pour le transporteur

                        int max = 2;                                                     // c'est le nombre de facon de comment le colis à été livré (0,1,2 car il y a 3 raisons)
                        int id_comment_livre = rand() % (max + 1);                       /// on créé un id pour choisir aléatoirement comment il est livré
                        char comment_livre[40];                                          // on initialise une variable de comment on livre
                        strcpy(comment_livre, livre_en_quoi[id_comment_livre]);          // on copie la raison dans la variable
                        if (strcmp(comment_livre, "Refusé par le destinataire : ") == 0) // on regarde si la raison est == à Refusé pour pouvoir init une raison de pourquoi il est refusé
                        {
                            char raison_du_refus[40];                               // on initialise une variable de la raison du refus
                            int max_raison_refus = 4;                               // c'est le nombre de facon de pourquoi le colis à été refusé par le client (0,1,2,3,4 car il y a 5 raisons)
                            int id_raison_refus = rand() % (max + 1);               // on créé un id pour choisir aléatoirement comment il est refusé
                            strcpy(raison_du_refus, raison_refus[id_raison_refus]); // on copie la raison du refus dans raison_du_refus
                            strcat(comment_livre, raison_du_refus);                 // on concatene la chaine de comment_livre avec raison_du_refus pour afficher
                        }
                        char id_bordereau_update[128];
                        snprintf(id_bordereau_update, sizeof(id_bordereau_update), "%d", label);
                        const char *params[2] = {
                            comment_livre,
                            id_bordereau_update};

                        PGresult *res_update = PQexecParams(conn, "UPDATE cobrec1._bordereau SET mode_livraison = $1  WHERE id_bordereau = $2",
                                                            2,
                                                            NULL,
                                                            params,
                                                            NULL,
                                                            NULL,
                                                            0);
                        if (PQresultStatus(res_update) != PGRES_COMMAND_OK)
                        {
                            fprintf(stderr, "Erreur UPDATE mode_livraison: %s\n", PQerrorMessage(conn));
                        }
                        PQclear(res_update); // libère la mémoire de PGresult sinon la mémoire s'acumulent
                        write(client_fd, comment_livre, strlen(comment_livre));
                    }
                    else if (status_act >= 5)
                    {
                        FILE *src = fopen(FICHIER_SCRIPT, "r");
                        FILE *tmp = fopen("script_tmp.bash", "w");
                        if (src && tmp)
                        {
                            char line[256];
                            char pattern[64];
                            snprintf(pattern, sizeof(pattern), "STATUS_UP %d", label);
                            while (fgets(line, sizeof(line), src))
                            {
                                if (strstr(line, pattern) == NULL)
                                {
                                    fputs(line, tmp);
                                }
                            }
                            fclose(src);
                            fclose(tmp);
                            remove(FICHIER_SCRIPT);
                            rename("script_tmp.bash", FICHIER_SCRIPT);
                        }
                        else
                        {
                            if (src)
                                fclose(src);
                            if (tmp)
                                fclose(tmp);
                        }
                        const char *msg = "COMMANDE FINI\n";
                        write(client_fd, msg, strlen(msg));
                    }
                    else if (status_act >= 0)
                    {
                        int new_status = status_act + 1;
                        change_status(conn, label, new_status);

                        char response[BUFFER_SIZE];
                        snprintf(response, sizeof(response),
                                 "OK BORDEREAU=%d STATUS=%d\n", label, new_status);
                        write(client_fd, response, strlen(response));
                    }
                    else if (status_act == -2)
                    {
                        const char *msg = "ERREUR, aucune commande trouvee\n";
                        write(client_fd, msg, strlen(msg));
                    }
                    else
                    {
                        const char *msg = "ERREUR SELECT\n";
                        write(client_fd, msg, strlen(msg));
                    }
                }
            }
            else if (strcmp(ligne, "--help") == 0 || strcmp(ligne, "HELP") == 0)
            {
                const char *rep =
                    "LOGIN <param1> <param2> : identifiant=<param1>, mot_de_passe=<param2>\n"
                    "STATUS <param1>        : statut actuel via bordereau=<param1>\n"
                    "STATUS_UP <param1>     : incremente le statut via bordereau=<param1>\n"
                    "CREATE_BORDEREAU <p1>  : cree et stocke un bordereau pour commande=<param1>\n";
                write(client_fd, rep, strlen(rep));
            }

            else
            {
                const char *rep = "ERROR UNKNOWN_COMMAND\nLa commande que vous avez tapez n'existe pas.\n";
                write(client_fd, rep, strlen(rep));
            }
        } while (1);

        printf("Client déconnecté.\n");
        close(client_fd);
        PQfinish(conn);
        exit(0);
    }
    close(sock);
    return 0;
}
