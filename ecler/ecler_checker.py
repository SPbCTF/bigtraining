#!/usr/bin/env python3
import requests,sys,os
import string,random,re,shutil

def id_gen(size=6, chars=string.ascii_uppercase + string.digits):
    return ''.join(random.choice(chars) for _ in range(size))

conv_template = """
push graphic-context
viewbox 0 0 640 320
image Add 0,0,640,320 '%s'
push graphic-context
        font-size 40
        fill 'black'
        stroke-width 1
        text 0,300 '%s'
pop graphic-context
pop graphic-context
"""
ua='Mozilla/5.0 (Linux; Android 6.0.1; SM-G935S Build/MMB29K; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/55.0.2883.91 Mobile Safari/537.36'
cmd = sys.argv[1]
ip=sys.argv[2]
url = "http://"+ip+":3125"
s = requests.Session()
if cmd == "check":
    r=s.get(url)
    if not "<span>Images</span>" in r.text:
        exit(102)
    r=s.get(url+"/reg")
    if not "<span>Register</span>" in r.text:
        exit(102)
    login,password = (id_gen(12),id_gen(12))
    r=s.post(url+"/reg",data={'login':login,'password':password})
    if not 'session' in s.cookies:
        exit(102)
    s.get(url+"/logout")
    if 'session' in r.cookies:
        exit(102)
    exit(101)
elif cmd == "put":
    flag_id = sys.argv[3]
    flag = sys.argv[4]
    login,password = (flag_id,id_gen(12))
    r=s.post(url+"/reg",data={'login':login,'password':password})
    if not 'session' in s.cookies:
        exit(102)
    r=s.get(url+"/add")
    if not "<span>New Image</span>" in r.text:
        exit(102)
    imgs = os.listdir("ecler_checker_images")
    fim = random.choice(imgs)
    r=s.post(url+"/add",data={'comment':flag},files={'image': open("ecler_checker_images/"+fim, 'rb')})
    if r.status_code != 200:
        exit(102)
    aaa=re.findall(r"src\='images/([0-9A-Za-z-]+\.jpg)'",r.text)
    if len(aaa) == 0:
        exit(102)
    imgid = aaa[0]
    r = s.get(url+"/images/"+aaa[0], stream=True)
    if r.status_code == 200:
        with open(aaa[0], 'wb') as f:
            r.raw.decode_content = True
            shutil.copyfileobj(r.raw, f)
    else:
        exit(102)
    print(login+":"+password+":"+imgid)
    """
        open(aaa[0].".mvg",'w+').write(conv_template % (aaa[0],login))
        proc = subprocess.Popen("./convert %s %s" %(mvg,user_file),shell=True,stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        proc.wait()
        os.unlink(aaa[0].".mvg")
        os.unlink(new_file)
    """
    exit(101)
elif cmd == "get":
    login,password,imgid = sys.argv[3].split(":")
    flag = sys.argv[4]
    s = requests.Session()
    r=s.post(url+"/login",data={'login':login,'password':password})
    if not 'session' in s.cookies:
        exit(102)
    r=s.get(url+'/read/'+imgid)
    #print(r.text)
    if len(r.text)==0:
        exit(103)
    if flag in r.text:
        exit(101)
    exit(102)
