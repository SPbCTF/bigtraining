#include <stdio.h>
#include <unistd.h>
#include <time.h>
#include <string.h>
#include <stdlib.h>

typedef unsigned long long u64;

#define K(i) key[((u64)(i)) % keyLen]
#define ROLchar(n, b) ((((unsigned char)(n)) << (b)) | (((unsigned char)(n)) >> (8 - (b))))
#define ROLu64(n, b) ((((u64)(n)) << (b)) | (((u64)(n)) >> (64 - (b))))

void key_expansion(char * key, unsigned long long nonce, char * expandedKey) {
    int i, keyLen;
    char c;
    
    keyLen = strlen(key);
    
    for (i = 0; i < 255; i++) {
        #include "../rounds.h"
        
        expandedKey[i] = c;
    }
}

int main(int argc, char ** argv) {
    char key[64];
    int i, len;
    char expKey[256];
    char chs[] = "1234567890qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM";
    
    srand(time(NULL) + getpid() + getppid());
    len = rand() % 20 + 20;
    for (i = 0; i < len; i++) {
        key[i] = chs[rand() % 62];
    }
    key[i] = '\0';
    
    key_expansion(key, (rand() & 0xFFFFull) | ((rand() & 0xFFFFull) << 16) | ((rand() & 0xFFFFull) << 32) | ((rand() & 0xFFFFull) << 48), expKey);
    
    for (i = 0; i < 256; i++) {
        printf("%02x", (unsigned char)expKey[i]);
    }
    printf("\n");
}
