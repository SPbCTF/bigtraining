FROM debian:stretch-slim

WORKDIR /home/weeper

RUN apt-get update && \
    apt-get -y install socat
CMD exec socat -T 10 -d -d tcp-l:6868,reuseaddr,fork exec:./weeper
