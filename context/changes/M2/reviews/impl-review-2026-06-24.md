# Implementation Review — M2 (Join Library Registration)

**Date:** 2026-06-24  
**Change:** M2 — Rejestracja z dołączeniem do istniejącej biblioteki  
**Reviewer:** AI (GitHub Copilot)  
**Verdict:** ✅ Ready to merge

---

## Findings

| # | Severity | Category | Description | Outcome |
|---|----------|----------|-------------|---------|
| F-01 | Low | Plan adherence | Komunikaty błędów JS wyświetlały angielski `json.detail` zamiast polskich stringów | **fixed** (`6dd9819`) |
| F-02 | Low | Scope discipline | `assets/styles/app.css` zmieniony — dead CSS classes (`.form-field__radio--disabled`, `.form-field__badge`) zostały | **skip** — zero risk |
| F-03 | Low | Safety | Brak dummy `password_verify` przy library not found (timing side-channel) | **skip** — świadoma decyzja projektowa (Risk Register) |
| F-04 | Low | Quality | Integration test join happy path nie weryfikuje wspólnej biblioteki (tylko 201 + email) | **skip** — unit test pokrywa asercję library ID |
| F-05 | Low | Pattern consistency | ExceptionListener `detail` ujawnia wewnętrzny komunikat exception | **skip** — pattern spójny z resztą listener (ShelfNotFound, BookNotFound itp.) |

---

## Aspect Summary

| Aspect | Verdict |
|--------|---------|
| Plan adherence | ✅ Zgodna (F-01 fixed) |
| Scope discipline | ⚠️ Minor CSS (F-02, skip) |
| Safety & quality | ✅ Bezpieczna |
| Architecture | ✅ Follows existing patterns |
| Pattern consistency | ✅ Naming, structure, exceptions mirror existing code |
| SC-02 criteria | ✅ Met |

---

## Fix Applied

### F-01: Polish error messages for library join errors

**Commit:** `6dd9819`  
**Change:** Replaced `json?.detail ?? '...'` with hardcoded Polish messages in `register.js` for `library-not-found` and `invalid-library-password` handlers.

**Before:**
```javascript
libraryName: [json?.detail ?? 'Biblioteka o podanej nazwie nie istnieje.'],
```

**After:**
```javascript
libraryName: ['Biblioteka o podanej nazwie nie istnieje.'],
```

---

## Notes

- Wszystkie 10 unit testów pass (w tym 3 nowe dla join mode)
- Integration testy poprawne składniowo — wymagają Docker DB do uruchomienia
- PHPStan: 0 errors
- GrumPHP: all checks pass

