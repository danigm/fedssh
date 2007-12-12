#include <stdio.h>

int main(){
    FILE *f;
    f = fopen("/tmp/pamlog1", "a");
    fwrite("prueba\n",sizeof(char),6,f);
    fclose(f);
    return 0;
}
