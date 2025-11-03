## Wedding E‑Commerce (AgriMarket fork)

This repository contains a PHP-based Wedding E‑Commerce website intended for wedding planner vendors and couples. Vendors can list products and services (attire, flowers, paper goods, favors and gifts, venue, photographers, printing, rings, etc.). Couples can discover vendors/products, compare options, book appointments, and place orders efficiently for their wedding day.

### Project description (as provided)
- Login/logout
- Manage customers
- Products specifications, categories, packages, prices, etc.
- Manage products
- Vendors information, services offered, etc.
- Manage vendors
- Manage staffs
- Manage appointments
- Manage booking and/or orders, shopping cart, payment, delivery, customer’s past orders, etc.
- Products and vendor’s rating
- Customer’s and appointment notifications
- Manage account and system setting
- Reports (for vendors/customers):
  - The most searched products and/or vendors
  - The most popularly visited product pages
  - The most popularly ordered products
  - Sales (weekly/monthly/annually/quarterly)
- Additional features may be specified with justification

### Development requirements
- Technologies: HTML, CSS, JavaScript, PHP (server-side only), and SQL database.
- Use variables/constants, conditionals, loops, functions, arrays/multidimensional arrays, string processing, files/directories, and object‑oriented programming.
- No external frameworks, template generators, or code scaffolding; plain PHP only.
- Media from external sources must include references.
- All web pages must include a footer stating the business is fictitious and part of a university course.
- All pages must be linked together.

## Repository structure (what we do in each folder)

- `admin/`: Administrative interfaces for managing products, vendors, staff, orders, analytics, and reports (e.g., `analytics_dashboard.php`, `most_viewed_products.php`, `reports.php`).
- `classes/`: Object‑oriented PHP classes for core domain models and pages (e.g., `Product.php`, `ProductsPage.php`, `ProductPage.php`, `Database.php`, `AuditLog.php`).
- `includes/`: Reusable server‑side modules and helpers (e.g., authentication, analytics tracking like `track_analytics.php`, product view tracking, update routines). Included via `include`/`require` from pages.
- `templates/`: View templates for page layouts and reusable page sections for products and listings.
- `assets/`: Frontend static assets (JavaScript and images specific to features like product view tracking in `assets/js/`).
- `css/` and `styles.css`: Stylesheets for the site (global and component‑level).
- `images/` and `uploads/`: Static images and user/vendor uploaded files (e.g., product photos). Consider ignoring large uploaded files if not needed for version control.
- `database/` and `sql/`: SQL schema, views, triggers, and update scripts (e.g., `setup.sql`, `create_product_views_table.sql`, `product_view_tracking.sql`). Used to initialize and update the database.
- `logs/`: HTML/log files for diagnostics and auditing where applicable.
- `tools/`: Utility scripts for maintenance or diagnostics.
- Root PHP pages (e.g., `index.php`, `products.php`, `product_details.php`, `orders.php`, `login.php`, `register.php`, etc.): Entry points for user/vendor/admin flows.

## How the repo maps to the requirements

- Login/logout: `login.php`, `logout.php`, auth utilities in `includes/`.
- Manage customers: `manage_users.php`, `profile.php`, supporting `classes/` and `includes/`.
- Products specs/categories/prices & Manage products: `products.php`, `product.php`, `product_details.php`, `manage_products.php`, classes in `classes/`, templates in `templates/`.
- Vendor info & Manage vendors: `vendors.php`, `vendor.php`, `manage_vendors.php`, vendor dashboards in `admin/`.
- Manage staffs: `manage_staff.php`, `edit_staff.php` with related includes/classes.
- Appointments: `manage_orders.php` (and appointment pages if added), notifications in `notifications.php`.
- Orders/cart/payment/delivery/history: `cart.php`, `checkout.php`, `orders.php`, `my_orders.php`, `payment_processor.php`, `order_details.php`, `print_receipt.php`.
- Ratings: `add_review.php`, `reviews.php`, `manage_reviews.php`.
- Notifications: `notifications.php`, plus supporting includes.
- Account & system settings: `settings.php`, `update_settings.php`, `includes/` helpers.
- Reports: `admin/reports.php`, `admin/analytics_dashboard.php`, `admin/most_viewed_products.php`, `vendor_reports.php`, `view_reports.php`, plus SQL in `sql/` for tracking searches, views, orders, and sales.

## Setup

1) Prerequisites
   - XAMPP or similar (Apache + PHP + MySQL/MariaDB)
   - PHP 8.x recommended

2) Clone
```
git clone https://github.com/Lawrennzz/AgriMarket.git
```

3) Configure
- Copy `.env.example` to `.env` (if present) OR update `config.php` with your DB credentials.
- Ensure `uploads/` is writable by the web server.

4) Database
- Import base schema: `database/setup.sql` (or `database.sql` if provided).
- Apply analytics/view tracking scripts from `sql/` (e.g., `create_product_views_table.sql`, triggers, views). Order is documented inside the SQL files when applicable.

5) Run
- Place the project under your web root (e.g., `htdocs/AgriMarket`) and open `http://localhost/AgriMarket/`.

## Development guidelines

- Plain PHP (no frameworks). Keep business logic in `classes/`, reuse server‑side logic via `includes/`.
- Follow OOP where appropriate; use clear, descriptive variable and function names.
- Avoid committing large binary uploads unless necessary; prefer sample assets.
- Each page must include the required academic footer stating the business is fictitious and part of a university course.

## Reports and analytics

- Product searches, views, orders, and sales summaries are supported via SQL objects and admin/vendor pages. See `admin/analytics_dashboard.php`, `admin/most_viewed_products.php`, `admin/reports.php`, and the `sql/` folder.

## Attribution

- External media (images, icons, etc.) used in this project must include references/credits in the code comments or a dedicated CREDITS section.

## License

This project is for academic purposes and client demonstration. Review your institution’s policies before reuse.


