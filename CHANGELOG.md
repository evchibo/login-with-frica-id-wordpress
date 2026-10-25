# Changelog

## 1.0.4

- Add safe verification reason categories and OpenSSL dependency checks.
- Cover malformed JWTs, unknown signing keys and invalid identity claims.

## 1.0.3

- Apply consistent bounded clock tolerance during signature/claim verification.
- Add precise safe verification errors and private, expiring diagnostic metadata.
- Preserve strict expired-token, nonce, issuer, audience, signature and subject checks.

## 1.0.2

- Accept the legacy HTTP 201 token success status without weakening identity verification.
- Distinguish rejected authorization, provider rate limits and connection failures.
- Add regression coverage for real token status behavior and failed provider responses.

## 1.0.1

- Correct WordPress.org contributor attribution to the verified publishing account, `ivanchibo`.
- No changes to authentication behavior.

## 1.0.0

- Frica ID OpenID Connect login with PKCE S256 and verified email.
- Login/registration buttons, login shortcode and administrator setup.
- Explicit session-bound account linking and disconnect.
- Optional registration with Subscriber permissions only.
- Single-use state, signed ID-token checks and same-origin return paths.
- Reproducible ZIP package, compatibility tests and tagged GitHub releases.
