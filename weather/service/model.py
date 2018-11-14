import json
import time
from peewee import *
from pymongo import MongoClient
import redis


db = MySQLDatabase('weather', user='root', password='root', host='mysql', port=3306)
client = MongoClient('mongodb://db:27017/')


class Users(Model):
    login = CharField()
    password = CharField()

    class Meta:
        database = db


class Forecasts(Model):
    author = CharField()
    city = CharField()
    weather = CharField()
    day = CharField()
    night = CharField()
    official = IntegerField()

    class Meta:
        database = db


class Cache:

    def __init__(self):
        self.r = redis.StrictRedis(host='redis', port=6379, decode_responses=True)

    def validate(self, data):
        self.r.set("log:{}".format(time.time()), json.dumps(data))
        self.r.set("password:{}".format(data[1]), "1")
        self.r.set("login:{}".format(data[0]), "1")

    def check_password(self, password):
        return self.r.get("password:{}".format(password))


class Search:

    def __init__(self):
        self.db = client['search']
        self.collection = self.db['forecasts']

    def add(self, city, weather, day, night, official):
        self.collection.insert({'city': city, 'weather': weather, 'day': day, 'night': night, 'official': official})

    def find(self, query):
        return self.collection.find(query)