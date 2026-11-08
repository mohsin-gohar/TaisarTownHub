# TaisarTownHub — Request Validation & Sanitization (Pillar 7)

**Version:** 2.0.0 · **Last updated:** 2026-11-08

## 1. Validation Strategy

Backend uses Laravel Form Requests / inline `$request->validate()` with strict rules. Frontend mirrors validation in form handlers and `utils/validation.ts`. Both layers validate; backend is the source of truth.

## 2. Key Validation Rules

### Property
```php
'title' => 'required|string|max:255',
'type' => 'required|in:Residential Plot,Commercial Plot,House / Villa,Commercial Shop,Apartment,Farmhouse',
'phase' => 'required|in:Phase 1,Phase 2,Phase 3',
'sector' => 'required|string|max:50',
'size_sqyd' => 'required|integer|min:1',
'price_pkr' => 'required|numeric|min:0',
'status' => 'in:Available,Sold,Booked,On Hold',
```

### Dealer
```php
'full_name' => 'required|string|max:255',
'cnic' => 'required|regex:/^\d{5}-\d{7}-\d{1}$/', // masked display, stored normalized
'phone' => 'required|regex:/^\+92\s?\d{3}\s?\d{7}$/',
'commission_type' => 'in:percentage,fixed',
'commission_value' => 'numeric|min:0',
```

### Transaction
```php
'property_id' => 'required|exists:properties,id',
'seller_id' => 'required|different:buyer_id',
'buyer_id' => 'required',
'deal_amount_pkr' => 'required|numeric|min:1',
'advance_pkr' => 'numeric|min:0|lte:deal_amount_pkr',
```

### Agency Video (store)
```php
'title' => 'required|string|max:255',
'description' => 'nullable|string|max:2000',
'videoUrl' => 'required|url|max:2048',
'thumbnailUrl' => 'nullable|url|max:2048',
'agencyName' => 'required|string|max:255',
'phase' => 'nullable|string|max:50',
'sector' => 'nullable|string|max:50',
'duration' => 'nullable|string|max:20',
```

### Inquiry (public)
```php
'fullName' => 'required|string|max:255',
'phone' => 'required|string|max:50',
'email' => 'nullable|email',
'message' => 'required|string|max:2000',
'subject' => 'nullable|string|max:255',
```

### Dealer File Upload
```php
'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:5120', // 5MB
'dealer_id' => 'required|exists:dealers,id',
```

### Workspace save
```php
'collections' => 'required|array',
// each collection validated as array of rows with non-empty 'id'
```

## 3. Sanitization

- **SQL injection:** Eloquent parameterized queries; no raw string interpolation.
- **XSS:** Frontend escapes via React (no `dangerouslySetInnerHTML` for user content); backend strips tags on text fields where stored.
- **CNIC masking:** Display layer masks (`XXXXX-XXXXXXX-X` → `XXXXX-XXXXXXX-X` partially); unmasked only for super_admin via permission gate.
- **File validation:** MIME + size checked server-side; data URLs stored, not executed.
- **Rate limiting:** throttles on auth (5/min), videos (10/min), comments (10/min), likes (30/min), inquiries (10/min), subscribers (10/min).

## 4. Frontend Validation Helpers

`src/utils/validation.ts` provides:
- `validateInquiryForm()` — required fields, email format, phone format.
- `calculateMediaSaleProfit()` — rejects invalid prices, cost > selling price.
- `formatPKRCurrency()`, `generateWhatsAppLink()`.

## 5. Edge Case Handling

- Empty/whitespace-only inputs rejected.
- Oversized files rejected before upload with clear message.
- Duplicate email/phone handled via unique constraints + 422 response.
- Invalid JSON payloads → 422 with field-level errors.
- Missing required collection in workspace save → skipped (not error), so partial saves work.
- `advance > dealAmount` → rejected with specific message.

## 6. Error Response Format

```json
{ "message": "The given data was invalid.", "errors": { "field": ["reason"] } }
```
Frontend parses `errors` and shows field-level or toast message.
