#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <dirent.h>

#define OP_REG 0x2E6157E22E6157E2ull
#define OP_SEND 0x5E8D5E8D5E8D5E8Dull
#define OP_READ 0x2EAD2EAD2EAD2EADull
#define OP_USERS 0x1157ACC51157ACC5ull
#define OP_MSGS 0x115711578355A6E5ull
#define OP_ABOUT 0xAB08750F7847Eull

#define ST_SUCCESS 0x5ACCE55ull
#define ST_FAIL 0xFA11002Eull

typedef unsigned long long u64;

#define REPLY(code) \
    memset(buf, 0, 4096); \
    *(u64 *)&buf[0x00] = packetId; \
    *(u64 *)&buf[0x08] = code; \
    \
    write(1, buf, 8 + 8);

#define REPLY_STRING(code, string) \
    memset(buf, 0, 4096); \
    *(u64 *)&buf[0x00] = packetId; \
    *(u64 *)&buf[0x08] = code; \
    strcpy(&buf[0x10], string); \
    \
    write(1, buf, 8 + 8 + strlen(&buf[0x10]) + 1);

#define REPLY_NUM(code, num) \
    memset(buf, 0, 4096); \
    *(u64 *)&buf[0x00] = packetId; \
    *(u64 *)&buf[0x08] = code; \
    *(u64 *)&buf[0x10] = num; \
    \
    write(1, buf, 8 + 8 + 8);

#define REPLY_NUM_STRING(code, num, string) \
    memset(buf, 0, 4096); \
    *(u64 *)&buf[0x00] = packetId; \
    *(u64 *)&buf[0x08] = code; \
    *(u64 *)&buf[0x10] = num; \
    strcpy(&buf[0x18], string); \
    \
    write(1, buf, 8 + 8 + 8 + strlen(&buf[0x18]) + 1);


#define K(i) key[((u64)(i)) % keyLen]
#define ROLchar(n, b) ((((unsigned char)(n)) << (b)) | (((unsigned char)(n)) >> (8 - (b))))
#define ROLu64(n, b) ((((u64)(n)) << (b)) | (((u64)(n)) >> (64 - (b))))

void key_expansion(char * key, u64 nonce, char * expandedKey) {
    int i, keyLen;
    char c;
    
    keyLen = strlen(key);
    
    for (i = 0; i < 255; i++) {
        #include "rounds.h"
        
        expandedKey[i] = c;
    }
}

void encrypt(char * message, char * key, char * out) {
    int i, ki;
    
    for (i = 0, ki = 0; i < strlen(message); i++, ki++) {
        if (ki >= strlen(key)) {
            ki = 0;
        }
        snprintf(&out[2 * i], 3, "%02hhx", message[i] ^ key[ki]);
    }
    out[2 * i] = '\0';
}

void decrypt(char * ciphertext, char * key, char * out) {
    int i, ki;
    char c;
    
    for (i = 0, ki = 0; i < strlen(ciphertext) / 2; i++, ki++) {
        if (sscanf(&ciphertext[2 * i], "%02hhx", &c) != 1) {
            break;
        }
        if (ki >= strlen(key)) {
            ki = 0;
        }
        out[i] = c ^ key[ki];
    }
    out[i] = '\0';
}

void hex2bin(char * hex, unsigned char * bin) {
    int i;
    
    i = 0;
    while (sscanf(&hex[2 * i], "%02hhx", &bin[i]) == 1) {
        i++;
    }
    bin[i] = '\0';
}

void bin2hex(unsigned char * bin, char * hex) {
    int i;
    
    for (i = 0; i < strlen(bin); i++) {
        snprintf(&hex[2 * i], 3, "%02hhx", bin[i]);
    }
    hex[2 * i] = '\0';
}

void handle_reg(char * buf) {
    u64 packetId, userId;
    char * key;
    
    packetId = *(u64 *)&buf[0x00];
    userId = *(u64 *)&buf[0x10];
    key = &buf[0x18];
    
    if (strlen(key) < 1) {
        REPLY_STRING(ST_FAIL, "Key too short");
        return;
    }
    if (strlen(key) > 255) {
        REPLY_STRING(ST_FAIL, "Key too long");
        return;
    }
    
    char path[64];
    memset(path, 0, sizeof(path));
    snprintf(path, sizeof(path), "users/%016llx", userId);
    
    int fd = open(path, O_WRONLY | O_CREAT | O_EXCL, 0600);
    if (fd < 0) {
        REPLY_STRING(ST_FAIL, "User exists");
        return;
    }
    
    write(fd, key, strlen(key));
    close(fd);
    
    memset(path, 0, sizeof(path));
    snprintf(path, sizeof(path), "messages/%016llx", userId);
    mkdir(path, 0700);

    REPLY_NUM(ST_SUCCESS, userId);
}

void handle_send(char * buf) {
    u64 packetId, userIdS, userIdR;
    char * message;
    char keyR[256];
    char keyExpR[256];
    
    packetId = *(u64 *)&buf[0x00];
    userIdS = *(u64 *)&buf[0x10];
    userIdR = *(u64 *)&buf[0x18];
    message = &buf[0x20];
    
    if (strlen(message) < 1) {
        REPLY_STRING(ST_FAIL, "Message too short");
        return;
    }
    if (strlen(message) > 255) {
        REPLY_STRING(ST_FAIL, "Message too long");
        return;
    }
    
    int fd;
    char path[64];
    
    memset(path, 0, sizeof(path));
    snprintf(path, sizeof(path), "users/%016llx", userIdS);
    fd = open(path, O_RDONLY);
    if (fd < 0) {
        REPLY_STRING(ST_FAIL, "Sender user does not exist");
        return;
    }
    close(fd);
    
    memset(path, 0, sizeof(path));
    snprintf(path, sizeof(path), "users/%016llx", userIdR);
    fd = open(path, O_RDONLY);
    if (fd < 0) {
        REPLY_STRING(ST_FAIL, "Receiver user does not exist");
        return;
    }
    memset(keyR, 0, sizeof(keyR));
    read(fd, keyR, sizeof(keyR) - 1);
    close(fd);
    
    u64 msgId;
    
    fd = open("/dev/urandom", O_RDONLY);
    if (fd < 0) {
        REPLY_STRING(ST_FAIL, "Can't use source of randomness");
        return;
    }
    read(fd, &msgId, sizeof(msgId));
    close(fd);
    
    char ciphertext[512];
    
    memset(keyExpR, 0, sizeof(keyExpR));
    key_expansion(keyR, msgId, keyExpR);
    memset(ciphertext, 0, sizeof(ciphertext));
    encrypt(message, keyExpR, ciphertext);
    
    memset(path, 0, sizeof(path));
    snprintf(path, sizeof(path), "messages/%016llx/%016llx", userIdR, msgId);
    
    fd = open(path, O_WRONLY | O_CREAT, 0600);
    if (fd < 0) {
        REPLY_STRING(ST_FAIL, "Message creation failed");
        return;
    }
    
    write(fd, ciphertext, strlen(ciphertext));
    close(fd);
    
    REPLY_NUM_STRING(ST_SUCCESS, msgId, ciphertext);
}

void handle_read(char * buf) {
    u64 packetId, userId, msgId;
    char * key;
    char keyR[256];
    char keyExpR[256];
    
    packetId = *(u64 *)&buf[0x00];
    userId = *(u64 *)&buf[0x10];
    msgId = *(u64 *)&buf[0x18];
    key = &buf[0x20];
    
    int fd;
    char path[64];
    
    memset(path, 0, sizeof(path));
    snprintf(path, sizeof(path), "users/%016llx", userId);
    fd = open(path, O_RDONLY);
    if (fd < 0) {
        REPLY_STRING(ST_FAIL, "User does not exist");
        return;
    }
    memset(keyR, 0, sizeof(keyR));
    read(fd, keyR, sizeof(keyR) - 1);
    close(fd);
    
    char ciphertext[512];
    
    memset(path, 0, sizeof(path));
    snprintf(path, sizeof(path), "messages/%016llx/%016llx", userId, msgId);
    fd = open(path, O_RDONLY);
    if (fd < 0) {
        REPLY_STRING(ST_FAIL, "Message does not exist");
        return;
    }
    memset(ciphertext, 0, sizeof(ciphertext));
    read(fd, ciphertext, sizeof(ciphertext) - 1);
    close(fd);
    
    if (strcmp(keyR, key) != 0) {
        REPLY_STRING(ST_FAIL, "Invalid key");
        return;
    }
    
    char message[256];
    
    memset(keyExpR, 0, sizeof(keyExpR));
    key_expansion(key, msgId, keyExpR);
    memset(message, 0, sizeof(message));
    decrypt(ciphertext, keyExpR, message);
    
    REPLY_STRING(ST_SUCCESS, message);
}

void handle_users(char * buf) {
    u64 packetId;
    
    packetId = *(u64 *)&buf[0x00];
    
    DIR * d = opendir("users");
    if (! d) {
        REPLY_STRING(ST_FAIL, "Can't list users");
        return;
    }

    struct dirent * dent;
    long initPos = telldir(d);
    int userCount = 0;
    
    u64 userId;
    
    while (dent = readdir(d)) {
        if (sscanf(dent->d_name, "%016llx", &userId) != 1) {
            continue;
        }
        userCount++;
    }
    
    REPLY_NUM(ST_SUCCESS, userCount);
    
    seekdir(d, initPos);
    
    while (dent = readdir(d)) {
        if (sscanf(dent->d_name, "%016llx", &userId) != 1) {
            continue;
        }
        write(1, &userId, 8);
    }
    
    closedir(d);
}

void handle_msgs(char * buf) {
    u64 packetId, userId;
    
    packetId = *(u64 *)&buf[0x00];
    userId = *(u64 *)&buf[0x10];

    char path[64];
    memset(path, 0, sizeof(path));
    snprintf(path, sizeof(path), "messages/%016llx", userId);
    
    DIR * d = opendir(path);
    if (! d) {
        REPLY_STRING(ST_FAIL, "User doesn't exist");
        return;
    }

    struct dirent * dent;
    long initPos = telldir(d);
    int msgCount = 0;
    
    u64 msgId;
    int fd;
    char ciphertext[512];
    
    while (dent = readdir(d)) {
        if (sscanf(dent->d_name, "%016llx", &msgId) != 1) {
            continue;
        }
        
        memset(path, 0, sizeof(path));
        snprintf(path, sizeof(path), "messages/%016llx/%016llx", userId, msgId);
        fd = open(path, O_RDONLY);
        if (fd < 0) {
            continue;
        }
        close(fd);

        msgCount++;
    }
    
    REPLY_NUM(ST_SUCCESS, msgCount);
    
    seekdir(d, initPos);
    
    while (dent = readdir(d)) {
        if (sscanf(dent->d_name, "%016llx", &msgId) != 1) {
            continue;
        }
        
        memset(path, 0, sizeof(path));
        snprintf(path, sizeof(path), "messages/%016llx/%016llx", userId, msgId);
        fd = open(path, O_RDONLY);
        if (fd < 0) {
            continue;
        }
        memset(ciphertext, 0, sizeof(ciphertext));
        read(fd, ciphertext, sizeof(ciphertext) - 1);
        close(fd);
        
        write(1, &msgId, 8);
        write(1, ciphertext, strlen(ciphertext) + 1);
    }
    
    closedir(d);
}

void handle_about(char * buf) {
    u64 packetId;
    
    packetId = *(u64 *)&buf[0x00];

    REPLY_STRING(ST_SUCCESS, "PUblic Message BoArd - encrypts your messages so you don't have to.");
}

void handle_invalid(char * buf) {
    u64 packetId;
    
    packetId = *(u64 *)&buf[0x00];

    REPLY_STRING(ST_FAIL, "Invalid command");
}

int main() {
    char buf[4096];
    u64 opcode;
    
    while (1) {
        alarm(10);
        memset(buf, 0, sizeof(buf));
        if (read(0, buf, sizeof(buf) - 16) <= 0) {
            return 0;
        }
        
        opcode = *(u64 *)&buf[0x08];
        
        if (opcode == OP_REG) {  // register
            handle_reg(buf);
        } else if (opcode == OP_SEND) {  // send message
            handle_send(buf);
        } else if (opcode == OP_READ) {  // read message
            handle_read(buf);
        } else if (opcode == OP_USERS) {  // list users
            handle_users(buf);
        } else if (opcode == OP_MSGS) {  // list messages
            handle_msgs(buf);
        } else if (opcode == OP_ABOUT) {  // send about message
            handle_about(buf);
        } else {
            handle_invalid(buf);
        }
    }
}
