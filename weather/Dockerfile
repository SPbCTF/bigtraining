FROM alpine:3.8

COPY service/requirements.txt /home/weather/
WORKDIR /home/weather

RUN apk add --no-cache python3 alpine-sdk python3-dev libffi-dev openssl-dev && \
    pip3 install -r requirements.txt

CMD python3 main.py
