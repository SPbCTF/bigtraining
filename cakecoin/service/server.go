package main

import (
  "os"
  "io/ioutil"
	"net/http"
  "crypto/md5"
	"github.com/gorilla/sessions"
  "encoding/json"
  "regexp"
  "strconv"
  "html/template"
)

var (
	key = []byte("super-secret-key")
	store = sessions.NewCookieStore(key)
  all_pays = map[string]int{}
)
type ErrorMes struct {
  Message string
}
func er(mes string,w http.ResponseWriter, r *http.Request){
  t, _ := template.ParseFiles("templates/error.html")
  t.Execute(w, ErrorMes{Message:mes})
}
func OkMes(mes string,w http.ResponseWriter, r *http.Request){
  t, _ := template.ParseFiles("templates/ok.html")
  t.Execute(w, ErrorMes{Message:mes})
}
func login(w http.ResponseWriter, r *http.Request) {
  if r.Method == "GET" {
    t, _ := template.ParseFiles("templates/login.html")
    t.Execute(w, nil)
    return
  }
  r.ParseForm()
  mlogin, ok := r.Form["login"]
  if !ok {
    er("No login provided",w,r)
		return
  }
  mpassword, ok := r.Form["password"]
  if !ok {
    er("No password provided",w,r)
    return
  }
  user := loadUser(mlogin[0])
  if user.Money>0 && user.Password != mpassword[0] {
    er("Invalid password",w,r)
		return
  }
  session, _ := store.Get(r, "authed")
	session.Values["authenticated"] = true
  session.Values["user"] = mlogin[0]
	session.Save(r, w)
  http.Redirect(w, r, "/", http.StatusSeeOther)
}
func verifyPay(pay string) bool{
  var sum = md5.Sum([]byte(pay))
  if sum[0] == 0x1 && sum[1] == 0x37 {
    _,er := all_pays[pay]
    if !er {
      all_pays[pay]=1
      return true
    }
    return false
  }
  return false
}
func loadUser(login string) UserInfo {
  user_file := "users/"+login
  jsonFile, _ := os.Open(user_file)
  byteValue, _ := ioutil.ReadAll(jsonFile)
  jsonFile.Close()
  var user UserInfo
  json.Unmarshal([]byte(byteValue), &user)
  return user
}
func storeUser(user UserInfo){
  b, _ := json.Marshal(user)
  user_file := "users/"+user.Login
  ioutil.WriteFile(user_file, b, 0644)
}
func payOneCoin(login string){
  user := loadUser(login)
  mon := user.Money
  user.Money = mon + 1
  storeUser(user)
}
type Trans struct {
  From string `json:"from"`
  To string   `json:"to"`
  Money int64 `json:"money"`
  Message string `json:"message"`
}
type UserInfo struct {
  Login string         `json:"login"`
  Password string      `json:"password"`
  Money int64           `json:"money"`
  Transactions []Trans  `json:"transactions"`
}
func register(w http.ResponseWriter, r *http.Request) {
  if r.Method == "GET" {
    t, _ := template.ParseFiles("templates/reg.html")
    t.Execute(w, nil)
    return
  }
  r.ParseForm()
  mlogin, ok := r.Form["login"]
  if !ok || len(mlogin[0])>64 {
    http.Error(w, "Forbidden", http.StatusForbidden)
		return
  }
  mpassword, ok := r.Form["password"]
  if !ok || len(mpassword[0])>64 {
    http.Error(w, "Forbidden", http.StatusForbidden)
    return
  }
  mpay, ok := r.Form["pay"]
  if !ok || len(mpay[0])>64 {
    http.Error(w, "Forbidden", http.StatusForbidden)
    return
  }
  if !verifyPay(mpay[0]){
    http.Error(w, "Forbidden", http.StatusForbidden)
    return
  }
  reg, _ := regexp.Compile("[^a-zA-Z0-9-]+")
  clear_login := reg.ReplaceAllString(mlogin[0], "")
  user_file := "users/"+clear_login
  if _, err := os.Stat(user_file); os.IsExist(err) {
    http.Error(w, "Forbidden", http.StatusForbidden)
    return
  }
  new_user := UserInfo{clear_login,mpassword[0],0,[]Trans{}}
  //new_user := map[string] interface{} {"login":clear_login,"password":mpassword[0],"money":"0","transactions":[]Trans{}}
  storeUser(new_user)
  payOneCoin(clear_login)
  session, _ := store.Get(r, "authed")
	session.Values["authenticated"] = true
  session.Values["user"] = clear_login
	session.Save(r, w)
  http.Redirect(w, r, "/", http.StatusSeeOther)
}

func logout(w http.ResponseWriter, r *http.Request) {
	session, _ := store.Get(r, "authed")
	// Revoke users authentication
	session.Values["authenticated"] = false
  session.Values["user"] = ""
	session.Save(r, w)
  http.Redirect(w, r, "/", http.StatusSeeOther)
}
func makeTransfer(from string, to string, money int64,mes string) bool {
  user_struct := loadUser(from)
  from_money := user_struct.Money
  if money > from_money {
    return false
  }
  to_user_struct := loadUser(to)
  to_money_int := to_user_struct.Money
  user_struct.Money = from_money - money
  to_user_struct.Money = to_money_int + money
  var tr = user_struct.Transactions
  user_struct.Transactions = append(tr,Trans{user_struct.Login,to_user_struct.Login,money,mes})
  if len(user_struct.Transactions)>10 {
    user_struct.Transactions = user_struct.Transactions[len(user_struct.Transactions)-10:]
  }
  tr = to_user_struct.Transactions
  to_user_struct.Transactions = append(tr,Trans{user_struct.Login,to_user_struct.Login,money,mes})
  if len(to_user_struct.Transactions)>10 {
    to_user_struct.Transactions = to_user_struct.Transactions[len(to_user_struct.Transactions)-10:]
  }
  storeUser(user_struct)
  storeUser(to_user_struct)
  return true
}
func sendmoney(w http.ResponseWriter, r *http.Request) {
  session, _ := store.Get(r, "authed")
  user,ok1 := session.Values["user"]
  authed,ok2 := session.Values["authenticated"]
  if ok1 && ok2 && authed == true {
    r.ParseForm()
    to_login, _ := r.Form["to_login"]
    to_money, _ := r.Form["to_money"]
    if to_login[0] == user {
      er("Invalid to field",w,r)
      return
    }
    tosend_money,err := strconv.ParseInt(to_money[0],10,64)
    if err != nil {
      er("No money provided",w,r)
      return
    }
    to_message, _ := r.Form["to_message"]
    ok := makeTransfer(user.(string),to_login[0],tosend_money,to_message[0])
    if !ok {
      er("No such money",w,r)
      return
    }
    OkMes("Money was sent",w,r)
    return
  }
  er("Not authenticated",w,r)
}
func getmoney(w http.ResponseWriter, r *http.Request) {
  session, _ := store.Get(r, "authed")
  user,ok1 := session.Values["user"]
  authed,ok2 := session.Values["authenticated"]
  if ok1 && ok2 && authed == true {
    r.ParseForm()
    mpay, _ := r.Form["pay"]
    if !verifyPay(mpay[0]){
      er("Not valid pay",w,r)
      return
    }
    payOneCoin(user.(string))
    http.Redirect(w, r, "/", http.StatusSeeOther)
  }
  er("Not autenticated",w,r)
}
func userlist(w http.ResponseWriter, r *http.Request) {
  session, _ := store.Get(r, "authed")
  user,ok1 := session.Values["user"]
  authed,ok2 := session.Values["authenticated"]
  if ok1 && ok2 && authed == true {
    user_struct := loadUser(user.(string))
    if user_struct.Money <1 {
      er("No money for this",w,r)
      return
    }
    user_struct.Money = user_struct.Money - 1
    storeUser(user_struct)
    ulist :=[]string{}
    files, _ := ioutil.ReadDir("users")
  	for _, file := range files {
      ulist = append(ulist,file.Name())
  	}
    t, _ := template.ParseFiles("templates/ulist.html")
    t.Execute(w, ulist)
    return
  }
  er("Not autenticated",w,r)
}
func index(w http.ResponseWriter, r *http.Request) {
  session, _ := store.Get(r, "authed")
  user,ok1 := session.Values["user"]
  authed,ok2 := session.Values["authenticated"]
  t, _ := template.ParseFiles("templates/index.html")
  if ok1 && ok2 && authed == true {
    user_struct := loadUser(user.(string))
    t.Execute(w, user_struct)
    return
  }
  t.Execute(w, nil)
}
func main() {
  http.HandleFunc("/", index)
	http.HandleFunc("/login", login)
  http.HandleFunc("/userlist", userlist)
  http.HandleFunc("/sendmoney", sendmoney)
  http.HandleFunc("/register", register)
  http.HandleFunc("/getmoney", getmoney)
	http.HandleFunc("/logout", logout)
	http.ListenAndServe(":2145", nil)
}
