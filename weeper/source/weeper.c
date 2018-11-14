#define _DEFAULT_SOURCE

#include <stdio.h>
#include <stdlib.h>
#include <termios.h>
#include <unistd.h>
#include <signal.h>
#include <string.h>
#include <time.h>
#include <dirent.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/select.h>

#define KEY_UP (-0x101)
#define KEY_DOWN (-0x102)
#define KEY_LEFT (-0x103)
#define KEY_RIGHT (-0x104)
#define KEY_PAGEUP (-0x105)
#define KEY_PAGEDN (-0x106)
#define KEY_HOME (-0x107)
#define KEY_END (-0x108)
#define KEY_ESC (-0x109)
#define KEY_ENTER '\n'
#define KEY_SPACE ' '

#define STR_DASHES "----------------------------------------------------------------------------------------------------"

// #define sleep(x) 0
// #define select(x,y,z,a,b) 0

struct xy {
    char x;
    char y;
};

int we_have_telnet = 0;
int raw_enabled = -1;

struct xy moves[256] = {{0, 0}};
char field[4096] = "";
char visible[4096] = "";
char prize[64] = "";
int width = 0, height = 0, mines = 0, squaresLeft = 0;

#define FLD(x, y) field[((y) - 1) * width + ((x) - 1)]
#define VIS(x, y) visible[((y) - 1) * width + ((x) - 1)]

void clear_screen() {
    printf("\x1B[3J\x1B[H\x1B[2J");
}

void raw_enable() {
    if (raw_enabled == 1) {
        return;
    }
    
    raw_enabled = 1;
    
    struct termios raw;
    if (we_have_telnet || tcgetattr(STDIN_FILENO, &raw) != 0) {
        printf("\xFF\xFB\x01" "\xFF\xFB\x03" "\xFF\xFC\x22");
        return;
    }
    raw.c_lflag &= ~(ECHO | ICANON);
    tcsetattr(STDIN_FILENO, TCSAFLUSH, &raw);
}

void __attribute__((destructor)) raw_disable() {
    if (raw_enabled == 0) {
        return;
    }
    
    raw_enabled = 0;
    
    struct termios raw;
    if (we_have_telnet || tcgetattr(STDIN_FILENO, &raw) != 0) {
        printf("\xFF\xFC\x01" "\xFF\xFC\x03" "\xFF\xFB\x22");
        if (we_have_telnet) {
            if (getchar() == 0xFF) { 
                getchar(); getchar();
                if (getchar() == 0xFF) { 
                    getchar(); getchar();
                    if (getchar() == 0xFF) {
                        getchar(); getchar();
                    }
                }
            }
        }
        return;
    }
    raw.c_lflag |= (ECHO | ICANON);
    tcsetattr(STDIN_FILENO, TCSAFLUSH, &raw);
}

void signal_handler(int signal) {
    struct termios raw;
    if (signal == SIGALRM) {
        printf("Timed Out!\r\n");
        if (! we_have_telnet && tcgetattr(STDIN_FILENO, &raw) != 0) {
            printf("\r\nBest played in telnet\r\n");
        }
    }
    we_have_telnet = 0;
    raw_disable();
    exit(1);
}

int read_key() {
    alarm(10);
    
    int key = getchar();
    if (key == EOF) {
        exit(1);
    }
    
    if (key == 0xFF) {
        we_have_telnet = 1;
        getchar();
        getchar();
        
        return read_key();
    }
    
    if (key == 27) {
        key = getchar();
        if (key == 27) {
            return KEY_ESC;
        } else if (key == 91) {
            key = getchar();
            if (key == 65) {
                return KEY_UP;
            } else if (key == 68) {
                return KEY_LEFT;
            } else if (key == 66) {
                return KEY_DOWN;
            } else if (key == 67) {
                return KEY_RIGHT;
            } else if (key == 53) {
                getchar();
                return KEY_PAGEUP;
            } else if (key == 54) {
                getchar();
                return KEY_PAGEDN;
            } else if (key == 49) {
                getchar();
                return KEY_HOME;
            } else if (key == 52) {
                getchar();
                return KEY_END;
            } else if (key == 72) {
                return KEY_HOME;
            } else if (key == 70) {
                return KEY_END;
            } else {
                return 27 * 1000000 + 91 * 1000 + key;
            }
        } else {
            return 27 * 1000 + key;
        }
    }
    
    if (key == '\r') {
        getchar();
        return KEY_ENTER;
    }
    
    return key;
}

int read_allowed_key(int * allowed) {
    int key, i;
    
    while (1) {
        key = read_key();
        
        for (i = 0; allowed[i] != 0; i++) {
            if (key == allowed[i]) {
                return key;
            }
        }
    }
}

int menu(char * prompt, char ** choices) {
    int curChoice, menuWidth, len, i, numChoices, key;
    char * tokPrompt, * linePtr;
    
    menuWidth = 0;
    numChoices = 0;
    
    tokPrompt = strdup(prompt);
    linePtr = strtok(tokPrompt, "\n");
    while (linePtr) {
        len = strlen(linePtr) + 8;
        if (len > menuWidth) {
            menuWidth = len;
        }
        linePtr = strtok(NULL, "\n");
    }
    free(tokPrompt);
    
    for (i = 0; choices[i]; i++) {
        numChoices++;
        len = strlen(choices[i]) + 11;
        if (len > menuWidth) {
            menuWidth = len;
        }
    }
    
    clear_screen();
    
    printf("+%.*s+\r\n", menuWidth - 2, STR_DASHES);
    printf("|%*s|\r\n", menuWidth - 2, "");
    
    tokPrompt = strdup(prompt);
    linePtr = strtok(tokPrompt, "\n");
    while (linePtr) {
        printf("|  %-*s    |\r\n", menuWidth - 8, linePtr);
        linePtr = strtok(NULL, "\n");
    }
    free(tokPrompt);
    
    printf("|%*s|\r\n", menuWidth - 2, "");
    
    for (i = 0; choices[i]; i++) {
        printf("|     %-*s    |\r\n", menuWidth - 11, choices[i]);
    }

    printf("|%*s|\r\n", menuWidth - 2, "");
    printf("+%.*s+\r\n", menuWidth - 2, STR_DASHES);

    curChoice = 0;
    raw_enable();
    while (1) {
        printf("\x1B[%dA\x1B[%dC=>\r\x1B[%dB", 2 + numChoices - curChoice, 3, 2 + numChoices - curChoice);
        key = read_allowed_key((int[]){KEY_UP, KEY_DOWN, KEY_PAGEUP, KEY_PAGEDN, KEY_HOME, KEY_END, 'q', KEY_ENTER, 0});
        if (key == 'q') {
            exit(1);
        }
        if (key == KEY_ENTER) {
            break;
        }
        printf("\x1B[%dA\x1B[%dC  \r\x1B[%dB", 2 + numChoices - curChoice, 3, 2 + numChoices - curChoice);
        if (key == KEY_UP) {
            if (curChoice > 0) {
                curChoice--;
            } else if (curChoice == 0) {
                curChoice = numChoices - 1;
            }
        } else if (key == KEY_DOWN) {
            if (curChoice < numChoices - 1) {
                curChoice++;
            } else if (curChoice == numChoices - 1) {
                curChoice = 0;
            }
        } else if (key == KEY_PAGEUP || key == KEY_HOME) {
            curChoice = 0;
        } else if (key == KEY_PAGEDN || key == KEY_END) {
            curChoice = numChoices - 1;
        }
    }
    raw_disable();
    
    return curChoice;
}

int count_around(char * field, int x, int y, char type) {
    int minX, maxX, minY, maxY, tx, ty, count;
    
    minX = x - 1;
    if (minX <= 0) {
        minX = 1;
    }
    maxX = x + 1;
    if (maxX > width) {
        maxX = width;
    }
    minY = y - 1;
    if (minY <= 0) {
        minY = 1;
    }
    maxY = y + 1;
    if (maxY > height) {
        maxY = height;
    }
    
    count = 0;
    for (tx = minX; tx <= maxX; tx++) {
        for (ty = minY; ty <= maxY; ty++) {
            if (tx == x && ty == y) {
                continue;
            }
            if (field[((ty) - 1) * width + ((tx) - 1)] == type) {
                count++;
            }
        }
    }
    
    return count;
}

void generate_field() {
    srand(time(NULL) + getpid() + getppid());
    
    memset(field, '0', width * height);
    
    int i, x, y, minX, maxX, minY, maxY, tx, ty;
    
    for (i = 0; i < mines; i++) {
        x = rand() % width + 1;
        y = rand() % height + 1;
        if (FLD(x, y) == '*') {
            i--;
            continue;
        }
        FLD(x, y) = '*';
        
        minX = x - 1;
        if (minX <= 0) {
            minX = 1;
        }
        maxX = x + 1;
        if (maxX > width) {
            maxX = width;
        }
        minY = y - 1;
        if (minY <= 0) {
            minY = 1;
        }
        maxY = y + 1;
        if (maxY > height) {
            maxY = height;
        }
        
        for (tx = minX; tx <= maxX; tx++) {
            for (ty = minY; ty <= maxY; ty++) {
                if (FLD(tx, ty) != '*') {
                    FLD(tx, ty)++;
                }
            }
        }
    }
}

int open_square(int x, int y) {
    int i;
    
    if (VIS(x, y) != '_') {
        return 0;
    }
    
    squaresLeft--;
    
    if (FLD(x, y) == '*') {
        printf("WW\x1B[2D");
        return -1;
    }
    
    if (FLD(x, y) == '0') {
        VIS(x, y) = ' ';
    } else {
        VIS(x, y) = FLD(x, y);
    }
    
    if (FLD(x, y) == '0') {
        if (y > 1) {
            if (x > 1 && VIS(x - 1, y - 1) == '_') { printf("\x1B[1A\x1B[2D"); if (open_square(x - 1, y - 1) == -1) return -1; printf("\x1B[1B\x1B[2C"); }
            if (VIS(x, y - 1) == '_') { printf("\x1B[1A"); if (open_square(x, y - 1) == -1) return -1; printf("\x1B[1B"); }
            if (x < width && VIS(x + 1, y - 1) == '_') { printf("\x1B[1A\x1B[2C"); if (open_square(x + 1, y - 1) == -1) return -1; printf("\x1B[1B\x1B[2D"); }
        }
        if (x > 1 && VIS(x - 1, y) == '_') { printf("\x1B[2D"); if (open_square(x - 1, y) == -1) return -1; printf("\x1B[2C"); }
        if (x < width && VIS(x + 1, y) == '_') { printf("\x1B[2C"); if (open_square(x + 1, y) == -1) return -1; printf("\x1B[2D"); }
        if (y < height) {
            if (x > 1 && VIS(x - 1, y + 1) == '_') { printf("\x1B[1B\x1B[2D"); if (open_square(x - 1, y + 1) == -1) return -1; printf("\x1B[1A\x1B[2C"); }
            if (VIS(x, y + 1) == '_') { printf("\x1B[1B"); if (open_square(x, y + 1) == -1) return -1; printf("\x1B[1A"); }
            if (x < width && VIS(x + 1, y + 1) == '_') { printf("\x1B[1B\x1B[2C"); if (open_square(x + 1, y + 1) == -1) return -1; printf("\x1B[1A\x1B[2D"); }
        }
    }

    printf("%c \x1B[2D", VIS(x, y));
    
    return 0;
}

void play() {
    int i, x, y, key, flagCount;
    fd_set fdset;
    struct timeval tm;
    char clubPass[64], input[64];
    FILE * f;
    
    memset(moves, 0, sizeof(moves));
    memset(visible, '_', width * height);

    squaresLeft = 0;

    clear_screen();
    
    printf("If you're VIP, enter your VIP club pass (7): ");
    for (i = 6; i >= 0; i--) {
        FD_ZERO(&fdset);
        FD_SET(0, &fdset);
        tm.tv_sec = 1;
        tm.tv_usec = 0;
        if (select(1, &fdset, NULL, &fdset, &tm)) {
            break;
        }
        printf("\x1b[s\x1b[1;42H%d\x1b[u", i);
    }
    if (i < 0) {
        clear_screen();
    } else {
        alarm(10);
        fgets(input, sizeof(input), stdin);
        if (input[strlen(input) - 1] == '\n') {
            input[strlen(input) - 1] = '\0';
        }
        if (input[strlen(input) - 1] == '\r') {
            input[strlen(input) - 1] = '\0';
        }
        
        f = fopen(".checker-clubpass", "r");
        if (f) {
            fgets(clubPass, sizeof(clubPass), f);
            if (clubPass[strlen(clubPass) - 1] == '\n') {
                clubPass[strlen(clubPass) - 1] = '\0';
            }
            fclose(f);
            
            if (strcmp(input, clubPass) != 0) {
                menu("Bad VIP club pass!", (char *[]){"Exit", NULL});
                return;
            }
        }
        printf("Enter new club pass: ");
        alarm(10);
        fgets(input, sizeof(input), stdin);
        if (input[strlen(input) - 1] == '\n') {
            input[strlen(input) - 1] = '\0';
        }
        if (input[strlen(input) - 1] == '\r') {
            input[strlen(input) - 1] = '\0';
        }
        
        f = fopen(".checker-clubpass", "w");
        fprintf(f, "%s\n", input);
        fclose(f);
        
        printf("Club pass saved\n");
        
        clear_screen();
        printf("%*c%s\n", width * 2 + 5, ' ', "        ");
        printf("%*c%s\n", width * 2 + 5, ' ', " o () o ");
        printf("%*c%s\n", width * 2 + 5, ' ', " |\\/\\/| ");
        printf("%*c%s\n", width * 2 + 5, ' ', " `````` ");
        printf("%*c%s\n", width * 2 + 5, ' ', "| O  O |");
        printf("%*c%s\n", width * 2 + 5, ' ', "|  L   |");
        printf("%*c%s\n", width * 2 + 5, ' ', " \\_u__/ ");
        printf("%*c%s\n", width * 2 + 5, ' ', "        ");
        printf("%*c%s\n", width * 2 + 5, ' ', " V I P  ");
        printf("%*c%s\n", width * 2 + 5, ' ', " Weeper ");
        printf("\x1b[1;1H");
    }
    
    char * strDashes = (char *)malloc(width * 2 + 1);
    memset(strDashes, '-', width * 2);
    strDashes[width * 2] = '\0';
    
    printf("+%s+\r\n", strDashes);
    for (y = 1; y <= height; y++) {
        printf("|");
        for (x = 1; x <= width; x++) {
            printf("%c ", VIS(x, y));
            if (FLD(x, y) != '*') {
                squaresLeft++;
            }
        }
        printf("|\r\n");
    }
    printf("+%s+\r\n", strDashes);
    
    free(strDashes);
    
    x = width / 2 + 1;
    y = height / 2 + 1;
    
    raw_enable();
    printf("\x1B[%dA\x1B[%dC", 2 + height - y, -1 + x * 2);
    while (1) {
        key = read_allowed_key((int[]){KEY_UP, KEY_DOWN, KEY_LEFT, KEY_RIGHT, KEY_PAGEUP, KEY_PAGEDN, KEY_HOME, KEY_END, 'q', KEY_ESC, 's', 'f', KEY_SPACE, KEY_ENTER, 0});
        if (key == 'q') {
            printf("\r\x1B[%dB", 2 + height - y);
            exit(1);
        } else if (key == KEY_ESC || key == 's') {
            printf("\r\x1B[%dB", 2 + height - y);
            
            if (key == 's' && moves[0].x != 0) {
                printf("\r\nYour saved game: ");
                for (i = 0; moves[i].x != 0; i++) {
                    printf("(%d, %d)%s", moves[i].x, moves[i].y, moves[i + 1].x != 0 ? ", " : "");
                }
                printf("\r\n");
                sleep(2);
                
                menu("Game saved.", (char *[]){"OK", NULL});
                return;
            }
            
            raw_disable();
            return;
        }
        if (key == KEY_UP) {
            if (y > 1) {
                y--;
                printf("\x1B[1A");
            } else {
                y = height;
                printf("\x1B[%dB", height - 1);
            }
        } else if (key == KEY_DOWN) {
            if (y < height) {
                y++;
                printf("\x1B[1B");
            } else {
                y = 1;
                printf("\x1B[%dA", height - 1);
            }
        } else if (key == KEY_LEFT) {
            if (x > 1) {
                x--;
                printf("\x1B[2D");
            } else {
                x = width;
                printf("\x1B[%dC", width * 2 - 2);
            }
        } else if (key == KEY_RIGHT) {
            if (x < width) {
                x++;
                printf("\x1B[2C");
            } else {
                x = 1;
                printf("\x1B[%dD", width * 2 - 2);
            }
        } else if (key == KEY_PAGEUP) {
            if (y > 1) {
                printf("\x1B[%dA", y - 1);
                y = 1;
            }
        } else if (key == KEY_PAGEDN) {
            if (y < height) {
                printf("\x1B[%dB", height - y);
                y = height;
            }
        } else if (key == KEY_HOME) {
            if (x > 1) {
                printf("\x1B[%dD", (x - 1) * 2);
                x = 1;
            }
        } else if (key == KEY_END) {
            if (x < width) {
                printf("\x1B[%dC", (width - x) * 2);
                x = width;
            }
        } else if (key == KEY_SPACE || key == 'f') {
            if (VIS(x, y) == 'F') {
                VIS(x, y) = '_';
                printf("_\x1B[1D");
            } else if (VIS(x, y) == '_') {
                VIS(x, y) = 'F';
                printf("F\x1B[1D");
            }
        } else if (key == KEY_ENTER) {
            if (VIS(x, y) == '_') {
                for (i = 0; moves[i].x != 0; i++) { }
                if (i < 256) {
                    moves[i].x = x;
                    moves[i].y = y;
                }
                
                if (open_square(x, y) == -1) {
                    printf("\r\x1B[%dB", 2 + height - y);
                    sleep(1);
                    menu("KABOOOOM!\n\nGame Over.", (char *[]){"OK", NULL});
                    
                    raw_disable();
                    return;
                }
            } else if (VIS(x, y) >= '1' && VIS(x, y) <= '9') {
                for (i = 0; moves[i].x != 0; i++) { }
                if (i < 256) {
                    moves[i].x = x;
                    moves[i].y = y;
                }
                
                if (count_around(visible, x, y, 'F') == (VIS(x, y) - '0')) {
                    i = VIS(x, y);
                    FLD(x, y) = '0';
                    VIS(x, y) = '_';
                    squaresLeft++;
                    if (open_square(x, y) == -1) {
                        printf("\r\x1B[%dB", 2 + height - y);
                        sleep(1);
                        menu("KABOOOOM!\n\nGame Over.", (char *[]){"OK", NULL});
                        
                        raw_disable();
                        return;
                    }
                    FLD(x, y) = i;
                    VIS(x, y) = i;
                    printf("%c \x1B[2D", VIS(x, y));
                }
            }
            
            if (squaresLeft <= 0) {
                printf("\r\x1B[%dB", 2 + height - y);
                sleep(1);
                menu("Y O U   W I N   ! ! !", (char *[]){"Your prize:", prize, NULL});
                
                raw_disable();
                return;
            }
        }
    }
}

void play_quick_game() {
    int choice;
    
    choice = menu("Select difficulty:", (char *[]){"Easy", "Medium", "Hard", "Insane", "<- back", NULL});
    
    if (choice == 0) {
        width = 9;
        height = 9;
        mines = 10;
        strcpy(prize, "A high-five");
    } else if (choice == 1) {
        width = 16;
        height = 16;
        mines = 40;
        strcpy(prize, "Friendly handshake");
    } else if (choice == 2) {
        width = 30;
        height = 16;
        mines = 99;
        strcpy(prize, "Respectful bow");
    } else if (choice == 3) {
        width = 48;
        height = 32;
        mines = 512;
    } else if (choice == 4) {
        return;
    }
    
    generate_field();
    play();
}

int sort_by_time(const struct dirent ** a, const struct dirent ** b) {
    struct stat sta, stb;
    
    if (stat((*a)->d_name, &sta) != 0 || stat((*b)->d_name, &stb) != 0) {
        return 0;
    }
    
    return stb.st_mtime - sta.st_mtime;
}

int filter_by_dot(const struct dirent * d) {
    return d->d_name[0] != '.';
}

void play_homebrew_game() {
    struct dirent **namelist;
    struct stat st;
    time_t tm;
    int count, i, choice;
    char * options[33];
    char name[64], * gamename;
    
    chdir("homebrew");
    count = scandir(".", &namelist, filter_by_dot, sort_by_time);
    chdir("..");
    for (i = 0; i < 30 && i < count; i++) {
        options[i] = namelist[i]->d_name;
    }
    options[i++] = "[+] More...";
    options[i++] = "<- back";
    options[i++] = NULL;
    
    choice = menu("Choose Homebrew game to play", options);
    
    if (options[choice] == "<- back") {
        return;
    } else if (options[choice] == "[+] More...") {
        printf("Newest and freshest homebrew games: ");
        tm = time(NULL);
        chdir("homebrew");
        for (i = 0; i < count; i++) {
            if (stat(namelist[i]->d_name, &st) != 0 || tm - st.st_mtime > 15 * 60) {
                break;
            }
            printf("%s, ", namelist[i]->d_name);
        }
        chdir("..");
        printf("\r\n\r\n");
        
        printf("Name of the game: ");
        alarm(10);
        fgets(name, sizeof(name), stdin);
        if (name[strlen(name) - 1] == '\n') {
            name[strlen(name) - 1] = '\0';
        }
        if (name[strlen(name) - 1] == '\r') {
            name[strlen(name) - 1] = '\0';
        }
        for (i = 0; name[i]; i++) {
            if (!((name[i] >= 'a' && name[i] <= 'z') || (name[i] >= 'A' && name[i] <= 'Z') || (name[i] >= '0' && name[i] <= '9') || name[i] == '-' || name[i] == '_')) {
                menu("Bad characters in name!", (char *[]){"OK", NULL});
                return;
            }
        }
        
        gamename = strdup(name);
    } else {
        gamename = strdup(options[choice]);
    }
    
    for (i = 0; i < count; i++) {
        free(namelist[i]);
    }
    free(namelist);
    
    chdir("homebrew");
    FILE * f = fopen(gamename, "r");
    chdir("..");
    
    free(gamename);
    
    if (! f) {
        menu("Can't open game file!", (char *[]){"OK", NULL});
        return;
    }
    if (fscanf(f, "%dx%dx%d\n%4096s\n%64s", &width, &height, &mines, field, prize) != 5) {
        menu("Can't read file!", (char *[]){"OK", NULL});
        return;
    }
    fclose(f);
    
    play();
}

void upload_homebrew_game() {
    char name[64], buf[64];
    int i, x, y, minX, maxX, minY, maxY, tx, ty;
    
    printf("Name of the game: ");
    alarm(10);
    fgets(name, sizeof(name), stdin);
    if (name[strlen(name) - 1] == '\n') {
        name[strlen(name) - 1] = '\0';
    }
    if (name[strlen(name) - 1] == '\r') {
        name[strlen(name) - 1] = '\0';
    }
    for (i = 0; name[i]; i++) {
        if (!((name[i] >= 'a' && name[i] <= 'z') || (name[i] >= 'A' && name[i] <= 'Z') || (name[i] >= '0' && name[i] <= '9') || name[i] == '-' || name[i] == '_')) {
            menu("Bad characters in name!", (char *[]){"OK", NULL});
            return;
        }
    }
    
    chdir("homebrew");
    if (fopen(name, "r") != NULL) {
        menu("Already exists!", (char *[]){"OK", NULL});
        return;
    }
    chdir("..");
    
    printf("Field size (WxHxM): ");
    alarm(10);
    if (scanf("%dx%dx%d", &width, &height, &mines) != 3) {
        return;
    }
    if (width < 9 || width > 1000 || height < 9 || height > 1000 || (width * height) >= 4096) {
        fgets(buf, sizeof(buf), stdin);
        menu("Bad field size!", (char *[]){"OK", NULL});
        return;
    }
    if (mines < 1 || mines > 999 || mines >= width * height) {
        fgets(buf, sizeof(buf), stdin);
        menu("Bad mine count!", (char *[]){"OK", NULL});
        return;
    }
    
    memset(field, '0', width * height);
    
    printf("Enter %d mines:   x,y x,y x,y ...\r\n", mines);
    for (i = 0; i < mines; i++) {
        alarm(10);
        if (scanf("%d,%d", &x, &y) != 2) {
            return;
        }
        if (x < 1 || x > width || y < 1 || y > height) {
            fgets(buf, sizeof(buf), stdin);
            menu("Bad mine position!", (char *[]){"OK", NULL});
            return;
        }
        if (FLD(x, y) == '*') {
            fgets(buf, sizeof(buf), stdin);
            menu("Duplicate mine!", (char *[]){"OK", NULL});
            return;
        }
        FLD(x, y) = '*';
        
        minX = x - 1;
        if (minX <= 0) {
            minX = 1;
        }
        maxX = x + 1;
        if (maxX > width) {
            maxX = width;
        }
        minY = y - 1;
        if (minY <= 0) {
            minY = 1;
        }
        maxY = y + 1;
        if (maxY > height) {
            maxY = height;
        }
        
        for (tx = minX; tx <= maxX; tx++) {
            for (ty = minY; ty <= maxY; ty++) {
                if (FLD(tx, ty) != '*') {
                    FLD(tx, ty)++;
                }
            }
        }
    }
    fgets(buf, sizeof(buf), stdin);
    
    printf("Enter trophy for the winner: ");
    alarm(10);
    fgets(prize, sizeof(prize), stdin);
    if (prize[strlen(prize) - 1] == '\n') {
        prize[strlen(prize) - 1] = '\0';
    }
    if (prize[strlen(prize) - 1] == '\r') {
        prize[strlen(prize) - 1] = '\0';
    }
    if (strlen(prize) < 1) {
        menu("Trophy is empty!", (char *[]){"OK", NULL});
        return;
    }
    
    chdir("homebrew");
    FILE * f = fopen(name, "w");
    chdir("..");
    fprintf(f, "%dx%dx%d\n%.4096s\n%s\n", width, height, mines, field, prize);
    fclose(f);
    
    menu("Homebrew game saved!", (char *[]){"OK", NULL});
}

int main() {
    signal(SIGALRM, signal_handler);
    signal(SIGINT, signal_handler);
    
    setvbuf(stdout, NULL, _IONBF, 0);
    
    int choice;

    while (1) {
        choice = menu("Welcome to Weeper!\nWhat are you going to do today?", (char *[]){"Play Quick Game", "Play Homebrew Game", "Upload Homebrew Game", "Quit", NULL});
    
        if (choice == 3) {
            exit(0);
        } else if (choice == 0) {
            play_quick_game();
        } else if (choice == 1) {
            play_homebrew_game();
        } else if (choice == 2) {
            upload_homebrew_game();
        }
    }
}
