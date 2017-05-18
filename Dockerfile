FROM php:7.1
MAINTAINER Miro Cillik <miro@keboola.com>

RUN apt-get update -q \
  && apt-get install unzip git -y --no-install-recommends \
  && rm -rf /var/lib/apt/lists/*


WORKDIR /root
RUN cd && curl -sS https://getcomposer.org/installer | php && ln -s /root/composer.phar /usr/local/bin/composer

ADD . /code
WORKDIR /code

RUN composer install --no-interaction

ENTRYPOINT php ./run.php --data=/data
