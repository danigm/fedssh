#include <ldap.h>
#include <sys/socket.h>
#include <sys/types.h>
#include <string.h>
#include <unistd.h>
#include <stdio.h>
#include <netdb.h>
#include <time.h>
#include "ssh_fed.h"

#include "includes.h"
#include <stdarg.h>

#include "log.h"
#include "servconf.h"

extern ServerOptions options;

int check_timeout(char *timeout) {
    int now = time(NULL);
    char now_str[60];
    char timeout_str[60];
    int i, j;
    sprintf(now_str, "%d", now);
    /*
     * The timeout can be a simple number, this is
     * for the urn case, but if it is a number, nothing
     * happens, because no have :
     */
    for(i = 0, j=0; (unsigned)i < strlen(timeout); i++){
        if(timeout[i] != ':'){
            timeout_str[j] = timeout[i];
            j++;
        }
        else
            j = 0;
    }
    timeout_str[j] = '\0';
    i = strcmp(now_str, timeout_str);
    if(i < 0)
        return 1;
    else if (i >= 0)
        return 0;
    else return 1;
}

int get_rsa_key_ldap(char *keyserver, int port, char *user, char *rsa_key){
    LDAP *ld;
    int  result;
    int desired_version = LDAP_VERSION3;
    int ldap_port       = port;
    char *ldap_host     = keyserver;
    debug("\n\nOPTIONS: %s, %s, %s, %s\n\n", options.fedserver_root_dn, options.fedserver_root_pw, options.fedserver_base, options.fedserver_attr);
    char *root_dn       = options.fedserver_root_dn;
    char *root_pw       = options.fedserver_root_pw;
    char* base          = options.fedserver_base;
    char *attribute     = options.fedserver_attr;
    char *timeattr      = options.fedserver_timeattr;
    char filter[255];
    char rsa_key2[600];
    char timeout[100];
    sprintf(filter, "(uid=%s)",user);

    LDAPMessage *msg;
    int msgid;
    int rc;

    BerElement *ber;
    char *attr;

    char ldap_host_string[256];
    sprintf(ldap_host, "%s:%d", ldap_host, ldap_port);
    strcpy(ldap_host_string, "ldap://");
    strcat(ldap_host_string, ldap_host);
    //connecting to ldap server
    rc = ldap_initialize(&ld, ldap_host_string);
    if ( rc != LDAP_SUCCESS ) {
        debug( "XX> ldap_init failed, %s", ldap_err2string(rc) );
        return -1;
    }

    //we set the version and protocol
    rc = ldap_set_option(ld, LDAP_OPT_PROTOCOL_VERSION, &desired_version);
    if ( rc != LDAP_OPT_SUCCESS)
    {
        debug( "XX> ldap_set_option failed, %s", ldap_err2string(rc) );
        return -1;
    }

    //bind
    struct berval cred;
    struct berval *servcred;
    cred.bv_val = root_pw;
    cred.bv_len = sizeof(root_pw) - 1;
    debug( ">>>>>>>>>>> %s", cred.bv_val);
    /**
    rc = ldap_sasl_bind_s(ld, root_dn, "DIGEST-MD5", &cred, NULL, NULL, &servcred);
    if ( rc != LDAP_SUCCESS ) {
        debug( "XX> ldap_bind failed, %s", ldap_err2string(rc) );
        return -1;
    }
    **/
    // search from this point
    rc = ldap_search_ext( ld, base, LDAP_SCOPE_SUBTREE, filter, NULL, 0, NULL, NULL, NULL, LDAP_NO_LIMIT, &msgid );
    if ( rc != LDAP_SUCCESS )
    {
        debug( "XX> ldap_search failed, %s", ldap_err2string(rc) );
        return -1;
    }
    result = ldap_result(ld, msgid, 1, NULL, &msg);

    switch(result)
    {
        case(-1):
            break;
        case(0):
            debug("Timeout exceeded in ldap_result()");
            break;
        case(LDAP_RES_SEARCH_RESULT):
            debug("Search result returned\n");

            break;
        default:
            debug("result : %x\n", result);
            break;
    }

    struct berval **vals;
    struct berval *val;
    int i;
    int num_entries_returned = ldap_count_entries(ld, msg);
    debug("xxxxxxxxxxxxxx %d\n", num_entries_returned);
    if (num_entries_returned > 0) {
        LDAPMessage *entry=ldap_first_entry(ld, msg);
        for( attr = ldap_first_attribute(ld, entry, &ber); attr != NULL;
                attr = ldap_next_attribute(ld, entry, ber)) 
        {
            vals = ldap_get_values_len(ld, entry, attr);
            if (vals != NULL)  {
                for(i = 0; vals[i] != NULL; i++) {
                    val = vals[i];
                    /* process the current value */
                    if (strcmp(attr, timeattr) == 0){
                        strcpy(timeout, val->bv_val);
                    }
                    if (strcmp(attr, attribute) == 0){
                        strcpy(rsa_key2, val->bv_val);
                        debug("xxxxxxxxxxxXX %s:%s\n", attr, rsa_key);
                    }
                }
                if (check_timeout(timeout)) {
                    strcpy(rsa_key, rsa_key2);
                    debug("xxxxxxxxxxxXX %s:%s\n", attr, rsa_key);
                }else
                    debug("\nTIMEOUT CUMPLIDO\n");
            }
            ldap_value_free_len(vals);
        }
        ldap_memfree(ber);
    }
    ldap_msgfree(msg);


    //unbind
    result = ldap_unbind_ext_s(ld, NULL, NULL);

    if (result != LDAP_SUCCESS) {
        debug("!!!!!!! ldap_unbind_s: %s\n", ldap_err2string(result));
        return -1;
    }
    return 0;
}



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
