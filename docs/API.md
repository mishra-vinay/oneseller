# OneSeller mobile API (v1)

The REST API behind the Android and iOS apps. It uses the same rules as the website (seller isolation, approvals, stock, coupons, commission, ledger), because both call the same services.

- **Base URL:** `https://shop.talkforweb.in/api/v1`
- **Format:** JSON in and out. Send `Accept: application/json`. File uploads use `multipart/form-data`.
- **Money:** every amount is an **integer in paise** (₹499.00 = `49900`). The only exceptions are the `min`/`max` price filters on `GET /products`, which are in rupees to match the website URLs.
- **Dates:** ISO 8601 strings.
- **Rate limit:** 120 requests a minute per user (or per IP for guests). Sign-in, registration and some write endpoints have tighter limits. Over the limit you get `429`.

## Authentication

Sign in to get a bearer token, then send it on every request:

```
Authorization: Bearer 12|AbCdEf…
```

Tokens last 90 days. Signing out revokes the current token, and changing the password revokes all the other ones. If an admin deactivates the account, the next request returns `403` and the token is revoked.

Buyers and sellers sign in through the same endpoint with a `role`. An account only works for its own role, and admins use the website.

| Method | Path | Body | Notes |
|---|---|---|---|
| POST | `/auth/register` | `name, email, phone, password, device_name?` | Buyers only. Returns `201 {token, token_type, user}` and sends a verification email. |
| POST | `/auth/login` | `email, password, role? (buyer\|seller), device_name?` | Returns `{token, token_type, user}`. Five failed attempts lock it for a minute. |
| POST | `/auth/logout` | | Revokes this token. |
| POST | `/auth/forgot-password` | `email` | Emails a reset link. Same response whether or not the account exists. |
| GET | `/me` | | The signed-in user. For a seller, `store` is included. |
| PATCH | `/me` | `name?, email?, phone?` | Changing the email means verifying it again. |
| PUT | `/me/password` | `current_password, password` | Signs out other devices. |
| POST | `/me/email/verification` | | Resends the verification email. |

Errors use standard HTTP codes with a JSON body:

```json
{ "message": "The email field is required.", "errors": { "email": ["The email field is required."] } }
```

- `401`: not signed in, or the token is invalid or expired
- `403`: wrong role, unverified email at checkout, store not approved (body includes `store_status`), or account deactivated
- `404`: not found, or not yours
- `422`: validation failed
- `429`: too many requests

## App configuration

`GET /config` returns what the app needs before checkout:

```json
{
  "site_name": "OneSeller", "currency": "INR", "money_unit": "paise",
  "payment_methods": ["online", "cod"], "razorpay_key_id": "rzp_live_…",
  "return_window_days": 7, "support_email": "help@talkforweb.in"
}
```

## Catalogue (public)

These work without a token. Send one anyway when signed in.

| Method | Path | Notes |
|---|---|---|
| GET | `/categories` | Main categories, each with `children`. |
| GET | `/products` | Query: `q`, `category` (slug, includes its sub-categories), `min`, `max` (rupees), `seller` (store slug), `in_stock=1`, `sort` (`newest`, `price_asc`, `price_desc`), `page`. 24 per page. |
| GET | `/products/{id}` | Full detail: `description`, `option_names`, `variants[]`, `images[]`, `category`. |
| GET | `/products/{id}/reviews` | Paginated, 20 per page. |
| GET | `/stores/{slug}` | Store profile, including a `shipping` line such as "Shipping ₹50.00 · free above ₹999.00". |
| GET | `/stores/{slug}/products` | Same filters as `/products`. |

A product in a list:

```json
{
  "id": 12, "name": "Handloom Cotton Kurta", "slug": "handloom-cotton-kurta",
  "short_description": "…", "price": 89900, "compare_at_price": 119900,
  "has_price_range": true, "in_stock": true,
  "rating": { "average": 4.5, "count": 2 },
  "image_url": "https://…/products/12/….jpg",
  "store": { "id": 2, "name": "Meera Handlooms", "slug": "meera-handlooms", "shipping": "…" },
  "url": "https://shop.talkforweb.in/product/meera-handlooms/handloom-cotton-kurta"
}
```

**Variants.** Every product has at least one variant. Products with options (`option_names: ["Size", "Colour"]`) have one variant per combination:

```json
{ "id": 40, "sku": "KURTA-M-INDIGO", "options": { "Size": "M", "Colour": "Indigo" }, "label": "M / Indigo",
  "price": 89900, "compare_at_price": 119900, "stock": 6, "in_stock": true, "weight_grams": 400, "is_default": false }
```

The product's `price` is its default (cheapest) variant's price. When `has_price_range` is true, show it as "From ₹…". Always add a **variant id** to the cart.

List endpoints are paginated: `{ "data": [...], "links": {...}, "meta": { "current_page": 1, "last_page": 3, "total": 61, ... } }`. Single items come wrapped in `data`.

## Cart

The cart works for guests and buyers.

- **Guests:** the first cart response includes a `cart_token`. Save it and send it as `X-Cart-Token` on later cart requests.
- **Signing in:** send `X-Cart-Token` with `/auth/login` or `/auth/register`, and the guest cart is merged into the buyer's cart. After that, `cart_token` is `null`.

| Method | Path | Body |
|---|---|---|
| GET | `/cart?coupon=CODE` | `coupon` is optional and previews the discount. |
| POST | `/cart/items` | `variant_id, quantity`. Adds to the existing line; quantity is capped at stock. |
| PATCH | `/cart/items/{id}` | `quantity` (0 removes the line) |
| DELETE | `/cart/items/{id}` | |

The response is grouped into **parcels**, one per seller. Each parcel has its own shipping charge:

```json
{
  "cart_token": null,
  "parcels": [{
    "store": { … },
    "items": [{ "id": 7, "product_id": 12, "name": "…", "image_url": "…", "variant": { … }, "quantity": 2, "line_total": 179800, "problem": null }],
    "subtotal": 179800, "shipping": 0, "discount": 17980
  }],
  "items_total": 179800, "shipping_total": 0, "discount_total": 17980,
  "coupon": { "code": "SAVE10", "summary": "10% off", "error": null },
  "grand_total": 161820, "count": 2, "has_problems": false
}
```

`problem` is set on a line that can't be bought as-is: "No longer available", "Out of stock" or "Only 2 left". Checkout is refused while `has_problems` is true.

## Addresses (buyer)

| Method | Path | Body |
|---|---|---|
| GET | `/addresses` | |
| POST | `/addresses` | `name, phone, address_line1, address_line2?, city, state, pincode, is_default?` |
| PATCH | `/addresses/{id}` | any of the above |
| DELETE | `/addresses/{id}` | |

`state` must be one of India's states or union territories, spelled as on the website (e.g. `Maharashtra`). `pincode` is 6 digits; `phone` is a 10-digit Indian mobile number.

## Checkout and payment (buyer, verified email)

`POST /checkout` with `{ "address_id": 3, "payment_method": "cod" | "online", "coupon": "SAVE10" }` returns `201`:

```json
{ "order": { "number": "OS-2026-000123", "status": "placed", …, "parcels": [ … ] }, "razorpay": null }
```

For **Cash on Delivery**, the order is placed at once.

For **online payment**, the order waits for payment, with stock reserved for 30 minutes:

```json
"razorpay": { "key_id": "rzp_live_…", "order_id": "order_N5…", "amount": 161820, "currency": "INR" }
```

1. Open Razorpay Checkout in the app (Razorpay's Android/iOS SDK) with `key_id`, `order_id` and `amount`.
2. On success, send the three values Razorpay returns to `POST /orders/{number}/payment`:
   `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`. The response is the paid order.
3. If the app closes before step 2, the Razorpay webhook still confirms the payment on the server.
4. To try again, `POST /orders/{number}/payment/retry` returns a fresh `razorpay` block.

If stock or the coupon changed since the cart was viewed, `/checkout` returns `422` with an `errors.cart` message. Show the cart again.

## Orders, returns, reviews, wishlist (buyer)

| Method | Path | Notes |
|---|---|---|
| GET | `/orders` | Newest first, with parcels. |
| GET | `/orders/{number}` | Each parcel has `status`, `courier`, `tracking_number`, `discount`, `total`, and `can_return`. |
| POST | `/orders/{number}/parcels/{parcelId}/returns` | `items: {orderItemId: quantity}, type (refund\|replacement), reason, details?` |
| GET | `/returns` | |
| GET | `/returns/{number}` | e.g. `OS-2026-000123-1-R1` |
| POST | `/products/{id}/reviews` | `rating (1–5), title?, body?`. Only after delivery; sending again updates the review. |
| GET | `/wishlist` | |
| POST | `/wishlist/{productId}` | |
| DELETE | `/wishlist/{productId}` | |

Return `reason` values: `damaged`, `wrong_item`, `not_as_described`, `size_fit`, `quality`, `other`. Returns are allowed within `return_window_days` of delivery.

Order statuses: `pending_payment`, `placed`, `completed`, `cancelled`.
Parcel statuses: `pending_payment`, `placed`, `accepted`, `shipped`, `delivered`, `cancelled`.
Return statuses: `requested`, `approved`, `rejected`, `received`, `refunded`, `replaced`.

## Messages (buyer and seller)

There's one conversation per buyer and store. A buyer sees conversations with stores; a seller sees conversations with buyers. Email addresses are never included.

| Method | Path | Body |
|---|---|---|
| POST | `/stores/{slug}/messages` | Buyer starts or continues: `body, product_id?, parcel_id?` |
| GET | `/conversations` | Each has `with`, `unread`, `last_message_at`. |
| GET | `/conversations/{id}` | Marks it read. Messages have `mine: true/false`. |
| POST | `/conversations/{id}/messages` | `body` |

## Seller app

These need a seller token, and the store must be approved. Every endpoint only sees the seller's own store: another store's IDs return `404`.

| Method | Path | Notes |
|---|---|---|
| GET | `/seller/dashboard` | `orders_today`, `revenue_this_month`, `awaiting_action`, `low_stock_variants`, `balance`, `unread_notifications` |
| GET | `/seller/products?status=&q=` | |
| POST | `/seller/products` | multipart: `name, category_id, short_description?, description?, sku, price, compare_at_price?, stock, weight_grams?, options[i][name], options[i][values] (array or "S, M"), images[], is_published?, submit (1 to send for approval)` |
| GET | `/seller/products/{id}` | |
| POST | `/seller/products/{id}` | Same fields as create. For products with options, send `variants[{variantId}][sku\|price\|compare_at_price\|stock\|weight_grams]` instead of the flat price/stock fields. Also `remove_images[]`. |
| DELETE | `/seller/products/{id}` | |
| GET | `/seller/inventory?low=1` | Every variant with its product. |
| PATCH | `/seller/inventory` | `{"rows": {"<variantId>": {"price": 49900, "stock": 3}}}` |
| GET | `/seller/orders?status=` | The seller's own parcels: items, commission, `net_payout`, `deliver_to` (name, phone, address; no email). |
| GET | `/seller/orders/{id}` | |
| POST | `/seller/orders/{id}/accept` | |
| POST | `/seller/orders/{id}/ship` | `courier, tracking_number` (emails the buyer) |
| POST | `/seller/orders/{id}/deliver` | Credits the earnings ledger. |
| POST | `/seller/orders/{id}/cancel` | `reason`. Only before shipping. |
| GET | `/seller/returns` | |
| GET | `/seller/returns/{number}` | |
| POST | `/seller/returns/{number}/approve` | `note?` |
| POST | `/seller/returns/{number}/reject` | `note` |
| POST | `/seller/returns/{number}/receive` | `restock` (default true) |
| POST | `/seller/returns/{number}/replace` | `courier, tracking_number` |
| GET | `/seller/earnings` | `{balance, data: [ledger entries], meta}` |
| GET | `/seller/reviews` | |
| POST | `/seller/reviews/{id}/reply` | `reply` (empty removes it) |
| GET | `/seller/notifications` | In-panel notifications: `title`, `url`, `read` |
| POST | `/seller/notifications/read` | Marks all read. |

A change to a live product's name, descriptions, options, any variant price, or images sends it back for admin approval. Stock and publish changes do not.

## Push notifications

See **Devices** in this file once push is enabled: `POST /devices` registers an FCM token for the signed-in user.
