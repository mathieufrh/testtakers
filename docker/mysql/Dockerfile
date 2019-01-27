# Use MySQL docker base image
FROM mysql:5.7

# Use the custom MySQL configuration
COPY ./my.cnf /etc/mysql/conf.d/
RUN chmod 0444 /etc/mysql/conf.d/my.cnf

# Change work directory
WORKDIR /

# Set permissions
RUN mkdir -p /var/lib/mysql && chown -R mysql:root /var/lib/mysql
