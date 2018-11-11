#!/usr/bin/env python3

import random
import re
import string
import sys
import socket
import telnetlib

OK, CORRUPT, MUMBLE, DOWN, CHECKER_ERROR = 101, 102, 103, 104, 110
SERVICENAME = "market"
PORT = 10081


def generate_rand(N=16):
    return ''.join(random.choice(string.ascii_letters) for i in range(N))


def close(code, public="", private=""):
    if public:
        print(public)
    if private:
        print(private, file=sys.stderr)
    print('Exit with code {}'.format(code), file=sys.stderr)
    exit(code)


def put(*args):
    team_addr, flag_id, flag = args[:3]
    tn = telnetlib.Telnet(team_addr, PORT, timeout=10)
    username, password = generate_rand(8), generate_rand(8)
    name, description, cost = generate_rand(8), generate_rand(8), random.randint(1, 1000)
    try:
        if not register(tn, username, password):
            close(MUMBLE)
        if not authorize(tn, username, password):
            close(MUMBLE)
        create_good(tn, name, flag, cost, False)
        tn.write(b"\n")
        tn.expect([b"Search goods"], 5)
        close(OK, "{}:{}".format(name, cost))
    except Exception as e:
        close(MUMBLE)


def error_arg(*args):
    close(CHECKER_ERROR, private="Wrong command {}".format(sys.argv[1]))


def info(*args):
    close(OK, "vulns: 1")


def register(tn, username, password):
    try:
        tn.expect([b"User menu"], 5)
        tn.write(b"1\n")
        tn.expect([b"Register"], 5)
        tn.write(b"1\n")
        tn.expect([b"Enter Login"], 5)
        tn.write(username.encode() + b"\n")
        tn.expect([b"Enter Password"], 5)
        tn.write(password.encode() + b"\n")
        (i, obj, res) = tn.expect([b"Press any key"], 5)
        tn.write(b"\n")
        tn.write(b"3\n")
        return True
    except Exception as e:
        close(MUMBLE)


def authorize(tn, username, password):
    try:
        tn.expect([b"User menu"], 5)
        tn.write(b"1\n")
        tn.expect([b"Authenticate"], 5)
        tn.write(b"1\n")
        tn.expect([b"Enter Login"], 5)
        tn.write(username.encode() + b"\n")
        tn.expect([b"Enter Password"], 5)
        tn.write(password.encode() + b"\n")
        (i, obj, res) = tn.expect([b"Press any key"], 5)
        tn.write(b"\n")
        tn.write(b"3\n")
        return True
    except Exception as e:
        close(MUMBLE)


def goods(tn):
    try:
        tn.expect([b"User menu"], 5)
        tn.write(b"2\n")
        tn.expect([b"Market menu"], 5)
        tn.write(b"1\n")
        tn.expect([b"Goods"], 5)
        tn.write(b"\n")
        tn.write(b"4\n")
        return True
    except Exception as e:
        close(MUMBLE)


def create_good(tn, name, description, cost, visible):
    try:
        tn.write(b"3\n")
        tn.expect([b"Bank menu"], 5)
        tn.write(b"2\n")
        tn.expect([b"Enter good name"], 5)
        tn.write(name.encode() + b"\n")
        tn.expect([b"Enter good description"], 5)
        tn.write(description.encode() + b"\n")
        tn.expect([b"Enter good cost"], 5)
        tn.write(str(cost).encode() + b"\n")
        tn.expect([b"Input y if good is visible"], 5)
        if visible:
            tn.write(b"y\n")
        else:
            tn.write(b"n\n")
        tn.expect([b"Success"], 5)
        tn.write(b"\n")

    except Exception as e:
        close(MUMBLE)


def check_good_exists(tn, name):
    tn.expect([b"Bank menu"], 5)
    tn.write(b"3\n")
    tn.expect([b"Market menu"], 5)
    tn.write(b"2\n")
    tn.expect([b"Show goods"], 5)
    tn.write(b"1\n")
    tn.expect([name.encode()], 5)
    tn.write(b"\n")
    tn.expect([b"Search goods"], 5)
    tn.write(b"3\n")
    tn.expect([b"Search goods"], 5)
    tn.write(name.encode() + b"\n")
    tn.expect([name.encode()], 5)
    tn.write(b"\n")
    tn.expect([b"Search goods"], 5)
    tn.write(b"3\n")


def check(*args):
    team_addr = args[0]
    tn = telnetlib.Telnet(team_addr, PORT, timeout=10)
    username = generate_rand(8)
    password = generate_rand(8)
    name, description, cost = generate_rand(8), generate_rand(8), random.randint(1,1000)
    try:
        if not register(tn, username, password):
            close(MUMBLE)
        if not authorize(tn, username, password):
            close(MUMBLE)
        if not goods(tn):
            close(MUMBLE)
        create_good(tn, name, description, cost, True)
        check_good_exists(tn, name)
        tn.expect([b"Search goods"], 5)
        tn.write(generate_rand(8).encode() + b"\n")
        tn.expect([b"No such good"], 5)
        tn.write(b"\n")
        tn.expect([b"Search goods"], 5)
        close(OK)

    except Exception as e:
        close(MUMBLE)


def make_steps(tn, steps):
    for send, exp in steps:
        if send is not None:
            tn.write(send)
        if isinstance(exp, list):
            for e in exp:
                (i, obj, res) = tn.expect([e], 5)
                if i < 0:
                    close(MUMBLE, private=steps)
        else:
            (i, obj, res) = tn.expect([exp], 5)
            if i < 0:
                close(MUMBLE, private=steps)


def get(*args):
    team_addr, lpb, flag = args[:3]
    tn = telnetlib.Telnet(team_addr, PORT, timeout=10)
    username, password = generate_rand(8), generate_rand(8)
    name, cost = map(lambda x: x.encode(), lpb.split(":"))
    try:
        if not register(tn, username, password):
            close(MUMBLE)
        if not authorize(tn, username, password):
            close(MUMBLE)
        make_steps(tn, [
            (None, b"Bank menu"),
            (b"2\n", b"Market menu"),
            (b"1\n", [name, cost]),
            (b"\n", b"Market menu"),
            (b"3\n", b"Search goods"),
            (name + b"\n", name),
            (b"\n", b"Market menu"),
            (b"3\n", b"Search goods"),
            (flag[5:-5].encode() + b"\n", flag.encode()),
            (b"\n", b"Market menu")
        ])
        close(OK)

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
