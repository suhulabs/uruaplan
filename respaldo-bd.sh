#!/usr/bin/env bash
# ==============================================================================
# Script de Respaldo Automático de MySQL / MariaDB para Uruaplan
# Diseñado para ejecutarse periódicamente mediante cron en cPanel / Servidor.
# ==============================================================================

set -e

DIRECTORIO_RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CARPETA_RESPALDOS="$(dirname "${DIRECTORIO_RAIZ}")/respaldos_uruaplan"

# Crear la carpeta de respaldos fuera del webroot; si falla, usar carpeta local con aviso
if ! mkdir -p "${CARPETA_RESPALDOS}" 2>/dev/null; then
    CARPETA_RESPALDOS="${DIRECTORIO_RAIZ}/respaldos"
    mkdir -p "${CARPETA_RESPALDOS}"
    echo "⚠️  Atención: No se pudo crear la carpeta fuera del webroot. Guardando en ${CARPETA_RESPALDOS} (dentro del webroot)."
fi

FECHA=$(date +%Y%m%d_%H%M%S)
ARCHIVO_SALIDA="${CARPETA_RESPALDOS}/respaldo_uruaplan_${FECHA}.sql.gz"

DUMP_CMD=""
if command -v mariadb-dump &> /dev/null; then
    DUMP_CMD="mariadb-dump"
elif command -v mysqldump &> /dev/null; then
    DUMP_CMD="mysqldump"
fi

if [ -n "$DUMP_CMD" ]; then
    echo "📦 Generando volcado de base de datos..."
    
    CRED_FILE="$(dirname "${DIRECTORIO_RAIZ}")/credenciales_uruaplan.php"
    
    if [ -f "$CRED_FILE" ]; then
        HOST=$(php -r "\$c = @include '$CRED_FILE'; echo is_array(\$c) && !empty(\$c['BD_HOST']) ? \$c['BD_HOST'] : '127.0.0.1';")
        BASE=$(php -r "\$c = @include '$CRED_FILE'; echo is_array(\$c) && !empty(\$c['BD_NOMBRE']) ? \$c['BD_NOMBRE'] : 'uruaplan';")
        USER=$(php -r "\$c = @include '$CRED_FILE'; echo is_array(\$c) && !empty(\$c['BD_USUARIO']) ? \$c['BD_USUARIO'] : 'uruaplan';")
        PASS=$(php -r "\$c = @include '$CRED_FILE'; echo is_array(\$c) && isset(\$c['BD_PASSWORD']) ? \$c['BD_PASSWORD'] : '';")
    else
        echo "⚠️  No se encontró credenciales_uruaplan.php. Usando credenciales locales de desarrollo..."
        HOST="127.0.0.1"
        BASE="uruaplan_dev"
        USER="uruaplan"
        PASS="uruaplan_dev"
    fi

    # Pasar la contraseña mediante variable de entorno para no exponerla en ps ni en la línea de comandos
    export MYSQL_PWD="$PASS"
    $DUMP_CMD -h "$HOST" -u "$USER" "$BASE" | gzip > "$ARCHIVO_SALIDA"
    DUMP_STATUS=${PIPESTATUS[0]}
    unset MYSQL_PWD

    if [ $DUMP_STATUS -ne 0 ]; then
        echo "❌ Error al generar el volcado de la base de datos (código de salida: $DUMP_STATUS)."
        rm -f "$ARCHIVO_SALIDA"
        exit 1
    fi

    # Eliminar respaldos más antiguos a 7 días (rotación automática)
    find "${CARPETA_RESPALDOS}" -name "respaldo_uruaplan_*.sql.gz" -mtime +7 -delete 2>/dev/null || true

    TAMANO=$(ls -lh "$ARCHIVO_SALIDA" | awk '{print $5}')
    echo "✅ Respaldo generado con éxito (${TAMANO}) en: ${ARCHIVO_SALIDA}"
else
    echo "❌ Error: ni mariadb-dump ni mysqldump están instalados en este sistema."
    exit 1
fi
