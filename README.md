# 🧠 Laravel Face Verification (Hyperverge)

This module provides flexible face login functionality using Hyperverge's Liveliness and Face Match APIs.

---

## ✅ 1. Flexible Login Identifier

Users can log in using any of the following:

- `user_id`
- `email`
- `mobile`

Configured via:

```php
protected array $fields = ['user_id'];
```

Validation rules are dynamic based on selected fields.

---

## 📸 2. Capture & Submit via Vue Frontend

Features:

- Camera preview
- Selfie capture and preview
- Retake on failure
- Auto-login with `autoFaceLogin` prop

---

## 🧠 3. `FaceLoginController` (Refactored)

- Locates user based on configured identifier.
- Pulls profile photo via Spatie MediaLibrary.
- Uses `FaceVerificationPipeline`:
    1. **LivelinessService**
    2. **MatchFaceService**

- Logs response, failures, and success
- Friendly error messaging using `summary.details`

---

## 🔍 4. `LivelinessService`

Checks if the selfie is valid using `/checkLiveness`.

- Validates:
    - `liveFace.value`
    - `qualityChecks`:
        - `eyesClosed`, `blur`, `maskPresent`, `multipleFaces`, etc.

- Throws exceptions with aggregated reasons:
    - “Eyes closed in selfie”
    - “Face is blurred”
    - “Multiple faces detected”

- Cleans up the temporary selfie afterward.

---

## 🧪 5. `MatchFaceService`

Compares selfie with stored profile using `/matchFace`.

- Extracts:
    - `match.value` (`yes`/`no`)
    - `confidence` (`high`, `medium`, etc.)
    - `summary.action` (`pass`/`fail`)

- Maps confidence to numeric scores:

```php
[
  'very_high' => 95,
  'high'      => 85,
  'medium'    => 60,
  'low'       => 30,
]
```

- Returns **array**, not object
- Uses `Arr::get()` for safer extraction

---

## 🔗 6. `FaceVerificationPipeline`

Handles full verification in order:

```php
$result = app(FaceVerificationPipeline::class)->verify(
    referenceCode: $referenceCode,
    base64img: $request->base64img,
    storedImagePath: $storedImagePath
);
```

- Ensures liveliness passes before matching
- Throws user-friendly errors

---

## 🧪 7. Pest Tests

- Uses `Mockery` spies and mocks
- Covers:
    - Valid face match
    - Unsuccessful matches
    - Missing fields
    - Expected service method calls

---

## 🛠 Future Improvements

- Configurable confidence thresholds
- Environment-based toggling of checks
- Auto-capture via face detection (PWA friendly)
- Pluggable pipeline stages

---

> Built with 🧠 by combining precision and practical Laravel structure.
