# Validação da release em MariaDB 10.11

Ensaio local de **24/11/2026**, em containers descartáveis, sem tocar em nenhum servidor real.
MariaDB 10.11 é a série do Ubuntu 24.04 e a única aceita pelo preflight
(`app/Support/DatabaseCompatibility.php`).

| Ensaio | Ambiente | Resultado |
|---|---|---|
| Suíte completa | imagem `mariadb:10.11` (10.11.19), PHP 8.4.28 | 359 testes, 918 asserções, sem falhas |
| Suíte completa (controle) | imagem `mysql:8.0` | 359 testes, 918 asserções, sem falhas |
| Migrations e `EditionSeeder` duas vezes | `mariadb:10.11` | 13 migrations; seeder idempotente |
| Deploy completo pelos scripts de `deploy/` | Ubuntu 24.04 + MariaDB 10.11.14 do Ubuntu + nginx 1.24 + PHP-FPM 8.4.28 | preflight, migrations, seeder, ativação e smoke sem falhas |
| Backup e restauração | idem | dump restaurado em outro schema; mesmas migrations e edição |

## Decisões

- **`DB_CONNECTION=mysql`**, e não `mariadb`: foi o driver ensaiado.
- O `CHECK` de `scores` é omitido, como no Percona 5.7; a regra "nota de 1 a 5" é da aplicação
  e está coberta por testes.
- **Só a série 10.11.** A partir da 11.6, `innodb_snapshot_isolation` vem ligado e muda o
  comportamento dos `lockForUpdate` da inscrição, do júri e da avaliação. No ensaio estava
  desligado. Outra série exige repetir este ensaio.
- A suíte não exercita concorrência real entre conexões.

O CI (`foundation.yml`) roda a suíte em MySQL 8.0 e em MariaDB 10.11.

Ainda precisa ser confirmado na VPS: versão exata do MariaDB e acesso do root por socket
(`provisionar.sh inventario`).
