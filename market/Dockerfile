FROM alpine:3.8

COPY service/requirements.txt /home/market/
WORKDIR /home/market

RUN apk add --no-cache python3 alpine-sdk python3-dev socat && \
    pip3 install -r requirements.txt

CMD socat -T 10 -d -d tcp-l:10081,reuseaddr,fork exec:"python3 server.py"
