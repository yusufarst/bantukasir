# 04 â€” Authentication, RBAC and security

Backend authorization is mandatory. Hiding UI is not authorization.

## Roles

### SUPER_ADMIN â€” Pemilik

Full business authority: accounts/security, BusinessProfile/policies, catalog/inventory/orders/services, sensitive corrections/refunds, private finance, audit and recovery oversight. High-risk actions require recent authentication where specified.

### OPERATIONS_ADMIN â€” Admin Operasional

Routine operations: catalog/selling prices, barcode/labels, customers/orders/bookings, receiving/non-sale issue/transfer, reservation/fulfillment, service schedules/progress, stock/restock and safe operational reports. Payment recording is allowed when the workflow authorizes it; cash requires the user's own shift.

Default exclusions: acquisition cost/HPP/margin/profit, user/security administration, owner-only refund/sensitive finance correction and recovery secrets.

### CASHIER â€” Kasir

Counter work: own shift, fast POS, product/service search/scan, simple order/booking, minimal customer creation, customer payments, allowed counter fulfillment, receipts/reprints and necessary own/current transaction history.

Default exclusions: stock adjustment/receiving/transfer, arbitrary price master changes, private finance, user/settings/audit administration and refund execution.

## Permission model

Use named backend permissions mapped to fixed roles; no dynamic role-builder UI in Core. Apply object/scope checks to register/location/order context. Reprint/recovery rechecks current authorization.

## Authentication

Use self-hosted Better Auth after implementation-time version/security verification:
- no public signup;
- individual accounts only;
- secure invite/reset;
- owner TOTP before pilot;
- session revocation on disable/security change;
- secure production cookies/origin protections;
- rate limiting on sensitive endpoints;
- no shared cashier/owner account.

## Sensitive data

Owner-only by default: acquisition cost, HPP, margin/gross profit, cost evidence, recovery details and sensitive audit/security details.

Staff/public DTOs are explicit allowlists. Never serialize a rich internal entity and rely on UI hiding.

Instant sale requires no customer identity. Deferred order collects only data needed to identify/contact the customer and execute the order.

## Audit and secrets

Audit successful sensitive/business mutations with actor, server time, action, entity/source, command identity and reason where required. Denied high-risk access is security logged separately.

Ledger, payments, commercial history and audit are never silently deleted; corrections append new facts.

Never hardcode/commit passwords, API keys, DB credentials, private keys, VPS credentials or production secrets. `.env.example` contains variable names with empty values; startup validates required configuration and fails safely.
