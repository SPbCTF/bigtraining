FROM ubuntu:xenial

WORKDIR /home/ecler

RUN apt-get update && \
    apt-get -y install python python-pip libjbig0 libtiff5 libjpeg8 libpng12-0 libfontconfig1 && \
    pip install flask pillow dataset
CMD exec ./run.sh
