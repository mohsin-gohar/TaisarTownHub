# Critical Repair Concurrency Audit

Branch: `fix/critical-security-integrity-repairs` @ `2fb1996`

## Fixes
1. AgencyPromotionService::promote() — moved duplicate check and slot check inside transaction with lockForUpdate()
2. AgencyPromotionService::assertSlotAvailable() — added lockForUpdate()
3. MediaController::sell() — wrapped in DB::transaction with lockForUpdate, re-check status inside transaction
4. FinanceController::storeSchedules() — wrapped in DB::transaction with parent lock

## Principle: all competing writes serialized via database locks.