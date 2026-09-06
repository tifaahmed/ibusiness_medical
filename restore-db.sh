#!/usr/bin/env bash
# Point-in-time restore of the `ibusiness_medical` development database.
#
# The database was dropped at 2026-09-06 14:44:48 by `php artisan db:wipe`.
# MySQL has binary logging on (ROW format, 30-day retention) and the database's
# whole history is still there: `CREATE DATABASE ibusiness_medical` sits in
# binlog.000316 at 2026-08-08 16:03:05, and every write since is in
# binlog.000316 .. binlog.000366.
#
# This replays those events, filtered to that one database, stopping just
# before the drop. It only writes into ibusiness_medical, which is empty.

set -euo pipefail

HOST=127.0.0.1
USER=laravel
PASS=yourpassword
DB=ibusiness_medical
STOP="2026-09-06 14:44:00"

LOGS=()
for i in $(seq -w 316 366); do LOGS+=("binlog.000$i"); done

# Only ever run against the empty shell the wipe left behind: if the database
# holds tables again, something has already restored it and a second replay
# would fight with it.
TABLES=$(mysql -h"$HOST" -u"$USER" -p"$PASS" -N -B \
  -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB';")

if [ "$TABLES" != "0" ]; then
  echo "$DB already holds $TABLES tables — leaving it alone. Restore aborted." >&2
  exit 1
fi

mysql -h"$HOST" -u"$USER" -p"$PASS" \
  -e "CREATE DATABASE IF NOT EXISTS \`$DB\` COLLATE 'utf8mb4_0900_ai_ci';"

# The stream replays the original `CREATE DATABASE`, which now already exists,
# so the client has to survive that one error (1007) to reach the rest. Every
# error is kept in mysql-errors.log and checked below rather than ignored.
set +e
mysqlbinlog --read-from-remote-server --host="$HOST" --user="$USER" --password="$PASS" \
  --database="$DB" --stop-datetime="$STOP" "${LOGS[@]}" 2>binlog-read.log \
| mysql -h"$HOST" -u"$USER" -p"$PASS" --force "$DB" 2>mysql-errors.log
set -e

echo "--- replay errors other than the expected 'database exists' ---"
grep -v "1007 (HY000)" mysql-errors.log | grep -v "Using a password" || echo "(none)"

echo "--- restored table counts ---"
mysql -h"$HOST" -u"$USER" -p"$PASS" -e "
  SELECT (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB') AS tables,
         (SELECT COUNT(*) FROM $DB.users)      AS users,
         (SELECT COUNT(*) FROM $DB.facilities) AS facilities,
         (SELECT COUNT(*) FROM $DB.orders)     AS orders,
         (SELECT COUNT(*) FROM $DB.settings)   AS settings;"
