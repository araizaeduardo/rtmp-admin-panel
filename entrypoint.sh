#!/bin/bash

# Iniciar el servicio cron
if service cron start; then
    echo "Servicio cron iniciado correctamente"
else
    echo "Error al iniciar el servicio cron" >&2
    exit 1
fi

# Iniciar stunnel
if stunnel4 /etc/stunnel/stunnel.conf; then
    echo "Stunnel iniciado correctamente"
else
    echo "Error al iniciar Stunnel" >&2
    exit 1
fi

# Iniciar NGINX
if nginx -g 'daemon off;'; then
    echo "NGINX iniciado correctamente"
else
    echo "Error al iniciar NGINX" >&2
    exit 1
fi

# Mantener el contenedor en ejecución
exec "$@"
