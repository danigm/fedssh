#include <stdio.h>
#include <string.h>
#include <time.h>

int check_timeout(char *timeout) {
    int now = time(NULL);
    char now_str[60];
    char timeout_str[60];
    int i, j;
    sprintf(now_str, "%d", now);
    for(i = 0, j=0; i < strlen(timeout); i++){
        if(timeout[i] != ':'){
            timeout_str[j] = timeout[i];
            j++;
        }
        else
            j = 0;
    }
    timeout_str[j] = '\0';
    i = strcmp(now_str, timeout_str);
    printf("%s, %s\n", timeout_str, now_str);
    if(i < 0)
        return 1;
    else if (i >= 0)
        return 0;
}

int main(){
    FILE *tub = popen("useradd -p xx -d /home/pepitoperez pepitoperez", "r");
    pclose(tub);
/**
    char *timeout = "schac:userStatus:us.es:timeout:1204890710";
    char *timeout2 = "schac:userStatus:us.es:timeout:1204893030";
    printf("comienzo prueba\n");
    printf("prueba1: %d\n", check_timeout(timeout));
    printf("prueba2: %d\n", check_timeout(timeout2));
    printf("final prueba\n");
*/
}
