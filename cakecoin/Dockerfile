FROM golang:1.9.2-stretch

WORKDIR /home/cakecoin

RUN go get github.com/gorilla/sessions
CMD exec go run server.go
