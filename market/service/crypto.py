import binascii
from Crypto import Random
from Crypto.Cipher import AES

BS = 16
SECRET = '1240deb026d49618eade2d2440de3f42'
pad = lambda s: s + (BS - len(s) % BS) * chr(BS - len(s) % BS)
unpad = lambda s : s[0:-ord(s[-1])]


def encode_aes(GOOD):
    string = "|{},{},{}|".format(GOOD['name'], GOOD['cost'], GOOD['visible'])

    iv = Random.new().read(AES.block_size)
    obj = AES.new(SECRET, AES.MODE_CBC, iv)
    message = pad(string)
    return iv + obj.encrypt(message)


def decode_aes(text):
    iv = binascii.unhexlify(text)[:16]
    txt = binascii.unhexlify(text)[16:]

    obj = AES.new(SECRET, AES.MODE_CBC, iv)
    plaintext = obj.decrypt(txt)
    return iv, plaintext
