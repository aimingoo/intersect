#!/bin/sh

CLIENT_ID="${2:-c1285a991ba7db5c395a}"


if [[ -z "$1" ]]; then
	echo 'Usage: bash try.sh <Gateway>'
	exit
else
	echo "Try gateway: $1"
fi

PROTOCOL=$(echo "$1" | grep -Eoe '^https?://')
PROTOCOL=${PROTOCOL:-http://}
DOMAIN=$(echo "$1" | sed -E 's/^https*:\/\/|\/$//g')

echo '-> Get code, open browser and pick the code from redirected url'
open "https://github.com/login/oauth/authorize?scope=public_repo&client_id=$CLIENT_ID"

code=""
while [[ -z "$code" ]]; do
	printf '%s' '-> Input code: '
	read code
done

echo "<- Return access_token:"
echo "=========================="
source headers.sh | xargs curl -d "client_id=$CLIENT_ID&code=$code" "$PROTOCOL$DOMAIN/login/oauth/access_token"
echo
echo "=========================="

echo "Done."
