#!/usr/bin/env bash
# BantuKasir: copy the decrypted files of a backup into the target's file volume over SSH and check every
# row of stored_files of the restored database against its file (BACKUP_RECOVERY RB-01 step 10, RB-02
# step 4; TASK-037 scope item 7). Run by the operator on the operator's own machine, after fetch-backup.sh
# and the restore of the database:
#
#     copy-files.sh <work folder> <ssh destination> <file volume path on the target>
#
# The restored database is reached through the SSH tunnel of RB-01 step 9 with psql's own environment —
# PGHOST, PGPORT, PGUSER (the migration role), PGPASSWORD and PGDATABASE — and the SSH destination with the
# operator's own key. A file is never overwritten: one already on the target stays and is checked like the
# others. Any row whose file is missing or differs from its sha256 stops it with an error.
set -euo pipefail

fail() {
    echo "COPY_FAILED $*" >&2
    exit 1
}

work="${1:-}"
destination="${2:-}"
volume="${3:-}"

[ -d "$work/plain/files" ] || fail "no decrypted files in the work folder"
[[ "$destination" =~ ^[a-z_][a-z0-9_-]*@[A-Za-z0-9.-]+$ ]] || fail "the SSH destination has another form"
[[ "$volume" =~ ^/[A-Za-z0-9/_.-]+$ ]] || fail "the file volume path has another form"

copied=0
for file in "$work"/plain/files/*; do
    [ -e "$file" ] || continue
    key="$(basename "$file")"
    [[ "$key" =~ ^[A-Za-z0-9_-]{32,128}$ ]] || fail "a file name is no storage key"

    if ssh "$destination" test -e "$volume/$key"; then
        continue
    fi

    scp -q "$file" "$destination:$volume/$key"
    copied=$((copied + 1))
done

rows="$(psql -X -At -v ON_ERROR_STOP=1 -c "select storage_key || '|' || encode(sha256, 'hex') from stored_files order by id")" \
    || fail "the restored database cannot be read"

checked=0
while IFS='|' read -r key sha256; do
    [ -n "$key" ] || continue
    [[ "$key" =~ ^[A-Za-z0-9_-]{32,128}$ ]] || fail "a storage key of the restored data has another form"
    remote="$(ssh "$destination" sha256sum "$volume/$key" 2>/dev/null | cut -d' ' -f1 || true)"
    [ "$remote" = "$sha256" ] || fail "the file of a row of stored_files is missing on the target or differs from its sha256"
    checked=$((checked + 1))
done <<< "$rows"

echo "COPIED files=$copied checked=$checked"
