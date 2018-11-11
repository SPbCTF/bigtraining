import binascii
from Crypto import Random
from Crypto.Cipher import AES


BS = 16
SECRET = '1240deb026d49618eade2d2440de3f42'
pad = lambda s: s + (BS - len(s) % BS) * chr(BS - len(s) % BS)
unpad = lambda s : s[0:-ord(s[-1])]


def decode_aes(text):
    iv = binascii.unhexlify(text)[:16]
    txt = binascii.unhexlify(text)[16:]

    obj = AES.new(SECRET, AES.MODE_CBC, iv)
    plaintext = obj.decrypt(txt)
    return iv, plaintext

token = "eca8eff704c8374de4ae6ba83fdeb4939a0dbc17972853cfad93509d48df365814a4a016986bfc76400e78c1f8cfdae9"
cost = "767" # |MHPzxOpP,000...

iv = bytearray(binascii.unhexlify(token)[:16])
txt = binascii.unhexlify(token)[16:]
start = 10

assert start + len(cost) <= 15

for i in range(start, start+len(cost)):
    iv[i] = iv[i] ^ ord(cost[i-start]) ^ ord('0')
iv = bytes(iv)


new_token = binascii.hexlify(iv + txt)
print(new_token)

# CHECK
_, txt = decode_aes(new_token)
print(unpad(txt.decode()))