#!/usr/bin/env bash
# Backup diário só deste sistema (banco + uploads). Instalado em /usr/local/sbin e executado
# como root por /etc/cron.d/premio-ceara-backup. Não inclui o .env: as chaves têm custódia
# separada. ⚠️ Fica no mesmo servidor; copiar para fora é um passo à parte (runbook).
set -euo pipefail
umask 077

STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p /var/backups/premio-ceara
cd /var/backups/premio-ceara

# Nomes temporários fixos: uma execução interrompida é sobrescrita pela seguinte.
mariadb-dump --single-transaction --quick premio_ceara | gzip > banco.parcial
gzip -t banco.parcial
mv banco.parcial "banco-$STAMP.sql.gz"

if [ -d /var/www/premio-ceara/shared/storage/app ]; then
    tar -C /var/www/premio-ceara/shared/storage -czf uploads.parcial app
    mv uploads.parcial "uploads-$STAMP.tgz"
fi

# Retenção de 14 dias, só depois de um backup novo concluído e só destes nomes.
find /var/backups/premio-ceara -maxdepth 1 -type f \
    \( -name 'banco-*.sql.gz' -o -name 'uploads-*.tgz' \) -mtime +14 -delete
