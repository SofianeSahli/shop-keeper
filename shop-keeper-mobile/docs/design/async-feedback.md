# Design note: async feedback — toasts and loaders

**Status**: approved design, not yet specified. Input for the next Spec Kit feature (async services + feedback). Do NOT implement it inside 003, which is closed.

**Source**: "Carnet Kridi — Mobile UI" design canvas, row "Retours d'état — toasts et chargement":
- `FeedbackKit` — all components, rules and the action → feedback table.
- `FeedbackFlow` — interactive demo: save a ticket → busy button → blocking loader → home + success toast (tweaks: error, slow).

## Problem

Services run synchronously and freeze the UI while they execute. Services will become async. The owner must always know what is running and how it ended.

## Behaviour

| Action | While running | After |
|---|---|---|
| Save a ticket, payment, product, customer | Busy button; blocking loader if it lasts > 300 ms | Back to previous screen + success toast |
| Void a ticket, remove a product or customer | Busy button; blocking loader if > 300 ms | Success toast |
| Open a list, the dashboard, a customer ledger | Skeleton rows; previous values kept during refresh | Content appears (no toast) |
| Long background work (later: backup, sync) | Pending toast, screen stays usable | Same toast turns into success or error |
| Any failure | — | Error toast that stays, with "Réessayer"; form keeps its values |

Rules:
- **One toast service for the whole app**, rendered once at the root. One toast at a time, others queued. A pending toast turns into success/error in place.
- Toast position: top, under the top bar (never hidden by keyboard, sticky form footer or tab bar). Swipe up to dismiss.
- Durations: success 3 s, info 4 s, error until "Réessayer" or ×.
- Screen readers: toasts announced; errors assertively.
- **One blocking loader for the whole app**: shown only after 300 ms, at least 500 ms on screen, names the action and its object, blocks touches and Android back, no cancel on writes. After 8 s: "Ça prend plus de temps que prévu" / "Ne fermez pas l'application." (amber spinner).
- Screens never implement their own loading logic: every service call goes through one shared async-action helper (e.g. `useAsyncAction`) that drives busy button + loader + toast + error mapping. Services never import UI.

## Visual spec

- **Toast**: width screen − 32, top 72, radius 16, min height 64, soft shadow, slide down 220 ms. Icon circle 32, title 15/700 (what happened), detail 13 (figure or consequence), optional text action, 44 touch targets.
  - Success: bg `#DDF3EE`, border `#A9DCD2`, icon `#0F5F57` (check), action "Voir".
  - Error: bg `#FDE8E6`, border `#F4BDB7`, icon `#A3221A` ("!"), detail `#6B1F18`, action "Réessayer". New tokens `error`, `error-soft`, `error-line` — red stays distinct from the orange used for debt.
  - Info: bg `#DCE8F7`, border `#B7CDEB`, icon `#164E9C` ("i"), close ×.
  - Pending: white, border `#DCE3E0`, spinner 28 (`#164E9C` on `#DCE8F7`), detail "Vous pouvez continuer."
- **Blocking loader**: scrim `rgba(15,31,26,0.45)`, centred card radius 20, spinner 44, title 17/700 ("Enregistrement du ticket…"), object 13.5 ("4 articles · 13,150 DT"), note 12.5 ("Vos données restent sur ce téléphone.").
- **Busy button**: same button, opacity 0.85, spinner 20 + "Enregistrement…", disabled, `accessibilityState.busy`.
- **Skeleton rows**: avatar circle + bars `#E6EBE9` / `#EEF1F0`, gentle pulse.
- **Refreshing figure**: pill "Mise à jour…" with 12 spinner next to the value.

## Strings (FR / AR)

| Key | fr | ar |
|---|---|---|
| `savingTicket` / `savingPayment` / `savingProduct` / `savingCustomer` | Enregistrement du ticket… / du paiement… / du produit… / du client… | جارٍ حفظ الفاتورة… / الدفعة… / المنتج… / العميل… |
| `voidingTicket` / `removingProduct` / `removingCustomer` | Annulation du ticket… / Retrait du produit… / Retrait du client… | جارٍ إلغاء الفاتورة… / حذف المنتج… / حذف العميل… |
| `saving` | Enregistrement… | جارٍ الحفظ… |
| `dataStaysOnPhone` | Vos données restent sur ce téléphone. | بياناتك تبقى على هذا الهاتف. |
| `takingLonger` | Ça prend plus de temps que prévu | الأمر يستغرق وقتاً أطول من المتوقع |
| `doNotCloseApp` | Ne fermez pas l'application. | لا تغلق التطبيق. |
| `ticketSavedToast` / `paymentSavedToast` / `productSavedToast` / `customerSavedToast` | Ticket enregistré / Paiement enregistré / Produit enregistré / Client enregistré | تم حفظ الفاتورة / الدفعة / المنتج / العميل |
| `amountCollected` | {amount} encaissés | تم استلام {amount} |
| `notSavedToast` | {thing} non enregistré | لم يتم حفظ {thing} |
| `nothingChanged` | Rien n'a été modifié. | لم يتغير شيء. |
| `valuesKept` | Vos saisies sont gardées. | تم الاحتفاظ بما أدخلته. |
| `view` | Voir | عرض |
| `youCanContinue` | Vous pouvez continuer. | يمكنك المتابعة. |
| `updating` | Mise à jour… | جارٍ التحديث… |
