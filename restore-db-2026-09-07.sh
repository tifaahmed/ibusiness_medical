#!/usr/bin/env bash
# Point-in-time restore of the `ibusiness_medical` development database.
#
# WHAT HAPPENED
# -------------
# On 2026-09-07 at 15:42:45 the database was dropped by
# `php artisan migrate:fresh --env=testing --database=mysql`, run while adding
# the storefront phone-login work.
#
# The mistake was `--env=testing`: there is no `.env.testing` in this project,
# so Laravel fell back to `.env` and the command pointed at the DEVELOPMENT
# database rather than at `membership_test`. The test database name lives in
# `phpunit.xml` as an `<env>` entry, which only applies while PHPUnit is
# running — an Artisan command never sees it.
#
# Twelve seconds later, at 15:42:54, the same command rebuilt an EMPTY schema.
# Nothing was written into it, so nothing of yours is in the current tables.
#
# WHAT THIS RESTORES
# ------------------
# MySQL has binary logging on (ROW format), and every write to this database is
# still in binlog.000366 .. binlog.000369. The window replayed below runs from
# just after the previous recovery finished rebuilding the schema
# (2026-09-06 16:09:59) to one second before the drop (2026-09-07 15:42:44).
#
# That window was checked before this script was written: 45 MB, 60 CREATE
# TABLE statements and 1,680 row events — the whole schema and the whole
# dataset, including the 525 users and 513 memberships that were there.
#
# HOW TO RUN IT
# -------------
#   bash restore-db-2026-09-07.sh
#
# It refuses to run if the database already holds data, so it cannot overwrite
# a restore somebody has already done.

set -euo pipefail

HOST=127.0.0.1
USER=laravel
PASS=yourpassword
DB=ibusiness_medical

# Just after the previous recovery rebuilt the schema.
START="2026-09-06 16:09:59"
# One second before the drop at 15:42:45.
STOP="2026-09-07 15:42:44"

LOGS=(binlog.000366 binlog.000367 binlog.000368 binlog.000369)

# Only ever run against a database holding no real rows. The tables that are
# there now are the empty ones `migrate:fresh` created; if any of them has data,
# something has already been restored and a second replay would fight with it.
ROWS=$(mysql -h"$HOST" -u"$USER" -p"$PASS" -N -B -e "
  SELECT COALESCE((SELECT COUNT(*) FROM \`$DB\`.users), 0)
       + COALESCE((SELECT COUNT(*) FROM \`$DB\`.facilities), 0);" 2>/dev/null || echo 0)

if [ "$ROWS" != "0" ]; then
  echo "$DB already holds $ROWS rows in users/facilities — leaving it alone." >&2
  echo "Restore aborted. Check the database before running this again." >&2
  exit 1
fi

echo "--- dropping the empty schema migrate:fresh left behind ---"
mysql -h"$HOST" -u"$USER" -p"$PASS" -e "
  DROP DATABASE IF EXISTS \`$DB\`;
  CREATE DATABASE \`$DB\` COLLATE 'utf8mb4_0900_ai_ci';"

echo "--- replaying $START .. $STOP ---"

# `--force` so one unexpected statement does not abandon the rest of the
# replay. Every error is kept and checked below rather than ignored.
set +e
mysqlbinlog --read-from-remote-server --host="$HOST" --user="$USER" --password="$PASS" \
  --database="$DB" --start-datetime="$START" --stop-datetime="$STOP" "${LOGS[@]}" \
  2>binlog-read.log \
| mysql -h"$HOST" -u"$USER" -p"$PASS" --force "$DB" 2>mysql-errors.log
set -e

echo "--- replay errors ---"
grep -v "Using a password" mysql-errors.log || echo "(none)"

echo "--- restored table counts ---"
mysql -h"$HOST" -u"$USER" -p"$PASS" -e "
  SELECT (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB') AS tables,
         (SELECT COUNT(*) FROM $DB.users)       AS users,
         (SELECT COUNT(*) FROM $DB.memberships) AS memberships,
         (SELECT COUNT(*) FROM $DB.facilities)  AS facilities,
         (SELECT COUNT(*) FROM $DB.orders)      AS orders,
         (SELECT COUNT(*) FROM $DB.settings)    AS settings;"

echo
echo "Expected roughly: 525 users, 513 memberships."
echo
echo "The replay stops BEFORE today's drop, so it also predates the three new"
echo "migrations added today. Finish with:"
echo
echo "    php artisan migrate"
echo "    php artisan db:seed --class=SettingSeeder"
echo
echo "which add orders.user_id, users.gender, the free-delivery columns on"
echo "orders, and the otp_* settings rows."
