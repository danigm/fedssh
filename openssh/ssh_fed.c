#include "ssh_fed.h"
#include <sys/socket.h>
#include <sys/types.h>
#include <string.h>
#include <unistd.h>
#include <stdio.h>
#include <netdb.h>
#include <ldap.h>

//TODO hacerlo seguro, con openssl
int get_rsa_key(char *keyserver, int port, char *user, char *rsa_key){
int sockfd, n;
struct sockaddr_in serv_addr;
struct hostent *server;

char ret[600];
char msg[100];
strcpy(ret,"");
sprintf(msg, "USR:%s\r\n", user);

sockfd = socket(AF_INET, SOCK_STREAM, 0);
if (sockfd < 0)
    return -1;

if ((server=gethostbyname(keyserver)) == NULL)
    return -1;

serv_addr.sin_family = AF_INET;
serv_addr.sin_port = htons(port);
serv_addr.sin_addr = *((struct in_addr *)server->h_addr);
memset(serv_addr.sin_zero, '\0', sizeof serv_addr.sin_zero);
if (connect(sockfd, (struct sockaddr *)&serv_addr, sizeof serv_addr) == -1)
    return -1;

send(sockfd, msg, sizeof(msg), 0);
if ((n=recv(sockfd, ret, 599, 0)) == -1)
    return -1;

close(sockfd);

strcpy(rsa_key, ret);
return 0;
}


