#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <unistd.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/ipc.h>
#include <sys/sem.h>

#define PORT 9000
#define BUFFER_SIZE 256

int main() {
    int server_fd, client_fd;
    struct sockaddr_in server_addr, client_addr;
    socklen_t client_len = sizeof(client_addr);
    char buffer[BUFFER_SIZE];
    int id_commande;

    // Création du socket
    server_fd = socket(AF_INET, SOCK_STREAM, 0);

    // Option pour réutiliser l'adresse
    int opt = 1;
    setsockopt(server_fd, SOL_SOCKET, SO_REUSEADDR, &opt, sizeof(opt));

    // Configuration adresse
    server_addr.sin_family = AF_INET;
    server_addr.sin_addr.s_addr = INADDR_ANY;
    server_addr.sin_port = htons(PORT);

    // Bind
    if (bind(server_fd, (struct sockaddr*)&server_addr, sizeof(server_addr)) < 0) {
        perror("Erreur bind");
        close(server_fd);
        return 1;
    }

    // Listen
    if (listen(server_fd, 5) < 0) {
        perror("Erreur listen");
        close(server_fd);
        return 1;
    }

    printf("Transporteur en écoute sur le port %d...\n", PORT);

    // Sémaphores
    key_t cle1 = ftok(".", 1);
    key_t cle2 = ftok(".", 2);
    int workers = semget(cle1, 1, IPC_CREAT | 0640);
    semctl(workers, 0, SETVAL, 0);
    int taches = semget(cle2, 1, IPC_CREAT | 0640);
    semctl(taches, 0, SETVAL, 0);

    while (1) {
        // Accepter connexion
        client_fd = accept(server_fd, (struct sockaddr*)&client_addr, &client_len);
        if (client_fd < 0) {
            perror("Erreur accept");
            continue;
        }

        // Lire le numéro de commande
        memset(buffer, 0, BUFFER_SIZE);
        int bytes_read = read(client_fd, buffer, BUFFER_SIZE - 1);
        if (bytes_read > 0) {
            id_commande = atoi(buffer);
            printf("Commande reçue: %d\n", id_commande);

            // Envoyer confirmation
            char response[64];
            sprintf(response, "OK:%d", id_commande);
            write(client_fd, response, strlen(response));

            // Traitement avec sémaphores
            struct sembuf sop;
            sop.sem_num = 0;
            sop.sem_flg = 0;

            // Signaler une tâche disponible
            sop.sem_op = 1;
            semop(taches, &sop, 1);
        }

        close(client_fd);
    }

    close(server_fd);
    return 0;
}