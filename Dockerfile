FROM php:8.2-apache

RUN docker-php-ext-install mysqli

COPY . /var/www/html/

RUN echo '#!/bin/bash\nsed -i "s/80/$PORT/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf\napache2-foreground' > /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]
