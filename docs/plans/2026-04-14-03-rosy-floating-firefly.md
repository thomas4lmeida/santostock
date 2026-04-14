# Plan: Fix — Quantity input disabled on new-order page

## Context

After shipping Phase 4 (PR #18), the Create page at `/pedidos/create` shows the "Quantidade" input greyed out and not accepting typing, with the helper text "Quantidade bloqueada: existem recebimentos registrados." visible — even though the order doesn't exist yet and has no receipts.

Root cause: **Vue 3 boolean prop casting.** When a prop is declared as `boolean` and the parent omits it, Vue coerces the absent prop to `false` rather than leaving it `undefined`. In `OrderForm.vue` the prop is declared `canEditQuantity?: boolean` and the template gates the disabled state on `canEditQuantity === false`. On Create, `Create.vue` doesn't pass the prop → Vue casts it to `false` → the check evaluates `true` → the field gets disabled and the "bloqueada" text shows. On Edit the bug is hidden because the controller always sends a real boolean.

Tests didn't catch this because the existing feature tests exercise HTTP endpoints, not the rendered Vue form. Store-endpoint validation still passes for admin creating an order because the POST succeeds when the form is bypassed.

## Fix

One targeted change in `resources/js/pages/Orders/OrderForm.vue`:

1. Replace the raw `defineProps<…>()` with `withDefaults(defineProps<…>(), { canEditQuantity: true })` so that an omitted prop resolves to `true` instead of `false`. This matches the intent: by default the field is editable; the Edit page explicitly downgrades it when receipts exist.
2. Simplify the two gates that currently read `canEditQuantity === false`:
   - `:disabled="!canEditQuantity"` on the `<input>`
   - `v-if="!canEditQuantity"` on the helper `<p>`

No controller, request, migration, route, or test needs to change for the minimal fix.

## Regression guard

Add one Pest 4 **browser** smoke test that exercises the Create page end-to-end so this class of UI-only bug can't silently regress:

- File: `tests/Browser/Orders/CreateOrderBrowserTest.php` (new)
- Flow: admin logs in → visits `/pedidos/create` → picks a seeded supplier + product → types a quantity → submits → lands on `/pedidos` with the order row visible.
- Seeds: create admin + one `Supplier` + one `Product` in the test body (`RefreshDatabase`).
- Assertions: the quantity input is **not** disabled, the submit succeeds, the database row exists.

This matches the plan's per-phase "browser smoke test" requirement that Phases 5/6/7 will also use, so we're establishing the pattern here one phase early — cheap insurance.

## Critical files

- `resources/js/pages/Orders/OrderForm.vue` (modify — the fix)
- `tests/Browser/Orders/CreateOrderBrowserTest.php` (new — regression guard)

## Verification

- `./vendor/bin/sail test --compact tests/Feature/Orders/ tests/Browser/Orders/` — green.
- `./vendor/bin/sail npm run build` — green (no new TS errors from `withDefaults`).
- Manual smoke: log in as admin, go to `/pedidos/create`, confirm the Quantidade field accepts typing, no "bloqueada" text visible, submit succeeds.
- Edit regression check: open an existing order with a receipt via tinker, visit `/pedidos/{id}/edit`, confirm the field is still disabled and the helper text still appears.
- Preflight + signed commit on a fix branch `fix/orders-create-quantity-disabled` off the current Phase 4 PR branch (or off `main` after PR #18 merges — ask before branching).

## Out of scope

- Changing `canEditQuantity`'s server-side semantics (it stays `! $order->receipts()->exists()` / `$order->receipts_count === 0`).
- Adding browser smokes for Edit / Show / Cancel / CloseShort (Phase 5+ work).
- Any other UI polish — this is a narrow bugfix.
