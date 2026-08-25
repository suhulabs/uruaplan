#!/usr/bin/env bash
# ==============================================================================
# Script de Despliegue para Uruaplan
# Genera un paquete listo para produccion (.zip) con las exclusiones correctas.
# ==============================================================================

set -e

DIRECTORIO_RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FECHA=$(date +%Y%m%d_%H%M%S)
PAQUETE_ZIP="${DIRECTORIO_RAIZ}/dist/uruaplan_release_${FECHA}.zip"
EXCLUSIONES_FILE="$(mktemp)"

echo "🚀 Iniciando preparación del paquete de despliegue para Uruaplan..."

# Crear carpeta dist si no existe
mkdir -p "${DIRECTORIO_RAIZ}/dist"

# Ejecutar suite de pruebas antes de empaquetar
echo "🧪 Ejecutando pruebas automatizadas de seguridad..."
php "${DIRECTORIO_RAIZ}/cheve/tests/pruebas.php"
echo ""

# Generar lista de exclusiones para zip / rsync
cat << 'EOF' > "$EXCLUSIONES_FILE"
.git/*
.gitignore
.claude/*
.gemini/*
.vscode/*
.idea/*
*.md
ANALISIS*.md
INSTRUCCIONES.md
cheve/instalar.php
cheve/probar-correo.php
cheve/tests/*
cheve/data/secreto.json
cheve/data/*.tmp*
img/uploads/*
dist/*
desplegar.sh
respaldo-bd.sh
respaldos/*
*.zip
*.tar.gz
.DS_Store
Thumbs.db
EOF

cd "$DIRECTORIO_RAIZ"

if command -v zip &> /dev/null; then
    echo "📦 Empaquetando sitio en: ${PAQUETE_ZIP}"
    zip -r -q "$PAQUETE_ZIP" . -x@"$EXCLUSIONES_FILE"
    echo "✅ Paquete generado con éxito en dist/uruaplan_release_${FECHA}.zip"
else
    echo "⚠️  El comando 'zip' no está instalado. Creando archivo .tar.gz..."
    PAQUETE_TAR="${DIRECTORIO_RAIZ}/dist/uruaplan_release_${FECHA}.tar.gz"
    tar --exclude-from="$EXCLUSIONES_FILE" -czf "$PAQUETE_TAR" .
    echo "✅ Paquete generado con éxito en dist/uruaplan_release_${FECHA}.tar.gz"
fi

rm -f "$EXCLUSIONES_FILE"

echo ""
echo "📋 SIGUIENTES PASOS EN PRODUCCIÓN (cPanel / Servidor):"
echo "  1. Sube el paquete generado a la carpeta raíz de tu sitio en cPanel (public_html)."
echo "  2. Descomprime el archivo sustituyendo solo el código PHP/CSS/JS."
echo "  3. Verifica que la carpeta img/uploads/ y el archivo cheve/includes/config-bd.php mantengan sus datos de producción."
echo "  4. Realiza una prueba de humo cargando el sitio y el panel /cheve/."
echo "🎉 ¡Despliegue preparado listo para producción!"
