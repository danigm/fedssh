#include "ssh_fed.h"
#include <sys/socket.h>
#include <sys/types.h>
#include <string.h>
#include <unistd.h>
#include <stdio.h>
#include <netdb.h>
#include <ldap.h>
#include "servconf.h"

extern ServerOptions options;

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

//TODO esto es para probar
int get_rsa_key_ldap(char *keyserver, int port, char *user, char *rsa_key){
    LDAP *ld;
    int  result;
    int  auth_method    = LDAP_AUTH_SIMPLE;
    int desired_version = LDAP_VERSION3;
    int ldap_port       = port;
    char *ldap_host     = keyserver;
    //TODO al fichero de configuracion
    char *root_dn       = "cn=admin,dc=us,dc=es";
    char *root_pw       = "fedssh";
    char* base          = "o=People,dc=us,dc=es";
    char *attribute     = "description";
    int attr_bin        = 0; //por si es el atributo usercertificate, b64decode
    char filter[255];
    sprintf(filter, "(uid=%s)",user);

    LDAPMessage *msg;
    int msgid;
    
    BerElement *ber;
    char *attr;

    //connecting to ldap server
    if ((ld = ldap_init(ldap_host, ldap_port)) == NULL ) {
        debug( "ldap_init failed" );
        return -1;
    }

    //we set the version and protocol
    if (ldap_set_option(ld, LDAP_OPT_PROTOCOL_VERSION, &desired_version) != LDAP_OPT_SUCCESS)
    {
        ldap_perror(ld, "ldap_set_option failed!");
        return -1;
    }

    //bind
    if (ldap_bind_s(ld, root_dn, root_pw, auth_method) != LDAP_SUCCESS ) {
        ldap_perror( ld, "ldap_bind" );
        return -1;
    }
    // search from this point 

    // return everything 
    debug("xxxxxxxxxxxxxxx %s\n", filter);

    if ((msgid = ldap_search(ld, base, LDAP_SCOPE_SUBTREE, filter, NULL, 0)) == -1) 
    {
        ldap_perror( ld, "ldap_search" );
    }
    result = ldap_result(ld, msgid, 1, NULL, &msg);

    switch(result)
    {
        case(-1):
            ldap_perror(ld, "ldap_result");
            break;
        case(0):
            debug("!!!!!!! Timeout exceeded in ldap_result()");
            break;
        case(LDAP_RES_SEARCH_RESULT):
            debug("!!!!!!! Search result returned\n");

            break;
        default:
            debug("!!!!!!! result : %x\n", result);
            break;
    }

    char **vals;
    int i;
    int num_entries_returned = ldap_count_entries(ld, msg);
    debug("xxxxxxxxxxxxxx %d\n", num_entries_returned);
    if (num_entries_returned > 0) {
        LDAPMessage *entry=ldap_first_entry(ld, msg);
        for( attr = ldap_first_attribute(ld, entry, &ber); attr != NULL;
                attr = ldap_next_attribute(ld, entry, ber)) 
        {
            if ((vals = ldap_get_values(ld, entry, attr)) != NULL)  {
                for(i = 0; vals[i] != NULL; i++) {
                    /* process the current value */
                    //Si puede haber varias claves, hay que concatenar, no copiar
                    if (strcmp(attr, attribute) == 0)
                        strcpy(rsa_key, vals[i]);
                    debug("xxxxxxxxxxxXX %s:%s\n", attr, vals[i]);
                }
            }
            ldap_memfree(vals);
        }
        ldap_memfree(ber);
    }
    ldap_msgfree(msg);


    //unbind
    result = ldap_unbind_s(ld);

    if (result != 0) {
        debug("!!!!!!! ldap_unbind_s: %s\n", ldap_err2string(result));
        return -1;
    }
    return 0;
}
