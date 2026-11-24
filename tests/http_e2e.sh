#!/bin/bash
# End-to-end HTTP checks against a RUNNING app with the seeded test accounts (php bin/seed_test_data.php).
# Usage: BASE=http://localhost:8888/public/index.php MYSQL_CMD="mysql -uroot -proot sdms" bash tests/http_e2e.sh
# Needs curl + the mysql client (only to force a permission to expire and to read the user id). Dev/test data only.
B=${BASE:-http://127.0.0.1:8080/index.php}
SQL=${MYSQL_CMD:-mysql -uroot sdms}
PW='TestPassword#2026'
tok(){ grep -o 'name="csrf_token" value="[0-9a-f]*"' | head -1 | sed 's/.*value="//;s/"//'; }
code(){ curl -s -m 20 -o /dev/null -w "%{http_code}" "$@"; }
login(){ T=$(curl -s -m 10 -c $2 -b $2 "$B?r=login" | tok)
  curl -s -m 30 -o /dev/null -w "%{redirect_url}" -c $2 -b $2 --data-urlencode "csrf_token=$T" --data-urlencode "identifier=$1" --data-urlencode "password=${3:-$PW}" "$B?r=login"; }
chk(){ [ "$2" = "$3" ] && r=PASS || r=FAIL; printf "[%s] %-52s got=%s expected=%s\n" $r "$1" "$2" "$3"; }
J=$(mktemp -d); trap 'rm -rf $J' EXIT
login alice $J/a >/dev/null; login bob $J/b >/dev/null; login admin_test $J/ad >/dev/null
BOBID=$($SQL -N -e "select id from users where username='bob'")
printf 'Top secret plan: alice only\n' > $J/secret.txt
T=$(curl -s -c $J/a -b $J/a "$B?r=doc/upload" | tok)
curl -s -o /dev/null -c $J/a -b $J/a -F "csrf_token=$T" -F "document=@$J/secret.txt;filename=secret.txt;type=text/plain" "$B?r=doc/upload"
ID=$($SQL -N -e "select max(id) from documents")
chk "alice views own document" "$(code -b $J/a "$B?r=doc/view&id=$ID")" 200
chk "bob views alice's document (T1)" "$(code -b $J/b "$B?r=doc/view&id=$ID")" 403
chk "bob guesses non-existent id (T2)" "$(code -b $J/b "$B?r=doc/view&id=999999")" 403
chk "anonymous request redirected to login" "$(code "$B?r=doc/view&id=$ID")" 302
chk "POST delete without CSRF token" "$(code -b $J/a -d "id=$ID" "$B?r=doc/delete")" 403
chk "disallowed HTTP method (GET on POST route)" "$(code -b $J/a "$B?r=doc/delete&id=$ID")" 405
T=$(curl -s -c $J/a -b $J/a "$B?r=share&id=$ID" | tok)
curl -s -o /dev/null -b $J/a --data-urlencode "csrf_token=$T" -d "recipient_id=$BOBID&level=viewer" "$B?r=share&id=$ID"
chk "bob (viewer) views after share" "$(code -b $J/b "$B?r=doc/view&id=$ID")" 200
chk "bob downloads without download flag (T5)" "$(code -b $J/b "$B?r=doc/download&id=$ID")" 403
chk "bob opens update page as viewer (T5)" "$(code -b $J/b "$B?r=doc/edit&id=$ID")" 403
TB=$(curl -s -b $J/b "$B?r=doc/show&id=$ID" | tok)
chk "bob deletes as viewer (T5)" "$(code -b $J/b -d "csrf_token=$TB&id=$ID" "$B?r=doc/delete")" 403
chk "bob opens share page (T5)" "$(code -b $J/b "$B?r=share&id=$ID")" 403
chk "admin opens alice's document" "$(code -b $J/ad "$B?r=doc/view&id=$ID")" 403
T=$(curl -s -c $J/a -b $J/a "$B?r=share&id=$ID" | tok)
curl -s -o /dev/null -b $J/a --data-urlencode "csrf_token=$T" -d "id=$ID&user_id=$BOBID" "$B?r=share/revoke"
chk "bob views after revoke (T6)" "$(code -b $J/b "$B?r=doc/view&id=$ID")" 403
T=$(curl -s -c $J/a -b $J/a "$B?r=share&id=$ID" | tok)
curl -s -o /dev/null -b $J/a --data-urlencode "csrf_token=$T" -d "recipient_id=$BOBID&level=editor&can_download=1" "$B?r=share&id=$ID"
chk "bob (editor+download) downloads" "$(code -b $J/b "$B?r=doc/download&id=$ID")" 200
$SQL -e "update document_permissions set expires_at='2000-01-01 00:00:00'"
chk "bob after permission expiry" "$(code -b $J/b "$B?r=doc/download&id=$ID")" 403
chk "normal user opens admin audit page" "$(code -b $J/b "$B?r=admin/audit")" 403
# --- password reset (dev: link is written to the server-side mail log) ---
MAILLOG=${MAIL_LOG:-private_storage/logs/mail.log}; NEWPW='NewPassword#2027'; : > "$MAILLOG" 2>/dev/null
T=$(curl -s -c $J/x -b $J/x "$B?r=forgot" | tok); curl -s -o /dev/null -c $J/x -b $J/x --data-urlencode "csrf_token=$T" -d "email=carol@test.local" "$B?r=forgot"
TK=$(grep -o 'token=[0-9a-f]*' "$MAILLOG" | tail -1 | cut -d= -f2)
chk "reset token issued to server log (64 hex chars)" "${#TK}" 64
T=$(curl -s -c $J/x -b $J/x "$B?r=reset&token=$TK" | tok)
curl -s -o /dev/null -c $J/x -b $J/x --data-urlencode "csrf_token=$T" -d "token=$TK&password=$NEWPW&confirm=$NEWPW" "$B?r=reset"
chk "carol can sign in with the NEW password" "$(login carol $J/c0 "$NEWPW" | grep -c dashboard)" 1
chk "reset link cannot be used a second time" "$(curl -s "$B?r=reset&token=$TK" | grep -c 'invalid or has expired')" 1
for i in 1 2 3; do login carol $J/c "bad$i" >/dev/null; done; for i in 1 2; do login carol@test.local $J/c "bad$i" >/dev/null; done
chk "correct password refused while locked out" "$(login carol $J/c2 "$NEWPW" | grep -c dashboard)" 0
T=$(curl -s -c $J/ad -b $J/ad "$B?r=admin/audit" | tok)
R=$(curl -s -L -c $J/ad -b $J/ad --data-urlencode "csrf_token=$T" "$B?r=admin/verify" | grep -oE "Audit chain verified: [0-9]+ records|TAMPERING DETECTED" | head -1)
echo "[INFO] admin audit verification: $R"
echo "[INFO] cookie flags: $(curl -sI "$B?r=login" | grep -i set-cookie | tr -d '\r')"
