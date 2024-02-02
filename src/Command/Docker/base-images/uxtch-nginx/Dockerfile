ARG ALPINE_VERSION="3.19"
ARG PHP_VERSION="8.2.15"

# Use Alpine Linux
FROM alpine:${ALPINE_VERSION}

# Declare maintainer
LABEL \
    Maintainer="Enrique Martinez <https://uxmal.tech>" \
    Description="NGINX with extensions on top of Alpine Linux."

# Timezone
ENV TIMEZONE America/Monterrey

# Let's roll
RUN	apk update && \
	apk upgrade && \
	apk add --update openssl nginx && \
	apk add --update tzdata && \
	cp /usr/share/zoneinfo/${TIMEZONE} /etc/localtime && \
	echo "${TIMEZONE}" > /etc/timezone && \
	mkdir /etc/nginx/certificates && \
	openssl req \
		-x509 \
		-newkey rsa:2048 \
		-keyout /etc/nginx/certificates/key.pem \
		-out /etc/nginx/certificates/cert.pem \
		-days 365 \
		-nodes \
		-subj /CN=localhost && \
	mkdir /www && \
	apk del tzdata && \
	rm -rf /var/cache/apk/*

# Expose volumes
EXPOSE 80

STOPSIGNAL SIGQUIT

# To start apache in the background as a service
CMD ["/bin/sh"]
