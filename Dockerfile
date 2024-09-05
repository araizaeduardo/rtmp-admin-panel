FROM ubuntu:20.04

# Evitar interacciones durante la instalación de paquetes
ENV DEBIAN_FRONTEND=noninteractive

# Instalar dependencias y limpiar caché en un solo comando
RUN apt-get update && apt-get install -y \
    ffmpeg \
    sqlite3 \
    nginx \
    libnginx-mod-rtmp \
    cron \
    stunnel4 \
    && rm -rf /var/lib/apt/lists/*

# Copiar archivos de configuración
COPY nginx.conf /etc/nginx/nginx.conf
COPY rtmp_scheduler.sh /app/rtmp_scheduler.sh
COPY entrypoint.sh /entrypoint.sh
COPY stunnel.conf /etc/stunnel/stunnel.conf

# Dar permisos de ejecución a los scripts
RUN chmod +x /app/rtmp_scheduler.sh /entrypoint.sh

# Crear directorio para la base de datos
RUN mkdir -p /app/db

# Configurar cron
RUN echo "*/5 * * * * /app/rtmp_scheduler.sh >> /var/log/cron.log 2>&1" | crontab -

# Exponer el puerto RTMP y el puerto stunnel
EXPOSE 1935 1936

# Punto de entrada
ENTRYPOINT ["/entrypoint.sh"]
