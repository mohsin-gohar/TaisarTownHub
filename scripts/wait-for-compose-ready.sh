#!/usr/bin/env bash
# CI readiness gate for the Docker Compose stack.
#
# Usage:
#   bash scripts/wait-for-compose-ready.sh <deadline-seconds> [service[:expectation] ...]
#
# Expectations:
#   healthy  - container state is "running" AND health is "healthy" (default)
#   exited0  - one-shot container state is "exited" AND exit code is 0
#
# Unlike an arbitrary count of "healthy" lines or a fixed `sleep`, this gate:
#   * checks an explicit list of REQUIRED services by name,
#   * reads machine fields (.State/.Health/.ExitCode) instead of matching the
#     human ".Status" text, which differs between Docker Compose versions
#     (`exited` vs `Exited (0) ...`) and made one-shot services look pending,
#   * fails with a non-zero exit status after the defined deadline,
#   * prints Compose status and tail logs of the services that are not ready,
#   * emits a GitHub Actions error annotation with the pending services, so a
#     failure is diagnosable even when raw job logs are inaccessible.
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

# Emit "state|health|exitcode" for exactly one service (empty when absent).
status_of() {
  docker compose "${COMPOSE_FILES[@]}" ps -a --format '{{.Service}}|{{.State}}|{{.Health}}|{{.ExitCode}}' \
    | awk -F'|' -v wanted="$1" '$1 == wanted { print $2 "|" $3 "|" $4; exit }'
}

describe() {
  local service="$1" raw="$2"
  if [ -z "$raw" ]; then
    echo "$service (absent)"
    return
  fi
  IFS='|' read -r state health exit_code <<< "$raw"
  echo "$service (state=${state:-unknown}, health=${health:-none}, exit=${exit_code:-none})"
}

matches() {
  local expectation="$1" raw="$2"
  [ -n "$raw" ] || return 1
  IFS='|' read -r state health exit_code <<< "$raw"
  case "$expectation" in
    healthy) [ "$state" = "running" ] && [ "$health" = "healthy" ] ;;
    exited0) [ "$state" = "exited" ] && [ "$exit_code" = "0" ] ;;
    *) return 2 ;;
  esac
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
    set +e
    matches "$expectation" "$status"
    match=$?
    set -e
    if [ "$match" -eq 2 ]; then
      echo "[readiness] ERROR: unknown expectation '$expectation' for '$service'." >&2
      exit 2
    fi
    if [ "$match" -eq 0 ]; then
      ready=$((ready + 1))
    else
      pending+=("$(describe "$service" "$status")")
    fi
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
    if [ "${GITHUB_ACTIONS:-}" = "true" ]; then
      echo "::error title=Readiness gate timeout::Not ready after ${elapsed}s: ${pending[*]}"
    fi
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
