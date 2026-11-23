#!/usr/bin/env bash
# CI readiness gate for the Docker Compose stack.
#
# Usage:
#   bash scripts/wait-for-compose-ready.sh <deadline-seconds> [service[:expectation] ...]
#
# Expectations:
#   healthy  - container status contains "(healthy)" (default)
#   exited0  - one-shot container finished with exit code 0
#
# Unlike an arbitrary count of "healthy" lines or a fixed `sleep`, this gate:
#   * checks an explicit list of REQUIRED services by name,
#   * fails with a non-zero exit status after the defined deadline,
#   * prints Compose status and tail logs of the services that are not ready.
# A failing gate therefore stops the job BEFORE any smoke test or test suite runs.

set -euo pipefail

COMPOSE_FILES=(-f docker-compose.yml -f docker-compose.ci.yml)
DEADLINE="${1:?usage: wait-for-compose-ready.sh <deadline-seconds> [service[:expectation] ...]}"
shift

if [ "$DEADLINE" -le 0 ]; then
  echo "[readiness] ERROR: deadline must be a positive number of seconds." >&2
  exit 2
fi

log() { echo "[readiness] $*"; }

status_of() {
  # Print the Compose status text for exactly one service (empty when absent).
  docker compose "${COMPOSE_FILES[@]}" ps -a --format '{{.Service}} {{.Status}}' \
    | awk -v wanted="$1" '$1 == wanted { sub(/^[^ ]+ /, ""); print; exit }'
}

start=$((SECONDS))
while :; do
  pending=()
  ready=0

  for spec in "$@"; do
    service="${spec%%:*}"
    expectation="healthy"
    if [[ "$spec" == *:* ]]; then
      expectation="${spec#*:}"
    fi

    status="$(status_of "$service")"
    case "$expectation" in
      healthy)
        if [[ "$status" == *"(healthy)"* ]]; then
          ready=$((ready + 1))
        else
          pending+=("$service (${status:-absent})")
        fi
        ;;
      exited0)
        if [[ "$status" == [Ee]xited\ "(0)"* ]]; then
          ready=$((ready + 1))
        else
          pending+=("$service (${status:-absent})")
        fi
        ;;
      *)
        echo "[readiness] ERROR: unknown expectation '$expectation' for '$service'." >&2
        exit 2
        ;;
    esac
  done

  if [ "${#pending[@]}" -eq 0 ]; then
    log "READY: $ready/$ready required services satisfied in $((SECONDS - start))s."
    exit 0
  fi

  elapsed=$((SECONDS - start))
  if [ "$elapsed" -ge "$DEADLINE" ]; then
    log "TIMEOUT after ${elapsed}s. Required services not ready:"
    for item in "${pending[@]}"; do
      log "  - $item"
    done
    log "--- compose ps -a ---"
    docker compose "${COMPOSE_FILES[@]}" ps -a || true
    log "--- logs of not-ready services ---"
    for item in "${pending[@]}"; do
      service="${item%% *}"
      log "### $service"
      docker compose "${COMPOSE_FILES[@]}" logs --no-color --tail=100 "$service" || true
    done
    exit 1
  fi

  log "waiting (${elapsed}s/${DEADLINE}s): ${pending[*]}"
  sleep 5
done
