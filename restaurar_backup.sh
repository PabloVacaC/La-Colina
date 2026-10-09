#!/bin/bash
set -e

echo "=========================================="
echo "  RESTAURANDO BASE DE DATOS E IMÁGENES    "
echo "=========================================="

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# 1. Unir partes del archivo de imagenes si no esta unido
if [ ! -f la_colinaf_imagenes_2026-10-05.tar.gz ]; then
    echo "1. Uniendo partes del archivo de imagenes..."
    cat la_colinaf_imagenes_2026-10-05.tar.gz.part_* > la_colinaf_imagenes_2026-10-05.tar.gz
else
    echo "1. El archivo de imagenes ya esta unido."
fi

# 2. Descomprimir imagenes en el proyecto con permisos de superusuario
echo "2. Descomprimiendo imagenes de clientes con sudo..."
sudo mkdir -p La-ColinaF-master/storage/app/public/clientes
sudo tar --no-same-owner -zxvf la_colinaf_imagenes_2026-10-05.tar.gz -C La-ColinaF-master/

# 3. Asignar permisos correctos
echo "3. Asignando permisos a las imagenes..."
sudo chown -R www-data:www-data La-ColinaF-master/storage/app/public
sudo chmod -R 775 La-ColinaF-master/storage/app/public

# 4. Importar base de datos a Docker MySQL
echo "4. Importando base de datos a MySQL en Docker..."
cd "$SCRIPT_DIR/La-ColinaF-master"
sudo docker exec -i laravel_mysql mysql -u root -pColinaRootSecure2026! sisdelivery < "$SCRIPT_DIR/backup_sisdelivery_2026-10-09.sql"

# 5. Asegurar storage:link y limpiar cache
echo "5. Creando enlace simbolico y limpiando cache..."
sudo docker exec -i laravel_app php artisan storage:link 2>/dev/null || true
sudo docker exec -i laravel_app php artisan cache:clear 2>/dev/null || true
sudo docker exec -i laravel_app php artisan view:clear 2>/dev/null || true

echo "=========================================="
echo "  ¡RESTAURACION COMPLETADA CON EXITO!     "
echo "=========================================="
