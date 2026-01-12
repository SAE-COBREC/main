#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <unistd.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define PORT 9000
#define BUFFER_SIZE 256
#define FICHIER_COMMANDES "commandes.txt"

int chercher_commande(int id_commande, int *max_bordereau)
{
    *max_bordereau = 0;
    FILE *f = fopen(FICHIER_COMMANDES, "r");
    if (f == NULL)
        return -1;

    int cmd, bordereau, resultat = -1, st;
    while (fscanf(f, "%d;%d;%d;\n", &cmd, &bordereau, &st) == 3)
    {
        if (*max_bordereau < bordereau)
            *max_bordereau = bordereau;
        if (cmd == id_commande)
        {
            resultat = bordereau;
            *max_bordereau = bordereau; // mise à jour du max même si trouvé
        }
    }
    fclose(f);
    return resultat;
}

void enregistrer_commande(int id_commande, int bordereau, int status)
{
    FILE *f = fopen(FICHIER_COMMANDES, "a");
    if (f != NULL)
    {
        fprintf(f, "%d;%d;%d;\n", id_commande, bordereau, status);
        fclose(f);
    }
    else
        perror("erreur ouverture");
}

int chercher_status_par_bordereau(int bordereau_recherche, int *status)
{
    FILE *f = fopen(FICHIER_COMMANDES, "r");
    if (f == NULL)
        return -1;

    int cmd, bordereau, st;
    while (fscanf(f, "%d;%d;%d; \n", &cmd, &bordereau, &st) == 3)
    {
        if (bordereau == bordereau_recherche)
        {
            *status = st;
            fclose(f);
            return 0;
        }
    }
    fclose(f);
    return -1;
}

int main()
{
    int sock, client_fd;
    struct sockaddr_in server_addr, client_addr;
    socklen_t client_len = sizeof(client_addr);
    char buffer[BUFFER_SIZE];
    int opt = 1;

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
                int id_commande = atoi(ligne + 13);
                int max_bordereau = 0;
                int bordereau = chercher_commande(id_commande, &max_bordereau);
                int already = 0;

                if (bordereau < 0)
                {
                    bordereau = max_bordereau + 1;
                    enregistrer_commande(id_commande, bordereau, 0); // status a 0 par défaut
                    already = 0;
                    printf("Nouveau: commande %d -> bordereau %d\n", id_commande, bordereau);
                }
                else
                {
                    already = 1;
                    printf("Existant: commande %d -> bordereau %d\n", id_commande, bordereau);
                }

                char response[BUFFER_SIZE];
                snprintf(response, sizeof(response),
                         "OK LABEL=%d ALREADY_EXISTS=%d STEP=1 LABEL_STEP=\"Chez Alizon\"\n",
                         bordereau, already);
                write(client_fd, response, strlen(response));
            }
            else if (strncmp(ligne, "STATUS ", 7) == 0)
            {
                int label = atoi(ligne + 7);
                int step = 0;
                int ret = chercher_status_par_bordereau(label, &step);

                if (ret != 0)
                {
                    const char *rep = "ERROR UNKNOWN_PARCEL\nAucune commande trouvé.";
                    write(client_fd, rep, strlen(rep));
                }
                else
                {
                    const char *libelle = "Chez Alizon";
                    char response[BUFFER_SIZE];
                    snprintf(response, sizeof(response),
                             "OK STEP=%d LABEL_STEP=\"%s\"\n", step, libelle);
                    write(client_fd, response, strlen(response));
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
    }

    close(sock);
    return 0;
}
