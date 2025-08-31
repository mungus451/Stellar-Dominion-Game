#!/usr/bin/env bash
set -euo pipefail

# Configure
BASE_URL="https://starlight-dominion.com"   # ← change to your domain
TOKEN="enclave-cron"    # ← also set the same token in the PHP files below

# 1) Train even split for a random Enclave member (only if they have citizens)
curl -fsS -m 20 -A "EnclaveCron/1.0" \
  -H "X-Enclave-Token: ${TOKEN}" \
  "${BASE_URL}/api/enclave_train_even.php"

# 2) Attack a random eligible target using a random Enclave member
curl -fsS -m 20 -A "EnclaveCron/1.0" \
  -H "X-Enclave-Token: ${TOKEN}" \
  "${BASE_URL}/api/enclave_attack_random.php"
