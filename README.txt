=== MMe Elementor Kit ===
Contributors: mmevn
Plugin URI: https://mme.vn
Tags: elementor, templates, template library, acf, scf
Requires at least: 5.5
Requires PHP: 7.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Bộ công cụ Elementor của MMe.vn: chia sẻ template Master → Client và widget động (ACF/SCF).

== Description ==

* Master (Server): tạo secret key, chọn template / danh mục được chia sẻ và giới hạn số site cho mỗi key.
* Client: kết nối tới site Master bằng URL + secret key, chèn template từ tab "MMe Library" trong thư viện Elementor.
* MMe Widgets: "Info Table (ACF)" — bảng thông tin label / giá trị lấy từ field ACF/SCF, post meta, shortcode hoặc dynamic tag.

Site Master và Client đều cần cài plugin này cùng Elementor.

== Credits ==

Phát triển dựa trên "Elements Share for Elementor" của DivDojo (GPL-2.0+).

== Changelog ==

= 1.1.0 =
* Đổi tên thành MMe Elementor Kit (MMe.vn).
* New: separate Master (Server) and Client admin pages with a redesigned UI.
* New: Master dashboard stats, copyable site URL / keys, per-key usage bar and "reset usage".
* New: Client connection status, connection test and preview of the templates shared by the master.
* New: "Info Table (ACF)" Elementor widget (MMe Widgets category) — label/value rows from ACF/SCF fields, post meta, shortcodes or dynamic tags.
* New: Export / Import secret keys (JSON) to move the master to another site; templates and categories are re-matched by slug or title.
* Changed: REST API namespace is now `wp-json/mme-kit/v1` — master and client sites must both run 1.1.0+.
* Security: nonce and capability checks on all admin ajax actions.
* Fix: a key set to "Specific templates" with no template selected no longer exposes every template.
