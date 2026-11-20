#!/usr/bin/env bash
# BantuKasir: download and decrypt one backup on the operator's own machine (BACKUP_RECOVERY RB-01 step 8,
# RB-02 step 3; BKP-07, BKP-08; TASK-037 scope item 7). Run by the operator, a person, never on the VPS
# or in an application container: the recovery key never leaves this machine.
#
#     fetch-backup.sh <backup id> <work folder>
#
# with, in the environment of the operator's shell (never written anywhere by this script):
#
#     BANTUKASIR_STORE_ENDPOINT   the store's S3 endpoint, over HTTPS
#     BANTUKASIR_STORE_REGION     its region; us-east-1 when empty
#     BANTUKASIR_STORE_BUCKET     the environment's bucket
#     BANTUKASIR_STORE_KEY_ID     the operator's storage access, read only
#     BANTUKASIR_STORE_KEY
#     BANTUKASIR_RECOVERY_KEY     the file of the age identity, the recovery key of BKP-20
#
# An endpoint over plain HTTP is refused: age does not authenticate who encrypted an object, so anyone on
# the way could replace a whole backup with one encrypted to the same recipient. Only the local rehearsal
# and its tests reach the test server over HTTP, with BANTUKASIR_STORE_PLAIN_HTTP=local-test-store, which
# the operator never sets.
#
# It downloads the manifest of the backup from the first tier that holds it, then the dump from the first
# tier that still holds a copy of it — an older backup's copy under db/6h/ is gone after its days there,
# while its weekly or monthly copy stays (BACKUP_RECOVERY section 6) — and every file the manifest names,
# into the empty work folder; checks the SHA-256 of the encrypted dump and of every
# decrypted object against the manifest; and leaves, under <work>/plain, manifest.json, dump.pgcustom and
# files/<storage key>, with the encrypted objects removed. It prints the SHA-256 of the manifest object,
# for the drill's record (OP-08), and nothing secret. Any difference stops it with an error: take the next
# older backup (RB-01 step 8). Destroy the work folder when the restore or the drill ends (BKP-07).
set -euo pipefail

fail() {
    echo "FETCH_FAILED $*" >&2
    exit 1
}

backup="${1:-}"
work="${2:-}"

[[ "$backup" =~ ^[0-9]{8}T[0-9]{6}Z-[A-Za-z0-9]{8}$ ]] || fail "the backup identifier has another form"
[ -n "$work" ] || fail "no work folder"
for name in BANTUKASIR_STORE_ENDPOINT BANTUKASIR_STORE_BUCKET BANTUKASIR_STORE_KEY_ID BANTUKASIR_STORE_KEY BANTUKASIR_RECOVERY_KEY; do
    [ -n "${!name:-}" ] || fail "$name is not set"
done
[ -r "$BANTUKASIR_RECOVERY_KEY" ] || fail "the recovery key cannot be read"
[[ "$BANTUKASIR_STORE_BUCKET" =~ ^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$ ]] || fail "the bucket has another form"

region="${BANTUKASIR_STORE_REGION:-}"
region="${region:-us-east-1}"
endpoint="${BANTUKASIR_STORE_ENDPOINT%/}"
proto='=https'
if [ "${BANTUKASIR_STORE_PLAIN_HTTP:-}" = local-test-store ]; then
    proto='=https,http'
elif [[ ! "$endpoint" =~ ^https:// ]]; then
    fail "the store endpoint is not HTTPS"
fi

mkdir -p "$work"
[ -z "$(ls -A "$work")" ] || fail "the work folder is not empty"
chmod 700 "$work"
mkdir -p "$work/encrypted" "$work/plain/files"

# One object of the bucket, signed with Signature Version 4; the credentials reach curl on its standard
# input, never in its arguments.
download() {
    local key="$1" target="$2"
    [[ "$key" =~ ^(db/(6h|weekly|monthly)/[0-9]{8}T[0-9]{6}Z-[A-Za-z0-9]{8}\.(dump|manifest)\.age|files/[A-Za-z0-9_-]{32,128}\.age)$ ]] || fail "an object key has another form"
    printf 'user = "%s:%s"\n' "$BANTUKASIR_STORE_KEY_ID" "$BANTUKASIR_STORE_KEY" \
        | curl --config - --silent --show-error --fail --location --proto "$proto" --proto-redir "$proto" \
            --aws-sigv4 "aws:amz:${region}:s3" \
            --output "$target" "${endpoint}/${BANTUKASIR_STORE_BUCKET}/${key}"
}

hash_of() {
    sha256sum "$1" | cut -d' ' -f1
}

decrypt() {
    age --decrypt --identity "$BANTUKASIR_RECOVERY_KEY" --output "$2" "$1"
}

# The manifest, from the first tier that holds it.
manifest_key=""
for tier in 6h weekly monthly; do
    if download "db/${tier}/${backup}.manifest.age" "$work/encrypted/manifest.age" 2>/dev/null; then
        manifest_key="db/${tier}/${backup}.manifest.age"
        break
    fi
done
[ -n "$manifest_key" ] || fail "no tier holds the manifest of this backup"
manifest_hash="$(hash_of "$work/encrypted/manifest.age")"
decrypt "$work/encrypted/manifest.age" "$work/plain/manifest.json" || fail "the manifest does not decrypt with this recovery key"
manifest="$(cat "$work/plain/manifest.json")"

grep -Eq '^\{"format":1,"backupId":"'"$backup"'",' <<< "$manifest" || fail "the manifest is not of format 1 for this backup"

# The dump: its encrypted hash, then its plaintext hash, as the manifest records them.
dump_part="$(grep -oE '"dump":\{"objectKey":"[^"]+","objectKeys":\[[^]]*\],"size":[0-9]+,"sha256":"[0-9a-f]{64}","plaintextSha256":"[0-9a-f]{64}"' <<< "$manifest")" \
    || fail "the manifest names no dump"
dump_sha256="$(sed -E 's/^.*,"sha256":"([0-9a-f]{64})".*$/\1/' <<< "$dump_part")"
dump_plain_sha256="$(sed -E 's/^.*"plaintextSha256":"([0-9a-f]{64})"$/\1/' <<< "$dump_part")"

# Every tier's copy is the same encrypted dump; the first one that the store still serves, in the order of
# the manifest's object keys.
dump_copies="$(grep -oE '"objectKeys":\[[^]]*\]' <<< "$dump_part" | grep -oE "db/(6h|weekly|monthly)/${backup}\.dump\.age")" \
    || fail "the manifest names no copy of the dump"
dump_fetched=""
while IFS= read -r dump_key; do
    if download "$dump_key" "$work/encrypted/dump.age" 2>/dev/null; then
        dump_fetched="$dump_key"
        break
    fi
done <<< "$dump_copies"
[ -n "$dump_fetched" ] || fail "no tier holds the dump of this backup"
[ "$(hash_of "$work/encrypted/dump.age")" = "$dump_sha256" ] || fail "the encrypted dump differs from the manifest"
decrypt "$work/encrypted/dump.age" "$work/plain/dump.pgcustom" || fail "the dump does not decrypt with this recovery key"
[ "$(hash_of "$work/plain/dump.pgcustom")" = "$dump_plain_sha256" ] || fail "the decrypted dump differs from the manifest"
rm -f "$work/encrypted/dump.age"

# Every file the manifest names, decrypted and checked against stored_files.sha256 as the manifest
# recorded it.
count="$(grep -oE '"files":\{"count":[0-9]+' <<< "$manifest" | grep -oE '[0-9]+$')" || fail "the manifest names no files"
fetched=0
while IFS= read -r item; do
    [ -n "$item" ] || continue
    storage_key="$(sed -E 's/^\{"storageKey":"([A-Za-z0-9_-]+)".*$/\1/' <<< "$item")"
    file_sha256="$(sed -E 's/^.*"sha256":"([0-9a-f]{64})".*$/\1/' <<< "$item")"
    object_key="$(sed -E 's/^.*"objectKey":"([^"]+)"\}$/\1/' <<< "$item")"
    [ "$object_key" = "files/${storage_key}.age" ] || fail "a file's object key does not name its storage key"

    download "$object_key" "$work/encrypted/file.age" || fail "a file cannot be downloaded"
    decrypt "$work/encrypted/file.age" "$work/plain/files/$storage_key" || fail "a file does not decrypt with this recovery key"
    [ "$(hash_of "$work/plain/files/$storage_key")" = "$file_sha256" ] || fail "a decrypted file differs from the manifest"
    rm -f "$work/encrypted/file.age"
    fetched=$((fetched + 1))
done < <(grep -oE '\{"storageKey":"[A-Za-z0-9_-]+","purpose":"[A-Z_]+","size":[0-9]+,"sha256":"[0-9a-f]{64}","objectKey":"files/[A-Za-z0-9_-]+\.age"\}' <<< "$manifest" || true)

[ "$fetched" -eq "$count" ] || fail "the manifest counts $count files and names $fetched"
rm -rf "$work/encrypted"

echo "FETCHED $backup files=$fetched manifest_sha256=$manifest_hash"
