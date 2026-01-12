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

int chercher_commande(int id_commande, int *max_bordereau) {
    FILE *f = fopen(FICHIER_COMMANDES, "r");
    *max_bordereau = 0;
    if (f == NULL) {
        return -1;
    }

    int cmd, bordereau, resultat = -1, st;
    char login[64], mdp[64];

    while (fscanf(f, "%d;%d;%d", &cmd, &bordereau, &st) == 5) {
        if (bordereau > *max_bordereau) {
            *max_bordereau = bordereau;
        }
        if (cmd == id_commande) {
            resultat = bordereau;
        }
    }
    fclose(f);
    return resultat;
}

void enregistrer_commande(int id_commande, int bordereau, int status) {
    FILE *f = fopen(FICHIER_COMMANDES, "a");
    if (f != NULL) {
        fprintf(f, "%d;%d;%d;\n", id_commande, bordereau, status);
        fclose(f);
    } else {
        perror("erreur ouverture");
    }
}

int chercher_status_par_bordereau(int bordereau_recherche, int *status) {
    FILE *f = fopen(FICHIER_COMMANDES, "r");
    if (f == NULL) return -1;

    int cmd, bordereau, st;
    char login[64], mdp[64];
    while (fscanf(f, "%d;%d;%63s;%63s;%d", &cmd, &bordereau, login, mdp, &st) == 5) {
        if (bordereau == bordereau_recherche) {
            *status = st;
            fclose(f);
            printf("Ligne lue: cmd=%d, bordereau=%d, login=%s, mdp=%s, st=%d\n",
            cmd, bordereau, login, mdp, st);

            return 0;
        }
    }

    fclose(f);
    return -1;
}



int main() {
    int server_fd, client_fd;
    struct sockaddr_in server_addr, client_addr;
    socklen_t client_len = sizeof(client_addr);
    char buffer[BUFFER_SIZE];
    char buffer2[BUFFER_SIZE];
    char buffer3[BUFFER_SIZE];


    server_fd = socket(AF_INET, SOCK_STREAM, 0);
    if (server_fd < 0) {
        perror("socket");
        return 1;
    }

    int opt = 1;
    setsockopt(server_fd, SOL_SOCKET, SO_REUSEADDR, &opt, sizeof(opt));

    memset(&server_addr, 0, sizeof(server_addr));
    server_addr.sin_family = AF_INET;
    server_addr.sin_addr.s_addr = INADDR_ANY;
    server_addr.sin_port = htons(PORT);

    if (bind(server_fd, (struct sockaddr*)&server_addr, sizeof(server_addr)) < 0) {
        perror("bind");
        close(server_fd);
        return 1;
    }

    if (listen(server_fd, 5) < 0) {
        perror("listen");
        close(server_fd);
        return 1;
    }

    printf("Serveur en écoute sur le port %d\n", PORT);

    while (1) {
        client_fd = accept(server_fd, (struct sockaddr*)&client_addr, &client_len);
        if (client_fd < 0) {
            perror("accept");
            continue;
        }

        // CREATE_LABEL
        memset(buffer2, 0, BUFFER_SIZE);
        ssize_t n = read(client_fd, buffer, BUFFER_SIZE - 1);
        if (n <= 0) {
            close(client_fd);
            continue;
        }

        char *ligne_cmd = strtok(buffer, "\r\n");
        if (!ligne_cmd) {
            close(client_fd);
            continue;
        }

        if (strncmp(ligne_cmd, "CREATE_LABEL ", 12) == 0) {
            int id_commande = atoi(ligne_cmd + 12);
            int max_bordereau = 0;
            int status = 0;
            int bordereau = chercher_commande(id_commande, &max_bordereau);
            int already = 0;

            if (bordereau < 0) {

                bordereau = max_bordereau + 1;
                enregistrer_commande(id_commande, bordereau, status);
                already = 0;
                printf("Nouveau: commande %d -> bordereau %d\n",
                       id_commande, bordereau);
            } else {

                already = 1;
                printf("Existant: commande %d -> bordereau %d\n",
                       id_commande, bordereau);
            }

            char response[BUFFER_SIZE];
            snprintf(response, sizeof(response),
                     "OK LABEL=%d ALREADY_EXISTS=%d STEP=1 LABEL_STEP=\"Chez Alizon\"\n",
                     bordereau, already);
            write(client_fd, response, strlen(response));
        } else {
            const char *rep = "ERREUR\n";
            write(client_fd, rep, strlen(rep));
        }
        // STATUS

    memset(buffer2, 0, BUFFER_SIZE);
    n = read(client_fd, buffer2, BUFFER_SIZE - 1);

        if (n <= 0) {
            close(client_fd);
            continue;
        }

        char *ligne_status = strtok(buffer2, "\r\n");
        
        fflush(stdout);


        if (!ligne_status) {
            close(client_fd);
            continue;
        }

        if (strncmp(ligne_status, "STATUS ", 7) == 0) {
            int label = atoi(ligne_status + 7);
            printf("label:%d",label);
            int step = 0;
            int ret = chercher_status_par_bordereau(label, &step);

            if (ret != 0) {
                const char *rep = "ERROR UNKNOWN_PARCEL\n";
                write(client_fd, rep, strlen(rep));
            } else {
                const char *libelle = "Chez Alizon"; 
                char response[BUFFER_SIZE];
                snprintf(response, sizeof(response),
                        "OK STEP=%d LABEL_STEP=\"%s\"\n", step, libelle);
                write(client_fd, response, strlen(response));
            }
        } else {
            const char *rep = "ERROR UNKNOWN_COMMAND\n";
            write(client_fd, rep, strlen(rep));
        }


    }

    close(server_fd);
    return 0;
}
