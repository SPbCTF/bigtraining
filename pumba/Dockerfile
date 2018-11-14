FROM debian:stretch-slim

WORKDIR /home/pumba

RUN apt-get update && \
    apt-get -y install socat
CMD exec socat -T 10 -d -d tcp-l:33333,reuseaddr,fork exec:./pumba
