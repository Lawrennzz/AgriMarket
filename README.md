## AgriMarket Solutions - Agricultural E-Commerce Platform

This repository contains a PHP-based e-commerce platform tailored for farmers and multi-vendors. The platform provides a digital marketplace where farmers can efficiently market their agricultural products, including livestock (cattle, poultry, hogs, etc.), crops (corn, soybeans, hay, etc.), edible forestry products (almonds, walnuts, etc.), dairy (milk products), fish farming, and miscellaneous products (honey, etc.). The platform also serves as an agricultural knowledge hub, offering insights into modern farming techniques, comparative market pricing, and streamlined agricultural workflows.

### Project Features

**1. User Authentication & Security:**
- Login/logout functionality for admin, vendor, customer, and staff with role-based access control.

**2. Customer Management:**
- User registration with profile management.
- Order history and saved preferences.

**3. Product Management:**
- Product listings with detailed specifications, categories, packaging, and pricing.
- Search and filter options for products.
- Bulk upload capability for vendors.

**4. Vendor Management:**
- Vendor registration with profile and service listings.
- Product and service management panel.
- Subscription-based vendor tiers.

**5. Staff Management:**
- Admin dashboard for managing website staff.
- Task assignment and performance tracking.

**6. Order & Shopping Cart System:**
- Shopping cart with product comparison feature.
- Multiple payment gateway integrations (credit/debit cards, mobile payments, bank transfers).
- Real-time order tracking and delivery management.
- Customer order history and reordering features.

**7. Ratings & Reviews:**
- Product and vendor rating system.
- Customer feedback and comment moderation.

**8. Notifications & Alerts:**
- Email/SMS/Push notifications for orders, and promotional updates.
- Low-stock alerts for vendors.

**9. Account & System Settings:**
- Profile management for customers and vendors.

**10. Reports & Analytics:**
- Reports on the most searched products and vendors.
- Insights into most visited product pages.
- Data on the most popularly ordered products.
- Sales reports (weekly, monthly, quarterly, annually).

### Development Requirements

The e-commerce website development must cover the following:
- **Technologies:** HTML, CSS, JavaScript, PHP, XHTML and/or HTML5.
- **Programming Elements:** The website must include appropriate variables and constants declaration, conditional and looping control structures, functions, one-dimension array, multidimensional array, string processing, files, directories, and object-oriented programming.
- **Database:** The data on the server should be stored in an SQL database.
- **Server-Side Scripts:** Server-side scripts need to be written only in PHP.
- **Restrictions:** External libraries and frameworks as well as existing e-commerce and automatic code generators or template-based designers are not permitted.
- **Media Attribution:** Media from external sources are permitted to use but must include references.
- **Footer Requirement:** All web pages must contain a footer stating that the business is fictitious and part of a university course.
- **Navigation:** All the pages on the website must be linked together.

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

**1. User Authentication & Security:**
- `login.php`, `logout.php`, authentication utilities in `includes/`, role-based access control across admin/vendor/customer/staff pages.

**2. Customer Management:**
- `register.php`, `manage_users.php`, `profile.php`, order history in `my_orders.php`, saved preferences (wishlist in `wishlist.php`).

**3. Product Management:**
- `products.php`, `product.php`, `product_details.php`, `manage_products.php`, `product_upload.php`, search/filter in `search.php`, bulk upload capabilities. Classes in `classes/`, templates in `templates/`.

**4. Vendor Management:**
- `vendor_register.php`, `vendors.php`, `vendor.php`, `vendor_dashboard.php`, `vendor_profile.php`, `manage_vendors.php`, vendor management panels.

**5. Staff Management:**
- `manage_staff.php`, `add_staff.php`, `edit_staff.php`, `staff_dashboard.php`, `my_performance.php`, `my_tasks.php`, task assignment and tracking.

**6. Order & Shopping Cart System:**
- `cart.php`, `compare_products.php`, `checkout.php`, `payment_processor.php`, `orders.php`, `manage_orders.php`, `my_orders.php`, `order_details.php`, `order_confirmation.php`, `print_receipt.php`, real-time order tracking.

**7. Ratings & Reviews:**
- `add_review.php`, `add-review.php`, `reviews.php`, `manage_reviews.php`, rating systems for products and vendors.

**8. Notifications & Alerts:**
- `notifications.php`, email/SMS notifications (via `test_email.php`, `test_order_email.php`), low-stock alerts for vendors.

**9. Account & System Settings:**
- `settings.php`, `update_settings.php`, `profile.php`, profile management for customers and vendors.

**10. Reports & Analytics:**
- `admin/reports.php`, `admin/analytics_dashboard.php`, `admin/most_viewed_products.php`, `vendor_reports.php`, `view_reports.php`, `vendor_advanced_analytics.php`, SQL tracking in `sql/` for searches, views, orders, and sales (weekly/monthly/quarterly/annually).

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

## Knowledge Hub

The platform includes agricultural knowledge resources:
- `knowledge_hub.php`: Agricultural knowledge and resources
- `farming_techniques.php`: Modern farming techniques and best practices
- `market_pricing.php`: Comparative market pricing information
- `agricultural_workflows.php`: Streamlined agricultural workflows and guides

## Attribution

- External media (images, icons, etc.) used in this project must include references/credits in the code comments or a dedicated CREDITS section.

## License

This project is for academic purposes and client demonstration. Review your institution’s policies before reuse.


