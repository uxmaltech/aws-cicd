# Use Alpine Linux
FROM alpine:3.19

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
VOLUME ["/etc/nginx/conf.d", "/var/log/nginx", "/www"]

# Expose ports
EXPOSE 8000

# Entry point
ENTRYPOINT ["/usr/sbin/nginx", "-g", "daemon off;"]
