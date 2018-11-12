#!/usr/bin/env python2

import requests,re,md5,sys,string,random

cmd = sys.argv[1]
IP = sys.argv[2]
url="http://"+IP+":2145/"

def id_gen(size=6, chars=string.ascii_uppercase + string.digits):
    return ''.join(random.choice(chars) for _ in range(size))

def getpay():
    while 1:
        s = id_gen(10)
        m = md5.new()
        m.update(s)
        h=m.digest()
        if h[0]=='\x01' and h[1] == '\x37':
            return s

if cmd == "check":
    s = requests.Session()
    r = s.get(url)
    if not "We pays money" in r.text:
        exit(103)
    exit(101)
elif cmd == "put":
    flag_id = sys.argv[3]
    flag = sys.argv[4]
    s1 = requests.Session()
    s2 = requests.Session()
    u1,p1,u2,p2 = (id_gen(12),id_gen(12),id_gen(12),id_gen(12))
    s1.post(url+"register",data={'login':u1,'password':p1,'pay':getpay()})
    s2.post(url+"register",data={'login':u2,'password':p2,'pay':getpay()})
    s1.post(url+'sendmoney',data=dict(to_login=u2,to_money=1,to_message=flag))
    s2.post(url+'sendmoney',data=dict(to_login=u1,to_money=1,to_message=flag))
    r1=s1.get(url)
    r2=s2.get(url)
    if not "Logged in as "+u2+" (1)" in r2.text:
        print r2.text
        print "Logged in as "+u2+" (1)"
        exit(102)
    if not "Logged in as "+u1+" (1)" in r1.text:
        print r1.text
        exit(102)
    print ":".join([u1,p1,u2,p2])
    exit(101)
elif cmd == "get":
    flag_id = sys.argv[3]
    flag = sys.argv[4]
    (u1,p1,u2,p2) = flag_id.split(":")
    s1 = requests.Session()
    s1.post(url+"login",data={'login':u1,'password':p1})
    r=s1.get(url)
    if not flag in r.text:
        exit(102)
    r=s1.get(url+"/userlist")
    if not u1 in r.text:
        exit(102)
    exit(101)
