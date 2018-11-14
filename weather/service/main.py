import json

from flask import Flask
from flask import make_response
from flask import redirect
from flask import render_template
from flask import request

from model import db, Users, Cache, Forecasts, Search

app = Flask(__name__)


def check_creadentials(login=None, password=None):
    if not login and not password:
        login = request.cookies.get('login')
        password = request.cookies.get('password')
    try:
        cursor = db.execute_sql('select * from users where login="{}" and password="{}"'.format(login, password))
        for row in cursor.fetchall():
            return True
    except:
        return False


@app.route('/')
def index():
    сursor = db.execute_sql('select forecasts.official, forecasts.city,(SELECT weather from forecasts WHERE city=f.city AND official=0 ORDER BY id DESC limit 1),(SELECT day from forecasts WHERE city=f.city AND official=0 ORDER BY id DESC limit 1),(SELECT night from forecasts WHERE city=f.city AND official=0 ORDER BY id DESC limit 1) from forecasts LEFT JOIN forecasts AS f ON f.city=forecasts.city group by city, official HAVING official=0;')
    return render_template('index.html', forecasts=[row for row in сursor.fetchall()])


@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        login, password = request.form['login'], request.form['password']
        if check_creadentials(login, password):
            resp = make_response(redirect('/cabinet'))
            resp.set_cookie('login', login)
            resp.set_cookie('password', password)
            return resp
    else:
        return render_template('login.html')


@app.route('/logout')
def logout():
    resp = make_response(redirect('/'))
    resp.set_cookie('login', '', expires=0)
    resp.set_cookie('password', '', expires=0)
    return resp


@app.route('/cabinet', methods=['GET', 'POST'])
def cabinet():
    if not check_creadentials():
        return redirect('/')

    login = request.cookies.get('login')
    if request.method == 'POST':
        city, weather, day, night, official = request.form['city'], request.form['weather'], request.form['day'], request.form['night'], int(request.form.get('official', 0))
        db.execute_sql('insert into forecasts VALUES(NULL, "{}", "{}", "{}", "{}", "{}", "{}")'.format(login, city, weather, day, night, official))
        Search().add(city, weather, day, night, str(official))

    cursor = db.execute_sql('select * from forecasts where author="{}"'.format(login))
    return render_template('cabinet.html', forecasts=[row for row in cursor.fetchall()])


@app.route('/register', methods=['GET', 'POST'])
def register():
    if request.method == 'POST':
        if check_creadentials(request.form['login'], request.form['password']):
            return render_template('register.html', error="User exists")
        else:
            if Cache().check_password(str(request.form['password'])) == "1":
                return render_template('register.html', error="Your password is too common")
            db.execute_sql('insert into users values(NULL, "{}", "{}")'.format(request.form['login'], request.form['password']))
            Cache().validate((request.form['login'], request.form['password']))
            return render_template('login.html', message="Successfully registered")
    else:
        return render_template('register.html')


@app.route('/search', methods=['GET', 'POST'])
def search():
    results = []
    city = ""
    if request.method == 'POST':
        city = request.form['city']
        results = [r for r in Search().find(json.loads('{"city": "'+city+'"}'))]
    return render_template('search.html', forecasts=results, city=city)


if __name__ == '__main__':
    db.connect()
    db.create_tables([Users, Forecasts])
    app.run(host='0.0.0.0', port=5000)
