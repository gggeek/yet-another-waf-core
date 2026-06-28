ARG UBUNTU_VERSION=resolute

FROM ubuntu:${UBUNTU_VERSION}

ARG APT_PACKAGE_PROXY=none
ARG PHP_VERSION=default
ARG UBUNTU_VERSION=resolute
ARG WEBSERVER_TYPE=all

COPY --chmod=755 setup/*.sh /root/setup/
COPY config/* /root/config/

RUN /root/setup/build.sh

COPY --chmod=755 docker/entrypoint.sh /root/entrypoint.sh

EXPOSE 80 443 8080 8443

# @todo can we avoid hardcoding this here? We can f.e. get it passed down as ARG...
WORKDIR /home/docker/workspace

ENTRYPOINT ["/root/entrypoint.sh"]
