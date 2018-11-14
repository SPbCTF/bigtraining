import binascii

import re

import fcntl
from consolemenu import *
from consolemenu.items import *
from filelock import FileLock

from crypto import encode_aes, decode_aes, unpad
from graphics import splash, spbctf
from cowpy import cow
from colorama import init
from termcolor import colored


USER = {}
GOODS = {}


def load_goods():
    global GOODS
    with open("items.txt") as r:
        GOODS = {x[0]: {'name': x[0], 'description': x[3], 'cost': int(x[1]), 'visible': int(x[2])} for x in map(lambda x: x.split('|'), filter(lambda x: x, r.read().split('\n')))}


def auth_menu():
    def register():
        print(cow.Moose().milk("Registration"))
        print()
        login = input("Enter Login: > ")
        password = input("Enter Password: > ")
        USER['login'] = login
        USER['password'] = password
        USER['money'] = 100
        print("Created user {} with password {}.\nPress any key.".format(login, '*' * len(password)))
        input()
    def auth():
        print(cow.Moose().milk("Authentication"))
        print()
        login = input("Enter Login: > ")
        if USER.get('login', None) != login:
            print("Invalid login")
            input()
            return
        password = input("Enter Password: > ")
        if USER.get('password', None) != password:
            print("Invalid password")
            input()
            return
        USER['is_auth'] = True
        print("OK. Press any key.")
        input()

    menu = ConsoleMenu("Authentication", splash)
    user_reg = FunctionItem("Register", register)
    user_auth = FunctionItem("Authenticate", auth)
    menu.append_item(user_reg)
    menu.append_item(user_auth)
    menu.show()


def market_menu():
    def show_goods():
        print(cow.Moose().milk("Goods"))
        with open("items.txt") as r:
            print('\n'.join([colored(good[0], 'red') + ["", " — " + good[3]][int(good[2])] + colored(" ({})".format(good[1]), 'green') for good in filter(lambda x: x, map(lambda x: x.split('|'), filter(lambda x: x, r.read().split('\n'))))]))
        input()
    def order_goods():
        print(cow.Moose().milk("Order"))
        print()
        good = input("Enter good name: > ")
        load_goods()
        if good not in GOODS:
            print("No such good")
            input()
            return
        print("Here is your payment token: {}".format(str(binascii.hexlify(encode_aes(GOODS[good])))[2:-1]))
        input()
    def search_goods():
        print(cow.Moose().milk("Search goods"))
        print()
        good = input("Enter good search string: > ")
        with open("items.txt") as r:
            content = r.read()
            results = re.findall(re.compile("^(.*?"+good+".*?)\|.*$", re.MULTILINE), content)
            results2 = re.findall(re.compile("^.*?\|\d+\|\d+\|(.*?" + good + ".*?)$", re.MULTILINE), content)
            if len(results):
                print('\n'.join([colored(res, 'red') for res in results]))
            elif len(results2):
                print('\n'.join([colored(res, 'red') for res in results2]))
            else:
                print("No such good")
        input()
        return

    menu = ConsoleMenu("Market menu", splash)
    show_goods_item = FunctionItem("Show goods", show_goods)
    order_goods_item = FunctionItem("Order goods", order_goods)
    search_goods_item = FunctionItem("Search goods", search_goods)
    menu.append_item(show_goods_item)
    menu.append_item(order_goods_item)
    menu.append_item(search_goods_item)
    menu.show()


def bank_menu():
    def pay_check():
        if not USER.get('login', None):
            print(colored("Unregistered", "red"))
            input()
            return
        load_goods()
        print(cow.Moose().milk("Order"))
        print()
        token = input("Enter payment token: > ")
        _, good = decode_aes(token)
        name, cost, visible = unpad(good.decode())[1:-1].split(",")
        if int(cost) > int(USER['money']):
            print(colored("Not enough money", "red"))
        else:
            print(colored("Successfully bought {} ({})".format(name, GOODS[name]["description"]), "green"))
            USER['money'] -= int(cost)
        input()
        return
    def add_good():
        if not USER.get('login', None):
            print(colored("Unregistered", "red"))
            input()
            return
        print(cow.Moose().milk("Order"))
        print()
        name = input("Enter good name: > ")
        description = input("Enter good description: > ")
        cost = int(input("Enter good cost: > "))
        visible = int(input("Input y if good is visible: > ") == 'y')
        if not re.match(r'^[a-zA-Z0-9 _]+$', name):
            print("Name is invalid")
            input()
            return
        if not re.match(r'^[a-zA-Z0-9 _=]+$', description):
            print("Description is invalid")
            input()
            return

        with open("items.txt", "a") as w:
            fcntl.flock(w, fcntl.LOCK_EX)
            w.write("{}|{}|{}|{}\n".format(name, cost, visible, description))
            fcntl.flock(w, fcntl.LOCK_UN)
        print(colored("Success", "green"))
        input()
        return

    menu = ConsoleMenu("Bank menu", splash)
    pay_check_item = FunctionItem("Buy good", pay_check)
    add_good_item = FunctionItem("Add good", add_good)
    menu.append_item(pay_check_item)
    menu.append_item(add_good_item)
    menu.show()


menu = ConsoleMenu("Amazing shop", splash)
user_menu_item = FunctionItem("User menu", auth_menu)
market_showcase_item = FunctionItem("Market menu", market_menu)
bank_menu_item = FunctionItem("Bank menu", bank_menu)

menu.append_item(user_menu_item)
menu.append_item(market_showcase_item)
menu.append_item(bank_menu_item)

menu.show()
print(spbctf)