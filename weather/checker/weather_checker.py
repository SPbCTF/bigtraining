#!/usr/bin/env python3

import random
import string
import sys
import requests

OK, CORRUPT, MUMBLE, DOWN, CHECKER_ERROR = 101, 102, 103, 104, 110
SERVICENAME = "weather"
PORT = 5000


def generate_rand(N=16):
    return ''.join(random.choice(string.ascii_letters) for i in range(N))


def generate_city():
    return random.choice(['Boksitogorsk', 'Sernur', 'Luchegorsk', 'Uspenskoye', 'Igrim', 'Volot', 'Dalmatovo', 'Kokarevka', 'Amzya', 'Roslavl', 'Bayanday', 'Chuguyevka', 'Khandyga', 'Aleysk', 'Andropov', 'Zelenoborskiy', 'Michurinsk', 'Verkhnyaya Salda', 'Sarmanovo', 'Vozrozhdeniye', 'Valday', 'Chusovoy', 'Muezersky', 'Udomlya', 'Asekeyevo', 'Valentin', 'Borovsk', 'Komsomolske', 'Kolomna', 'Verkhnyaya Tura', 'Venyov', 'Varnavino', 'Kyra', 'Malmyzh', 'Baley', 'Kazan', 'Koygorodok', 'Anna', 'Kresttsy', 'Tilichiki', 'Novovoronezh', 'Panino', 'Dorogobuzh', 'Starodub', 'Elektrougli', 'Sergiyev Posad', 'Artemovskiy', 'Sedelnikovo', 'Podporozhye', 'Grozny', 'Suntar', 'Ust-Kalmanka', 'Gorodishche', 'Vurnary', 'Okhotsk', 'Alapayevsk', 'Alekseyevka', 'Sharya', 'Znamensk', 'Andreyevo', 'Erzin', 'Izvestkovy', 'Kataysk', 'Iglino', 'Vizinga', 'Anzhero-Sudzhensk', 'Obluchye', 'Srednyaya Akhtuba', 'Leninsk-Kuznetskiy', 'Pitelino', 'Poyarkovo', 'Lavrentiya', 'Glazunovka', 'Beloretsk', 'Raychikhinsk', 'Iksha', 'Novospasskoye', 'Puchezh', 'Nekrasovka', 'Snezhnogorsk', 'Zeblyaki', 'Bogotol', 'Tlyarata', 'Radovitskiy', 'Shagonar', 'Kasimov', 'Zverevo', 'Ukhta', 'Isyangulovo', 'Vasilyevo', 'Sochi', 'Shilka', 'Sayansk', 'Ust-Kamchatsk', 'Novosil', 'Gubkinskiy', 'Ramenskoe', 'Zherdevka', 'Debesy', 'Drezna'])


def generate_weather():
    return random.choice(['rainy', 'sunny', 'windy', 'cloudy', 'foggy', 'gloomy', 'misty', 'hazy'])


def close(code, public="", private=""):
    if public:
        print(public)
    if private:
        print(private, file=sys.stderr)
    print('Exit with code {}'.format(code), file=sys.stderr)
    exit(code)


def put(*args):
    team_addr, flag_id, flag = args[:3]
    s = requests.Session()
    try:
        r = s.get("http://{}:{}/".format(team_addr, PORT))

        login, password, city = generate_rand(), generate_rand(), generate_rand()

        if r.status_code != 200:
            close(CORRUPT, 'Status code is not 200')

        r = s.get("http://{}:{}/register".format(team_addr, PORT))

        r = s.post("http://{}:{}/register".format(team_addr, PORT), {
            "login": login,
            "password": password
        })

        r = s.post("http://{}:{}/login".format(team_addr, PORT), {
            "login": login,
            "password": password
        })

        if not 'Your forecasts' in r.text:
            close(CORRUPT, 'Invalid layout in cabinet')

        r = s.post("http://{}:{}/cabinet".format(team_addr, PORT), {
            "city": city,
            "weather": flag,
            'day': random.randint(-20, 20),
            'night': random.randint(-20, 20),
            'official': 1
        })

        if flag not in r.text:
            close(CORRUPT, 'Flag is not in the cabinet')

        close(OK, "{}:{}".format(login, password))

    except Exception as e:
        close(MUMBLE, "PUT Failed")


def error_arg(*args):
    close(CHECKER_ERROR, private="Wrong command {}".format(sys.argv[1]))


def info(*args):
    close(OK, "vulns: 1")


def check(*args):
    team_addr = args[0]

    s = requests.Session()
    try:
        login, password, city, city2, flag, flag2 = generate_rand(), generate_rand(), generate_city(), generate_city(), generate_rand(), generate_weather()

        r = s.post("http://{}:{}/register".format(team_addr, PORT), {
            "login": login,
            "password": password
        })

        r = s.post("http://{}:{}/login".format(team_addr, PORT), {
            "login": login,
            "password": password
        })

        if not 'Your forecasts' in r.text:
            close(MUMBLE, 'Invalid layout in cabinet')

        r = s.post("http://{}:{}/cabinet".format(team_addr, PORT), {
            "city": city,
            "weather": flag,
            'day': random.randint(-20, 20),
            'night': random.randint(-20, 20),
            'official': 1
        })

        if flag not in r.text:
            close(CORRUPT, 'Flag is not in the cabinet')

        r = s.post("http://{}:{}/cabinet".format(team_addr, PORT), {
            "city": city2,
            "weather": flag2,
            'day': random.randint(-20, 20),
            'night': random.randint(-20, 20),
            'official': 0
        })

        if flag2 not in r.text:
            close(CORRUPT, 'Flag is not in the cabinet')

        r = s.post("http://{}:{}/search".format(team_addr, PORT), {
            "city": city
        })

        if flag not in r.text:
            close(CORRUPT, 'Flag is not in the search')

        r = s.post("http://{}:{}/search".format(team_addr, PORT), {
            "city": city2
        })

        if flag2 not in r.text:
            close(CORRUPT, 'Flag is not in the search')

        close(OK)

    except Exception as e:
        close(MUMBLE)


def get(*args):
    team_addr, lpb, flag = args[:3]

    s = requests.Session()
    try:
        login, password = lpb.split(":")

        r = s.post("http://{}:{}/login".format(team_addr, PORT), {
            "login": login,
            "password": password
        })

        if not 'Your forecasts' in r.text:
            close(CORRUPT, 'Invalid layout in cabinet')

        if flag not in r.text:
            close(CORRUPT, 'Flag is not in the cabinet')

        close(OK, "{}:{}".format(login, password))

    except Exception as e:
        close(CORRUPT)


def init(*args):
    close(OK)


COMMANDS = {
    'put': put,
    'check': check,
    'get': get,
    'info': info,
    'init': init
}


if __name__ == '__main__':
    try:
        COMMANDS.get(sys.argv[1], error_arg)(*sys.argv[2:])
    except Exception as ex:
        close(CHECKER_ERROR, private="INTERNAL ERROR: {}".format(ex))
