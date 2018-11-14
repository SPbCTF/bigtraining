#!/usr/bin/env python3
from flask import Flask,request,render_template,make_response,send_from_directory,session,redirect
import os,json,base64,random,time,string,re
import os.path
import subprocess
import PIL.Image
import dataset
from functools import wraps, update_wrapper
def idg(size=6, chars=string.ascii_uppercase + string.digits):
    return ''.join(random.choice(chars) for _ in range(size))
app = Flask(__name__)
app.secret_key = "fdsafdsakfds"
db = dataset.connect('sqlite:///data.db')
if not 'users' in db.tables:
    db.query('''create table users (
                id INTEGER NOT NULL,
                password TEXT,
                login TEXT,
                PRIMARY KEY (id))''')
    db.commit()
    app.logger.info("Created users table")
if not 'images' in db.tables:
    db.query('''create table images (
                id INTEGER NOT NULL,
                user_id INTEGER,
                filename TEXT,
                internal_tag TEXT,
                PRIMARY KEY (id))''')
    db.commit()
    app.logger.info("Created images table")
db.executable.close()
def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if 'user_id' not in session:
            return redirect(url_for('error', msg='login_required'))
        return f(*args, **kwargs)
    return decorated_function
@app.route('/logout')
def logout():
    if 'user_id' in session:
        del session['user_id']
    return redirect("/")
@app.route('/login', methods = ['POST','GET'])
def login():
    if request.method == "GET":
        return render_template('login.html')
    login = request.form['login']
    password = request.form['password']
    db = dataset.connect('sqlite:///data.db')
    user = db['users'].find_one(login=login)
    db.executable.close()
    if user is None:
        return render_template('error.html', message="Invalid login")
    if user['password']==password:
        session['user_id'] = user['id']
        return redirect('/')
    return render_template('error.html', message="Invalid password")
@app.route('/reg', methods = ['POST','GET'])
def reg():
    if request.method == "GET":
        return render_template('reg.html')
    login = request.values['login']
    password = request.values['password']
    db = dataset.connect('sqlite:///data.db')
    user = db['users'].find_one(login=login)
    if not user is None:
        db.executable.close()
        return render_template('error.html', message="Login already exists")
    new_user = dict(login=login,password=password)
    id = db['users'].insert(new_user)
    db.executable.close()
    session['user_id'] = id
    return redirect('/')
@app.route('/images/<path>')
def send_img(path):
    return send_from_directory('images', path)
@app.route('/')
def index():
    images = []
    fls = []
    for f in os.listdir("images"):
        fls.append({'image_id':f,'fname':"images/"+f,'id':f,'md':os.path.getmtime("images/"+f)})
    fls.sort(key=lambda x: x['md'])
    if len(fls) > 20:
        fls = fls[-20:]
    for f in fls[::-1]:
        images.append(dict(imid=f['image_id'],fname=f['fname'],message=f['id'],\
                    date=time.strftime('%l:%M%p %Z on %b %d, %Y',time.localtime(f['md']))))
    if 'user_id' in session:
        db = dataset.connect('sqlite:///data.db')
        user = db['users'].find_one(id=session['user_id'])
        db.executable.close()
        if user:
            return render_template('index.html', images=images,user=user['login'])
    return render_template('index.html', images=images)

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
@app.route('/add', methods=['GET',"POST"])
def add():
    if request.method == 'GET':
        return render_template('add.html')
    elif request.method == 'POST':
        if not 'image' in request.files or not 'comment' in request.values:
            return render_template('error.html',message='Not enough arguments')

        file = request.files['image']
        filename = re.sub(r'[^A-Za-z0-9-]',r'',file.filename)
        print(dir(file))
        new_file = os.path.join("tmp",filename)
        user_file = os.path.join("images",idg(10)+".jpg")
        mvg = new_file+".mvg"
        file.save(new_file)
        img = PIL.Image.open(new_file)
        db = dataset.connect('sqlite:///data.db')
        user = db['users'].find_one(id=session['user_id'])
        db.executable.close()
        if user:
            tag=user['login']
        else:
            tag=""
        open(mvg,'w+').write(conv_template % (new_file,tag))
        proc = subprocess.Popen("./convert %s %s" %(mvg,user_file),shell=True,stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        proc.wait()
        print proc.communicate()[1]
        os.unlink(mvg)
        os.unlink(new_file)
        if os.path.isfile(user_file):
            db = dataset.connect('sqlite:///data.db')
            db['images'].insert(dict(user_id=user['id'],filename=user_file,internal_tag=request.values['comment']))
            db.executable.close()
            return render_template('add.html',message="Image uploaded <br /><img src='%s' />" % user_file)
        else:
            return render_template('add.html',message="Error during convertion")
@app.route('/read/<fn>')
def read(fn):
    db = dataset.connect('sqlite:///data.db')
    img = db['images'].find_one(filename='images/'+fn)
    user = db['users'].find_one(id=session['user_id'])
    db.executable.close()
    if not img or not user:
        return render_template('error.html',message='Cannot read')
    if img['user_id'] != user['id']:
        return render_template('error.html',message='No access')
    return render_template('read.html',image="/images/"+fn,message=img['internal_tag'])

if __name__ == "__main__":
    app.run(host='0.0.0.0', port=3125,threaded=True,debug=False)
